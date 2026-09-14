<?php

namespace App\Services;

use Google\Service\YouTube;

class YouTubeService
{
    public function __construct(private YouTube $youtube)
    {
    }

    public function getChannelInfo(string $channelId): array
    {
        $response = $this->youtube->channels->listChannels('snippet', [
            'id' => $channelId,
        ]);

        $items = $response->getItems();
        if (empty($items)) {
            throw new \RuntimeException("Channel not found: {$channelId}");
        }

        $channel = $items[0];
        $snippet = $channel->getSnippet();

        return [
            'name' => $snippet->getTitle(),
            'thumbnail_url' => $this->extractThumbnailUrl($snippet),
        ];
    }

    /**
     * Look a channel up by resolved input type ("id" => UC… id, "handle" => "@name")
     * and return its canonical id plus display data.
     *
     * @return array{channel_id: string, handle: ?string, name: string, thumbnail_url: ?string}
     */
    public function findChannel(string $type, string $value): array
    {
        $params = $type === 'id' ? ['id' => $value] : ['forHandle' => $value];
        $response = $this->youtube->channels->listChannels('snippet', $params);

        $items = $response->getItems();
        if (empty($items)) {
            throw new \RuntimeException("Channel not found: {$value}");
        }

        $channel = $items[0];
        $snippet = $channel->getSnippet();
        $customUrl = $snippet->getCustomUrl();

        return [
            'channel_id' => $channel->getId(),
            'handle' => $customUrl ? '@' . ltrim($customUrl, '@') : null,
            'name' => $snippet->getTitle(),
            'thumbnail_url' => $this->extractThumbnailUrl($snippet),
        ];
    }

    public function searchStreams(string $channelId, string $eventType): array
    {
        $response = $this->youtube->search->listSearch('snippet', [
            'channelId' => $channelId,
            'type' => 'video',
            'eventType' => $eventType,
            'order' => 'date',
            'maxResults' => 50,
        ]);

        return array_map(function ($item) {
            $snippet = $item->getSnippet();
            return [
                'video_id' => $item->getId()->getVideoId(),
                'title' => $snippet->getTitle(),
                'thumbnail_url' => $this->extractThumbnailUrl($snippet),
            ];
        }, $response->getItems());
    }

    private function extractThumbnailUrl($snippet): ?string
    {
        return $snippet->getThumbnails()?->getDefault()?->getUrl();
    }

    public function getVideoDetails(array $videoIds): array
    {
        if (empty($videoIds)) {
            return [];
        }

        $response = $this->youtube->videos->listVideos('snippet,liveStreamingDetails', [
            'id' => implode(',', $videoIds),
        ]);

        return array_map(function ($video) {
            $details = $video->getLiveStreamingDetails();
            $actualStart = $details?->getActualStartTime();
            $actualEnd = $details?->getActualEndTime();

            if ($actualEnd) {
                $status = 'completed';
            } elseif ($actualStart) {
                $status = 'live';
            } else {
                $status = 'upcoming';
            }

            return [
                'video_id' => $video->getId(),
                'title' => $video->getSnippet()->getTitle(),
                'scheduled_at' => $details?->getScheduledStartTime(),
                'actual_start_at' => $actualStart,
                'actual_end_at' => $actualEnd,
                'status' => $status,
            ];
        }, $response->getItems());
    }
}
