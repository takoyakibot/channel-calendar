<?php

namespace App\Console\Commands;

use App\Models\Stream;
use App\Services\XPoster;
use App\Services\XPosterException;
use App\Support\StreamAnnouncement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AnnounceStreams extends Command
{
    protected $signature = 'streams:announce
        {--dry-run : Print the posts that would be made without posting or marking anything}';

    protected $description = 'Post new public reservations and streams already on air to X, oldest first, within the free-plan limits';

    public function handle(XPoster $poster): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if (! $dryRun && ! $poster->isConfigured()) {
            $this->info('X credentials are not configured; nothing announced.');

            return self::SUCCESS;
        }

        $limits = config('services.x.announce');

        // Only real posts (with a tweet id) count: rows backfilled as "announced"
        // when the feature shipped must not eat into the allowance.
        $posted = Stream::whereNotNull('announced_tweet_id');
        $room = min(
            (int) $limits['daily_limit'] - (clone $posted)->where('announced_at', '>=', now()->subDay())->count(),
            (int) $limits['monthly_limit'] - (clone $posted)->where('announced_at', '>=', now()->startOfMonth())->count(),
        );

        if ($room <= 0) {
            $this->info('X posting allowance used up for now; queued streams wait for the next window.');

            return self::SUCCESS;
        }

        // Fresh public streams, oldest first: reservations that have not started yet
        // ("新しい配信予定"), and streams currently on air ("配信中" — started without a
        // reservation, or a frame that went live before we announced it). A frame
        // that started and already ended is left alone; announcing it late is noise.
        $candidates = Stream::with('channel.groups')
            ->whereHas('channel', fn ($q) => $q->where('is_active', true))
            ->where('is_members_only', false)
            ->whereNull('announced_at')
            ->where(function ($q) {
                $q->where(fn ($u) => $u->where('status', 'upcoming')->where('scheduled_at', '>', now()))
                  ->orWhere('status', 'live');
            })
            ->where('created_at', '>=', now()->subDays((int) $limits['queue_days']))
            ->orderBy('created_at')
            ->limit($room)
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('No new streams to announce.');

            return self::SUCCESS;
        }

        $count = 0;
        foreach ($candidates as $stream) {
            $text = StreamAnnouncement::text($stream);

            if ($dryRun) {
                $this->line("--- would post for {$stream->video_id}:");
                $this->line($text);
                continue;
            }

            try {
                $tweetId = $poster->post($text);
            } catch (XPosterException $e) {
                $this->error("Failed to announce {$stream->video_id}: {$e->getMessage()}");
                Log::warning("streams:announce failed for {$stream->video_id} (HTTP {$e->getCode()}): {$e->getMessage()}");
                if ($e->affectsWholeRun()) {
                    // Rate limited or out of X credits: every further post would fail
                    // the same way, so leave the rest queued for the next run.
                    $this->warn($e->isOutOfCredits()
                        ? 'X credits depleted — top up in the X Developer Console; queued streams will post once credits are available.'
                        : 'X rate limit hit — remaining streams wait for the next run.');
                    break;
                }
                continue;
            }

            $stream->forceFill(['announced_at' => now(), 'announced_tweet_id' => $tweetId])->save();
            $this->line("  announced {$stream->video_id} → tweet {$tweetId}");
            $count++;
        }

        $this->info($dryRun ? 'Dry run done.' : "Done. {$count} stream(s) announced.");

        return self::SUCCESS;
    }
}
