<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Models\Stream;
use App\Services\YouTubeService;
use App\Support\StreamTagger;
use App\Support\StreamUpserter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Import a channel's whole YouTube history (streams, uploads and shorts) by
 * walking its uploads and members-only playlists 50 videos at a time.
 *
 * Each page costs 2 quota units (playlistItems + videos.list), so a run stops
 * after --pages pages and the next run resumes from the saved page token.
 * Channels that reached the end are skipped unless --reset is given.
 */
class BackfillStreams extends Command
{
    private const LISTS = ['uploads' => false, 'members' => true];

    protected $signature = 'streams:backfill
        {channel?* : YouTube channel ids (UC…) to backfill; all active channels when omitted}
        {--pages=100 : Stop after this many playlist pages in this run (2 quota units each)}
        {--reset : Start the given channels over from the newest video}';

    protected $description = 'Import past YouTube videos (streams, uploads, shorts) of channels, resumable and quota-capped';

    public function handle(YouTubeService $youtube, StreamUpserter $upserter): int
    {
        $budget = max(1, (int) $this->option('pages'));
        $ids = $this->argument('channel');

        $query = Channel::active();
        if (! empty($ids)) {
            $query->whereIn('channel_id', $ids);
        }
        $channels = $query->orderBy('id')->get();

        $unknown = array_diff($ids, $channels->pluck('channel_id')->all());
        if (! empty($unknown)) {
            $this->warn('Not found or inactive: ' . implode(', ', $unknown));
        }

        if ($this->option('reset')) {
            if (empty($ids)) {
                $this->error('--reset needs channel ids, so a typo cannot restart every channel.');

                return self::FAILURE;
            }
            foreach ($channels as $channel) {
                $channel->forceFill(['backfill_state' => null, 'backfill_completed_at' => null])->save();
            }
        }

        $touched = [];
        foreach ($channels as $channel) {
            if ($budget <= 0) {
                break;
            }
            if ($channel->backfill_completed_at !== null) {
                continue;
            }
            // YouTube channel ids only; Twitch / TikTok-only channels have nothing to walk.
            if (! str_starts_with((string) $channel->channel_id, 'UC')) {
                continue;
            }

            $this->info("Backfilling: {$channel->name}");
            try {
                [$pages, $saved] = $this->backfillChannel($youtube, $upserter, $channel, $budget, $touched);
            } catch (\Throwable $e) {
                // Progress up to the last finished page is saved; the next run resumes there.
                $this->error("Failed for {$channel->name}: {$e->getMessage()}");
                Log::error("streams:backfill failed for channel {$channel->channel_id}: {$e->getMessage()}");

                break;
            }
            $budget -= $pages;
            $state = $channel->backfill_completed_at ? 'done' : 'paused (resumes next run)';
            $this->line("  {$pages} page(s), {$saved} video(s) saved, {$state}");
        }

        if (! empty($touched)) {
            $tagger = app(StreamTagger::class);
            Stream::whereIn('id', array_keys($touched))->select('id', 'title', 'channel_id')->chunkById(200, function ($streams) use ($tagger) {
                foreach ($streams as $stream) {
                    $tagger->tag($stream);
                }
            });
            $tagger->rebuildUnmatched();
        }

        $this->info('Done.');

        return self::SUCCESS;
    }

    /**
     * @param  array<int, true>  $touched  stream ids saved in this run (filled in)
     * @return array{0: int, 1: int} [pages read, videos saved]
     */
    private function backfillChannel(YouTubeService $youtube, StreamUpserter $upserter, Channel $channel, int $budget, array &$touched): array
    {
        $state = $channel->backfill_state ?? [];
        $pages = 0;
        $saved = 0;

        foreach (self::LISTS as $list => $membersOnly) {
            while (empty($state[$list]['done']) && $pages < $budget) {
                $page = $youtube->listUploadIdsPage($channel->channel_id, $membersOnly, $state[$list]['token'] ?? null);
                $pages++;

                foreach ($page['ids'] === [] ? [] : $youtube->getVideoDetails($page['ids']) as $detail) {
                    if ($stream = $upserter->upsert($channel, $detail, null, $membersOnly)) {
                        $touched[$stream->id] = true;
                        $saved++;
                    }
                }

                $state[$list] = $page['next'] === null ? ['done' => true] : ['token' => $page['next']];
                $channel->forceFill(['backfill_state' => $state])->save();
            }
        }

        if (! empty($state['uploads']['done']) && ! empty($state['members']['done'])) {
            $channel->forceFill(['backfill_completed_at' => now()])->save();
        }

        return [$pages, $saved];
    }
}
