<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Models\Stream;
use App\Services\YouTubeService;
use Illuminate\Console\Command;

class FetchStreams extends Command
{
    protected $signature = 'streams:fetch';
    protected $description = 'Fetch upcoming and live streams from YouTube for all active channels';

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

        $videoIds = array_column($allResults, 'video_id');
        $details = $youtube->getVideoDetails($videoIds);

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
        }
    }

    private function markOldStreamsCompleted(): void
    {
        Stream::where('status', 'upcoming')
            ->where('scheduled_at', '<', now()->subHours(24))
            ->update(['status' => 'completed']);
    }
}
