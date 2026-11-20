<?php

namespace App\Support;

use App\Models\Channel;
use App\Models\Stream;
use Illuminate\Support\Carbon;

/**
 * Writes one videos.list detail (YouTubeService::getVideoDetails) to the streams
 * table. Shared by streams:fetch, streams:backfill and streams:refresh so the
 * stream / upload / short rules live in one place.
 */
final class StreamUpserter
{
    /**
     * @param  ?Carbon  $windowStart  ended videos older than this are skipped (null: keep everything)
     * @param  ?bool  $membersOnly  true/false from playlist evidence; null leaves the stored flag alone
     * @return ?Stream the saved row, or null when the detail was skipped
     */
    public function upsert(Channel $channel, array $detail, ?Carbon $windowStart = null, ?bool $membersOnly = null): ?Stream
    {
        if ($detail['is_broadcast']) {
            // Broadcasts without a schedule (no scheduledStartTime, no actualStartTime) are unusable.
            if ($detail['scheduled_at'] === null) {
                return null;
            }
            $type = 'stream';
            $scheduledAt = $detail['scheduled_at'];
        } else {
            $scheduledAt = $detail['published_at'] ?? null;
            if ($scheduledAt === null) {
                return null;
            }
            $type = (($detail['duration_seconds'] ?? null) !== null && $detail['duration_seconds'] <= 180)
                ? 'short'
                : 'upload';
            $detail['status'] = 'completed';
        }

        if ($windowStart !== null && $detail['status'] === 'completed' && Carbon::parse($scheduledAt)->lt($windowStart)) {
            return null;
        }

        $attributes = [
            'channel_id' => $channel->id,
            'title' => $detail['title'],
            'thumbnail_url' => $detail['thumbnail_url'],
            'scheduled_at' => $scheduledAt,
            'actual_start_at' => $detail['actual_start_at'],
            'actual_end_at' => $detail['actual_end_at'],
            'status' => $detail['status'],
            'type' => $type,
            // YouTube reports P0D while a broadcast is live; keep null until it is real.
            'duration_seconds' => ($detail['duration_seconds'] ?? null) ?: null,
            'verified_at' => now(),
        ];
        if ($membersOnly !== null) {
            $attributes['is_members_only'] = $membersOnly;
        }

        return Stream::updateOrCreate(['video_id' => $detail['video_id']], $attributes);
    }
}
