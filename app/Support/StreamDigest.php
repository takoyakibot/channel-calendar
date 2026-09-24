<?php

namespace App\Support;

use App\Models\Stream;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The once-a-day X post: what is live right now and what is coming up, as many
 * entries as fit in one post, the rest as "他 N 件" pointing at the site.
 */
class StreamDigest
{
    /** Weighted length allowed for one entry line (~23 Japanese characters). */
    private const LINE_MAX = 46;

    private const WEEKDAYS = ['日', '月', '火', '水', '木', '金', '土'];

    /**
     * @param  Collection<int, Stream>  $live
     * @param  Collection<int, Stream>  $upcoming  chronological
     */
    public static function text(Collection $live, Collection $upcoming, Carbon $now): ?string
    {
        if ($live->isEmpty() && $upcoming->isEmpty()) {
            return null;
        }

        $today = $now->copy()->setTimezone('Asia/Tokyo');
        $header = '📅 ' . $today->format('n/j') . '(' . self::WEEKDAYS[$today->dayOfWeek] . ') の配信予定';
        $site = url('/');

        $entries = [];
        foreach ($live as $stream) {
            $entries[] = self::line('🔴 配信中 ', $stream);
        }
        foreach ($upcoming as $stream) {
            $at = $stream->scheduled_at->copy()->setTimezone('Asia/Tokyo');
            $prefix = ($at->isSameDay($today) ? '' : $at->format('n/j') . ' ') . $at->format('H:i') . ' ';
            $entries[] = self::line($prefix, $stream);
        }

        // Add lines while the whole post (with the footer it would need) still fits.
        $total = count($entries);
        $lines = [];
        foreach ($entries as $i => $line) {
            $footer = self::footer($total - ($i + 1), $site);
            $candidate = implode("\n", [$header, ...$lines, $line, $footer]);
            if (StreamAnnouncement::weightedLength($candidate) > StreamAnnouncement::MAX_WEIGHTED_LENGTH) {
                break;
            }
            $lines[] = $line;
        }

        return implode("\n", [$header, ...$lines, self::footer($total - count($lines), $site)]);
    }

    private static function footer(int $remaining, string $site): string
    {
        return $remaining > 0 ? "他 {$remaining} 件 → {$site}" : $site;
    }

    private static function line(string $prefix, Stream $stream): string
    {
        $base = $prefix . $stream->channel->shortName() . ' / ';
        $title = trim(preg_replace('/\s+/u', ' ', $stream->title) ?? $stream->title);

        $budget = max(6, self::LINE_MAX - StreamAnnouncement::weightedLength($base));
        if (StreamAnnouncement::weightedLength($title) > $budget) {
            $title = StreamAnnouncement::shorten($title, $budget);
        }

        return $base . $title;
    }
}
