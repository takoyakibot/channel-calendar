<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Models\Stream;
use App\Services\TwitchService;
use App\Support\StreamTagger;
use App\Support\TwitchLogin;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Twitch side of the calendar for members with a twitch_login: what is live,
 * the scheduled segments, and recent archives — stored as streams with
 * platform "twitch" so they share the member's colour and column.
 */
class FetchTwitchStreams extends Command
{
    protected $signature = 'streams:fetch-twitch';

    protected $description = 'Fetch live, scheduled and archived Twitch broadcasts for channels with a Twitch login';

    /** A scheduled segment is considered done (started, skipped or superseded) this long after its start. */
    private const SEGMENT_GRACE_HOURS = 3;

    /** @var array<int, int> stream ids touched this run, for tagging */
    private array $touched = [];

    public function handle(TwitchService $twitch): int
    {
        if (! $twitch->isConfigured()) {
            $this->info('Twitch credentials are not configured; nothing fetched.');

            return self::SUCCESS;
        }

        $channels = Channel::active()->whereNotNull('twitch_login')->get();
        if ($channels->isEmpty()) {
            $this->info('No channels with a Twitch login.');

            return self::SUCCESS;
        }

        $windowStart = now()->subDays((int) config('services.youtube.backfill_days', 14));

        foreach ($channels as $channel) {
            $this->info("Fetching Twitch for: {$channel->name} (twitch.tv/{$channel->twitch_login})");
            try {
                $this->syncChannel($twitch, $channel, $windowStart);
            } catch (\Throwable $e) {
                $this->error("Failed for {$channel->name}: {$e->getMessage()}");
                Log::error("streams:fetch-twitch failed for {$channel->twitch_login}: {$e->getMessage()}");
            }
        }

        if ($this->touched !== []) {
            $tagger = app(StreamTagger::class);
            foreach (Stream::whereIn('id', $this->touched)->get(['id', 'title', 'channel_id']) as $stream) {
                $tagger->tag($stream);
            }
        }

        $this->info('Done.');

        return self::SUCCESS;
    }

    private function syncChannel(TwitchService $twitch, Channel $channel, Carbon $windowStart): void
    {
        if (! $channel->twitch_user_id) {
            $user = $twitch->getUserByLogin($channel->twitch_login);
            if (! $user) {
                $this->warn("  Twitch user not found: {$channel->twitch_login}");

                return;
            }
            $channel->forceFill(['twitch_user_id' => $user['id']])->save();
        }
        $userId = $channel->twitch_user_id;
        $channelUrl = TwitchLogin::url($channel->twitch_login);

        // 1. Live now. Keyed by the Twitch stream id so the VOD (which carries the
        //    same stream_id) later completes this very row.
        $live = $twitch->getLiveStream($userId);
        $liveKey = null;
        if ($live) {
            $liveKey = 'tw:' . $live['id'];
            $this->upsert($channel, $liveKey, [
                'title' => $live['title'] !== '' ? $live['title'] : 'Twitch 配信',
                'thumbnail_url' => $live['thumbnail_url'],
                'scheduled_at' => Carbon::parse($live['started_at'])->utc(),
                'actual_start_at' => Carbon::parse($live['started_at'])->utc(),
                'actual_end_at' => null,
                'status' => 'live',
                'url' => $channelUrl,
                'duration_seconds' => null,
            ]);
        }

        // 2. Archives: completed broadcasts in the backfill window (older ones only
        //    if we already hold them, e.g. a live row that just ended).
        foreach ($twitch->getArchives($userId) as $vod) {
            $key = $vod['stream_id'] ? 'tw:' . $vod['stream_id'] : 'tw:vod:' . $vod['id'];
            $createdAt = Carbon::parse($vod['created_at'])->utc();
            if ($createdAt->lt($windowStart) && ! Stream::where('video_id', $key)->exists()) {
                continue;
            }
            $this->upsert($channel, $key, [
                'title' => $vod['title'] !== '' ? $vod['title'] : 'Twitch 配信',
                'thumbnail_url' => $vod['thumbnail_url'],
                'scheduled_at' => $createdAt,
                'actual_start_at' => $createdAt,
                'actual_end_at' => $createdAt->copy()->addSeconds($vod['duration_seconds']),
                'status' => 'completed',
                'url' => $vod['url'],
                'duration_seconds' => $vod['duration_seconds'] ?: null,
            ]);
        }

        // 3. A live row that is no longer live and got no VOD (VODs off, or not yet
        //    processed): close it at the time we noticed.
        Stream::where('channel_id', $channel->id)->where('platform', 'twitch')->where('status', 'live')
            ->when($liveKey, fn ($q) => $q->where('video_id', '!=', $liveKey))
            ->get()
            ->each(function (Stream $s) {
                $s->forceFill(['status' => 'completed', 'actual_end_at' => now()])->save();
                $this->touched[] = $s->id;
            });

        // 4. Schedule segments → upcoming rows; segments Twitch no longer lists
        //    (cancelled, moved) disappear with them.
        $seen = [];
        foreach ($twitch->getSchedule($userId) as $seg) {
            $startsAt = Carbon::parse($seg['start_time'])->utc();
            if ($startsAt->lt(now()->subHours(self::SEGMENT_GRACE_HOURS))) {
                continue;
            }
            $key = 'tw:sched:' . substr(sha1($seg['id']), 0, 16);
            $seen[] = $key;
            $this->upsert($channel, $key, [
                'title' => $seg['title'] !== '' ? $seg['title'] : ($seg['category'] ? "Twitch 配信予定（{$seg['category']}）" : 'Twitch 配信予定'),
                'thumbnail_url' => null,
                'scheduled_at' => $startsAt,
                'actual_start_at' => null,
                'actual_end_at' => null,
                'status' => 'upcoming',
                'url' => $channelUrl,
                'duration_seconds' => null,
            ], onlyIfUpcoming: true);
        }
        Stream::where('channel_id', $channel->id)->where('platform', 'twitch')
            ->where('video_id', 'like', 'tw:sched:%')
            ->whereNotIn('video_id', $seen)
            ->delete();

        // 5. A segment whose slot a real broadcast covered is redundant; one that
        //    passed its grace period without a broadcast is stale.
        $starts = Stream::where('channel_id', $channel->id)->where('platform', 'twitch')
            ->whereNotNull('actual_start_at')->where('actual_start_at', '>=', now()->subDays(2))
            ->pluck('actual_start_at');
        Stream::where('channel_id', $channel->id)->where('platform', 'twitch')
            ->where('video_id', 'like', 'tw:sched:%')
            ->get()
            ->each(function (Stream $seg) use ($starts) {
                $covered = $starts->contains(fn ($at) => Carbon::parse($at)->between(
                    $seg->scheduled_at->copy()->subHour(),
                    $seg->scheduled_at->copy()->addHours(self::SEGMENT_GRACE_HOURS)
                ));
                if ($covered || $seg->scheduled_at->lt(now()->subHours(self::SEGMENT_GRACE_HOURS))) {
                    $seg->delete();
                }
            });
    }

    private function upsert(Channel $channel, string $key, array $attributes, bool $onlyIfUpcoming = false): void
    {
        $existing = Stream::where('video_id', $key)->first();
        if ($onlyIfUpcoming && $existing && $existing->status !== 'upcoming') {
            return;
        }
        $stream = Stream::updateOrCreate(['video_id' => $key], $attributes + [
            'channel_id' => $channel->id,
            'type' => 'stream',
            'platform' => 'twitch',
            'is_members_only' => false,
        ]);
        $this->touched[] = $stream->id;
    }
}
