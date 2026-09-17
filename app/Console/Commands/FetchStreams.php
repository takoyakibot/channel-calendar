<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Models\ManualSchedule;
use App\Models\Setting;
use App\Models\Stream;
use App\Services\YouTubeService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class FetchStreams extends Command
{
    public const LAST_FETCHED_AT_KEY = 'streams_last_fetched_at';

    protected $signature = 'streams:fetch
        {--watched : Only channels with a manual schedule in its watch window, plus due/live streams by id (no sweeps, not recorded as a full fetch)}';
    protected $description = 'Fetch upcoming, live and recently ended streams from YouTube for all active channels';

    /**
     * video_ids upserted during this run, across all channels.
     *
     * @var array<int, string>
     */
    private array $fetchedVideoIds = [];

    /**
     * Channel primary keys whose fetch threw during this run.
     *
     * @var array<int, int>
     */
    private array $failedChannelIds = [];

    /**
     * Which playlist each video id of the channel being synced came from, so the
     * members-only flag is set from evidence and left alone when we have none.
     *
     * @var array<int, string>
     */
    private array $publicIds = [];

    /** @var array<int, string> */
    private array $membersOnlyIds = [];

    public function handle(YouTubeService $youtube): int
    {
        $watched = (bool) $this->option('watched');
        $dueStreams = collect();

        if ($watched) {
            // A manual schedule says "this channel should go live about now": poll
            // just those channels so the real stream replaces the entry quickly.
            $channels = Channel::active()
                ->whereIn('id', ManualSchedule::inWatchWindow()->select('channel_id'))
                ->get();

            // Reserved frames whose time has come, and streams currently live, are
            // re-checked by id so the board flips to live / ended within minutes.
            $dueStreams = Stream::with('channel')
                ->whereHas('channel', fn ($q) => $q->active())
                ->whereNotIn('channel_id', $channels->pluck('id'))
                ->where(function ($q) {
                    $q->where(fn ($u) => $u->where('status', 'upcoming')->whereBetween('scheduled_at', [
                        now()->subHours(ManualSchedule::WATCH_AFTER_HOURS),
                        now()->addMinutes(ManualSchedule::WATCH_BEFORE_MINUTES),
                    ]))
                      ->orWhere('status', 'live');
                })
                ->get();

            if ($channels->isEmpty() && $dueStreams->isEmpty()) {
                $this->info('Nothing to watch: no manual schedule in its window and no stream due.');

                return self::SUCCESS;
            }
        } else {
            $channels = Channel::active()->get();

            if ($channels->isEmpty()) {
                $this->info('No active channels found.');
                Setting::set(self::LAST_FETCHED_AT_KEY, now()->toIso8601String());

                return self::SUCCESS;
            }
        }

        $windowStart = now()->subDays((int) config('services.youtube.backfill_days', 14));

        foreach ($channels as $channel) {
            $this->info("Fetching streams for: {$channel->name}");

            try {
                [$upserted, $deleted] = $this->syncChannel($youtube, $channel, $windowStart);
                $this->line("  {$upserted} stream(s) upserted, {$deleted} removed");
            } catch (\Throwable $e) {
                $this->error("Failed for {$channel->name}: {$e->getMessage()}");
                Log::error("streams:fetch failed for channel {$channel->channel_id}: {$e->getMessage()}");
                $this->failedChannelIds[] = $channel->id;
            }
        }

        if ($dueStreams->isNotEmpty()) {
            $this->refreshDueStreams($youtube, $dueStreams, $windowStart);
        }

        $this->removeOverlappingManualSchedules();

        // The watched run only refreshed a handful of channels, so the age-based
        // sweeps and the "last full fetch" marker would be misleading.
        if (! $watched) {
            $this->markOldStreamsCompleted();
            $this->removePastManualSchedules();
            Setting::set(self::LAST_FETCHED_AT_KEY, now()->toIso8601String());
        }

        $this->info('Done.');

        return self::SUCCESS;
    }

    /**
     * Pull the channel's recent uploads, then reconcile every stream we hold
     * inside the observable window against YouTube so deletions are followed.
     *
     * @return array{0: int, 1: int} [upserted, deleted]
     */
    private function syncChannel(YouTubeService $youtube, Channel $channel, Carbon $windowStart): array
    {
        $upserted = 0;
        $seen = [];

        $this->publicIds = array_values(array_unique($youtube->listRecentUploadIds($channel->channel_id)));
        $this->membersOnlyIds = array_values(array_unique($youtube->listMembersOnlyUploadIds($channel->channel_id)));
        $videoIds = array_values(array_unique(array_merge($this->publicIds, $this->membersOnlyIds)));
        foreach ($this->fetchDetails($youtube, $videoIds) as $detail) {
            $seen[] = $detail['video_id'];
            if ($this->upsertDetail($channel, $detail, $windowStart)) {
                $upserted++;
            }
        }

        // Streams inside the window that the uploads list did not mention: either
        // they fell off the top of the list (still exist → refresh) or YouTube no
        // longer serves them (deleted / private → drop them too).
        $unseen = Stream::where('channel_id', $channel->id)
            ->where('scheduled_at', '>=', $windowStart)
            ->whereNotIn('video_id', $seen)
            ->pluck('video_id')
            ->all();

        $deleted = 0;
        if (! empty($unseen)) {
            $stillThere = [];
            foreach ($this->fetchDetails($youtube, $unseen) as $detail) {
                $stillThere[] = $detail['video_id'];
                if ($this->upsertDetail($channel, $detail, $windowStart)) {
                    $upserted++;
                }
            }

            $gone = array_values(array_diff($unseen, $stillThere));
            if (! empty($gone)) {
                $deleted = Stream::where('channel_id', $channel->id)->whereIn('video_id', $gone)->delete();
                Log::info("streams:fetch removed {$deleted} stream(s) no longer on YouTube for channel {$channel->channel_id}: " . implode(',', $gone));
            }
        }

        return [$upserted, $deleted];
    }

    /**
     * Re-read known streams straight from videos.list (1 quota unit per 50 ids,
     * no playlist calls). Rows YouTube no longer returns were deleted or made
     * private, so they are dropped like in the channel-wide reconciliation.
     *
     * @param  \Illuminate\Support\Collection<int, Stream>  $streams
     */
    private function refreshDueStreams(YouTubeService $youtube, $streams, Carbon $windowStart): void
    {
        // No playlist evidence for these ids: leave the members-only flag as stored.
        $this->publicIds = [];
        $this->membersOnlyIds = [];

        try {
            $details = collect($this->fetchDetails($youtube, $streams->pluck('video_id')->all()))->keyBy('video_id');
        } catch (\Throwable $e) {
            $this->error("Failed to refresh due streams: {$e->getMessage()}");
            Log::error("streams:fetch --watched failed to refresh due streams: {$e->getMessage()}");

            return;
        }

        $refreshed = 0;
        $gone = [];
        foreach ($streams as $stream) {
            $detail = $details->get($stream->video_id);
            if ($detail === null) {
                $gone[] = $stream->video_id;
                continue;
            }
            if ($this->upsertDetail($stream->channel, $detail, $windowStart)) {
                $refreshed++;
            }
        }

        if (! empty($gone)) {
            Stream::whereIn('video_id', $gone)->delete();
            Log::info('streams:fetch --watched removed ' . count($gone) . ' stream(s) no longer on YouTube: ' . implode(',', $gone));
        }

        $this->line("  {$refreshed} due stream(s) refreshed, " . count($gone) . ' removed');
    }

    /** videos.list accepts at most 50 ids per call. */
    private function fetchDetails(YouTubeService $youtube, array $videoIds): array
    {
        $details = [];
        foreach (array_chunk($videoIds, 50) as $chunk) {
            $details = array_merge($details, $youtube->getVideoDetails($chunk));
        }

        return $details;
    }

    private function upsertDetail(Channel $channel, array $detail, Carbon $windowStart): bool
    {
        // Plain uploads have no liveStreamingDetails — they are not streams.
        // Streams started without a reservation are broadcasts too: they have
        // no scheduledStartTime, so YouTubeService falls back to actualStartTime.
        if (! $detail['is_broadcast'] || $detail['scheduled_at'] === null) {
            return false;
        }

        // Keep every upcoming/live broadcast, but only backfill ended ones from
        // the recent window so the board shows history without importing the
        // whole archive.
        if ($detail['status'] === 'completed' && Carbon::parse($detail['scheduled_at'])->lt($windowStart)) {
            return false;
        }

        $attributes = [
            'channel_id' => $channel->id,
            'title' => $detail['title'],
            'thumbnail_url' => $detail['thumbnail_url'],
            'scheduled_at' => $detail['scheduled_at'],
            'actual_start_at' => $detail['actual_start_at'],
            'actual_end_at' => $detail['actual_end_at'],
            'status' => $detail['status'],
        ];

        if (in_array($detail['video_id'], $this->membersOnlyIds, true)) {
            $attributes['is_members_only'] = true;
        } elseif (in_array($detail['video_id'], $this->publicIds, true)) {
            $attributes['is_members_only'] = false;
        }
        // Otherwise the video was only re-verified via videos.list; keep the stored flag.

        Stream::updateOrCreate(['video_id' => $detail['video_id']], $attributes);

        $this->fetchedVideoIds[] = $detail['video_id'];

        return true;
    }

    /**
     * Streams older than the window are never re-verified, so an upcoming/live
     * row that was never confirmed as ended is closed out here by age.
     */
    private function markOldStreamsCompleted(): void
    {
        $query = Stream::whereIn('status', ['upcoming', 'live'])
            ->where('scheduled_at', '<', now()->subHours(24));

        if (! empty($this->fetchedVideoIds)) {
            $query->whereNotIn('video_id', $this->fetchedVideoIds);
        }

        if (! empty($this->failedChannelIds)) {
            $query->whereNotIn('channel_id', $this->failedChannelIds);
        }

        $query->update(['status' => 'completed']);
    }

    /**
     * Remove manual schedules when a real stream exists for the same channel
     * within a 1-hour window of the manual schedule's time.
     */
    private function removeOverlappingManualSchedules(): void
    {
        // Manual schedules are few, so one existence query per row keeps this
        // portable across MySQL and SQLite instead of relying on DATE_SUB/INTERVAL.
        $overlapping = ManualSchedule::query()
            ->get(['id', 'channel_id', 'scheduled_at', 'is_all_day'])
            ->filter(function (ManualSchedule $manual) {
                $at = Carbon::parse($manual->scheduled_at);
                // All-day entries stand for "some time that day": any real stream of the
                // channel within the 24h starting at the stored (local) midnight replaces them.
                [$from, $to] = $manual->is_all_day
                    ? [$at->copy(), $at->copy()->addDay()]
                    : [$at->copy()->subHour(), $at->copy()->addHour()];

                return Stream::where('channel_id', $manual->channel_id)
                    ->whereBetween('scheduled_at', [$from, $to])
                    ->exists();
            })
            ->pluck('id')
            ->all();

        if (empty($overlapping)) {
            return;
        }

        $removed = ManualSchedule::whereIn('id', $overlapping)->delete();
        $this->line("  {$removed} overlapping manual schedule(s) removed");
    }

    private function removePastManualSchedules(): void
    {
        // Timed entries stay through their watch window (the streamer may be
        // late); all-day entries expire when their day is over.
        $removed = ManualSchedule::where(function ($q) {
            $q->where(fn ($t) => $t->where('is_all_day', false)->where('scheduled_at', '<', now()->subHours(ManualSchedule::WATCH_AFTER_HOURS)))
              ->orWhere(fn ($a) => $a->where('is_all_day', true)->where('scheduled_at', '<', now()->subDay()));
        })->delete();
        if ($removed > 0) {
            $this->line("  {$removed} past manual schedule(s) removed");
        }
    }
}
