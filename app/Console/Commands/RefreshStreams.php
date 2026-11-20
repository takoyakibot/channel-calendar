<?php

namespace App\Console\Commands;

use App\Models\Stream;
use App\Services\YouTubeService;
use App\Support\StreamTagger;
use App\Support\StreamUpserter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Keep stored YouTube data fresh. YouTube's developer policies allow keeping
 * API data only if it is refreshed (or deleted) within 30 days, and streams:fetch
 * only revisits the recent window, so this walks the rest a slice at a time:
 * oldest verification first, videos.list (1 quota unit per 50 ids), and rows
 * YouTube no longer returns (deleted / made private) are removed.
 */
class RefreshStreams extends Command
{
    protected $signature = 'streams:refresh
        {--limit=1000 : At most this many streams per run (1 quota unit per 50)}';

    protected $description = 'Re-check stored YouTube videos not verified recently; update them or delete the ones YouTube no longer serves';

    /** If more than this share of a sizeable batch "disappears", suspect the API and delete nothing. */
    private const MAX_DELETE_RATIO = 0.5;

    private const MIN_BATCH_FOR_RATIO_GUARD = 20;

    public function handle(YouTubeService $youtube, StreamUpserter $upserter): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $staleBefore = now()->subDays((int) config('services.youtube.refresh_after_days', 20));

        $streams = Stream::with('channel')
            ->where('platform', 'youtube')
            ->where(fn ($q) => $q->whereNull('verified_at')->orWhere('verified_at', '<', $staleBefore))
            // NULLs (never verified) first on both MySQL and SQLite.
            ->orderByRaw('verified_at IS NOT NULL')
            ->orderBy('verified_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($streams->isEmpty()) {
            $this->info('Nothing to refresh.');

            return self::SUCCESS;
        }

        $details = [];
        try {
            foreach (array_chunk($streams->pluck('video_id')->all(), 50) as $chunk) {
                foreach ($youtube->getVideoDetails($chunk) as $detail) {
                    $details[$detail['video_id']] = $detail;
                }
            }
        } catch (\Throwable $e) {
            // Nothing is deleted on an API error: a failed call is not "the videos are gone".
            $this->error("YouTube API failed: {$e->getMessage()}");
            Log::error("streams:refresh failed: {$e->getMessage()}");

            return self::FAILURE;
        }

        $tagger = app(StreamTagger::class);
        $updated = 0;
        $gone = [];
        foreach ($streams as $stream) {
            $detail = $details[$stream->video_id] ?? null;
            if ($detail === null) {
                $gone[] = $stream->id;
                continue;
            }
            $before = $stream->title;
            $saved = $upserter->upsert($stream->channel, $detail);
            if ($saved === null) {
                // Still on YouTube but not something we list (e.g. lost its schedule): mark it checked.
                $stream->forceFill(['verified_at' => now()])->save();
                continue;
            }
            if ($saved->title !== $before) {
                $tagger->tag($saved);
            }
            $updated++;
        }

        $deleted = 0;
        if (! empty($gone)) {
            if (count($streams) >= self::MIN_BATCH_FOR_RATIO_GUARD && count($gone) / count($streams) > self::MAX_DELETE_RATIO) {
                $this->warn(count($gone) . ' of ' . count($streams) . ' missing; not deleting (looks like an API problem).');
                Log::warning('streams:refresh skipped deleting ' . count($gone) . ' of ' . count($streams) . ' streams missing from YouTube');
            } else {
                $ids = Stream::whereIn('id', $gone)->pluck('video_id')->all();
                $deleted = Stream::whereIn('id', $gone)->delete();
                Log::info("streams:refresh removed {$deleted} stream(s) no longer on YouTube: " . implode(',', $ids));
            }
        }

        $this->info("{$updated} refreshed, {$deleted} removed.");

        return self::SUCCESS;
    }
}
