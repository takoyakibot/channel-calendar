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
        return $this->listPlaylistVideoIds('UU' . substr($channelId, 2), $max);
    }

    /**
     * Members-only videos live in the auto-generated "UUMO…" playlist, which the
     * uploads playlist omits. It is readable with an API key; channels without a
     * membership programme simply have no such playlist (404), which means "none".
     *
     * @return array<int, string>
     */
    public function listMembersOnlyUploadIds(string $channelId, int $max = 50): array
    {
        try {
            return $this->listPlaylistVideoIds('UUMO' . substr($channelId, 2), $max);
        } catch (\Google\Service\Exception $e) {
            if ($e->getCode() === 404) {
                return [];
            }
            throw $e;
        }
    }

    /** @return array<int, string> */
    private function listPlaylistVideoIds(string $playlistId, int $max): array
    {
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
            // Only broadcasts (live streams, premieres) carry liveStreamingDetails;
            // plain uploads and shorts have none.
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
                'is_broadcast' => $details !== null,
                // A stream started on the spot has no reservation time; slot it at
                // the moment it actually went live.
                'scheduled_at' => $details?->getScheduledStartTime() ?? $actualStart,
                'actual_start_at' => $actualStart,
                'actual_end_at' => $actualEnd,
                'status' => $status,
            ];
        }, $response->getItems());
    }
}
