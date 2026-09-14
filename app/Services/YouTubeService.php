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

    /**
     * Newest video ids from the channel's uploads playlist, most recent first.
     * Scheduled and live broadcasts appear here too, and the call costs 1 quota
     * unit versus 100 for search.list.
     *
     * @return array<int, string>
     */
    public function listRecentUploadIds(string $channelId, int $max = 50): array
    {
        // Every channel's uploads playlist id is its channel id with "UC" swapped for "UU".
        $playlistId = 'UU' . substr($channelId, 2);

        $response = $this->youtube->playlistItems->listPlaylistItems('snippet', [
            'playlistId' => $playlistId,
            'maxResults' => $max,
        ]);

        return array_values(array_filter(array_map(
            fn ($item) => $item->getSnippet()?->getResourceId()?->getVideoId(),
            $response->getItems() ?? []
        )));
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
                'thumbnail_url' => $this->extractThumbnailUrl($video->getSnippet()),
                'scheduled_at' => $details?->getScheduledStartTime(),
                'actual_start_at' => $actualStart,
                'actual_end_at' => $actualEnd,
                'status' => $status,
            ];
        }, $response->getItems());
    }
}
