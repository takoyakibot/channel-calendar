<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Models\Stream;
use App\Services\YouTubeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchStreams extends Command
{
    protected $signature = 'streams:fetch';
    protected $description = 'Fetch upcoming and live streams from YouTube for all active channels';

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
            return self::SUCCESS;
        }

        foreach ($channels as $channel) {
            $this->info("Fetching streams for: {$channel->name}");

            try {
                $this->fetchForChannel($youtube, $channel);
            } catch (\Throwable $e) {
                $this->error("Failed for {$channel->name}: {$e->getMessage()}");
                Log::error("streams:fetch failed for channel {$channel->channel_id}: {$e->getMessage()}");
                $this->failedChannelIds[] = $channel->id;
            }
        }

        $this->markOldStreamsCompleted();

        $this->info('Done.');
        return self::SUCCESS;
    }

    private function fetchForChannel(YouTubeService $youtube, Channel $channel): void
    {
        $upcoming = $youtube->searchStreams($channel->channel_id, 'upcoming');
        $live = $youtube->searchStreams($channel->channel_id, 'live');

        $allResults = array_merge($upcoming, $live);
        if (empty($allResults)) {
            return;
        }

        $videoIds = array_values(array_unique(array_column($allResults, 'video_id')));

        $details = [];
        foreach (array_chunk($videoIds, 50) as $chunk) {
            $details = array_merge($details, $youtube->getVideoDetails($chunk));
        }

        $thumbnailMap = [];
        foreach ($allResults as $result) {
            $thumbnailMap[$result['video_id']] = $result['thumbnail_url'];
        }

        foreach ($details as $detail) {
            if ($detail['scheduled_at'] === null) {
                continue;
            }

            Stream::updateOrCreate(
                ['video_id' => $detail['video_id']],
                [
                    'channel_id' => $channel->id,
                    'title' => $detail['title'],
                    'thumbnail_url' => $thumbnailMap[$detail['video_id']] ?? null,
                    'scheduled_at' => $detail['scheduled_at'],
                    'actual_start_at' => $detail['actual_start_at'],
                    'actual_end_at' => $detail['actual_end_at'],
                    'status' => $detail['status'],
                ]
            );

            $this->fetchedVideoIds[] = $detail['video_id'];
        }
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
