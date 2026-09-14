<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Models\Setting;
use App\Models\Stream;
use App\Services\YouTubeService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class FetchStreams extends Command
{
    public const LAST_FETCHED_AT_KEY = 'streams_last_fetched_at';

    protected $signature = 'streams:fetch';
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

    public function handle(YouTubeService $youtube): int
    {
        $channels = Channel::active()->get();

        if ($channels->isEmpty()) {
            $this->info('No active channels found.');
            Setting::set(self::LAST_FETCHED_AT_KEY, now()->toIso8601String());

            return self::SUCCESS;
        }

        $backfillSince = now()->subDays((int) config('services.youtube.backfill_days', 14));

        foreach ($channels as $channel) {
            $this->info("Fetching streams for: {$channel->name}");

            try {
                $count = $this->fetchForChannel($youtube, $channel, $backfillSince);
                $this->line("  {$count} stream(s) upserted");
            } catch (\Throwable $e) {
                $this->error("Failed for {$channel->name}: {$e->getMessage()}");
                Log::error("streams:fetch failed for channel {$channel->channel_id}: {$e->getMessage()}");
                $this->failedChannelIds[] = $channel->id;
            }
        }

        $this->markOldStreamsCompleted();
        Setting::set(self::LAST_FETCHED_AT_KEY, now()->toIso8601String());

        $this->info('Done.');

        return self::SUCCESS;
    }

    private function fetchForChannel(YouTubeService $youtube, Channel $channel, Carbon $backfillSince): int
    {
        $videoIds = array_values(array_unique($youtube->listRecentUploadIds($channel->channel_id)));
        if (empty($videoIds)) {
            return 0;
        }

        $details = [];
        foreach (array_chunk($videoIds, 50) as $chunk) {
            $details = array_merge($details, $youtube->getVideoDetails($chunk));
        }

        $count = 0;
        foreach ($details as $detail) {
            // Plain uploads have no liveStreamingDetails — they are not streams.
            if ($detail['scheduled_at'] === null) {
                continue;
            }

            // Keep every upcoming/live broadcast, but only backfill ended ones
            // from the recent window so the board shows history without
            // importing the whole archive.
            if ($detail['status'] === 'completed' && Carbon::parse($detail['scheduled_at'])->lt($backfillSince)) {
                continue;
            }

            Stream::updateOrCreate(
                ['video_id' => $detail['video_id']],
                [
                    'channel_id' => $channel->id,
                    'title' => $detail['title'],
                    'thumbnail_url' => $detail['thumbnail_url'],
                    'scheduled_at' => $detail['scheduled_at'],
                    'actual_start_at' => $detail['actual_start_at'],
                    'actual_end_at' => $detail['actual_end_at'],
                    'status' => $detail['status'],
                ]
            );

            $this->fetchedVideoIds[] = $detail['video_id'];
            $count++;
        }

        return $count;
    }

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
}
