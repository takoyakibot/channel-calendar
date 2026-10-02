<?php

namespace App\Support;

use App\Models\Stream;
use Illuminate\Database\Eloquent\Builder;
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
     * Digest of what streams of active channels are live or reserved right now.
     * Members-only ones are listed too, marked 🔒 as on the calendar.
     *
     * @param  array<int, int>|null  $channelIds  null = every channel
     */
    public static function current(?array $channelIds = null, ?string $site = null, ?string $label = null): ?string
    {
        $streams = fn () => Stream::with('channel')
            ->whereHas('channel', fn (Builder $q) => $q->where('is_active', true))
            ->when($channelIds !== null, fn (Builder $q) => $q->whereIn('channel_id', $channelIds));

        $live = $streams()->where('status', 'live')->orderBy('scheduled_at')->get();
        $upcoming = $streams()->where('status', 'upcoming')->where('scheduled_at', '>', now())->orderBy('scheduled_at')->get();

        return self::text($live, $upcoming, now(), $site, $label);
    }

    /**
     * @param  Collection<int, Stream>  $live
     * @param  Collection<int, Stream>  $upcoming  chronological
     */
    public static function text(Collection $live, Collection $upcoming, Carbon $now, ?string $site = null, ?string $label = null): ?string
    {
        if ($live->isEmpty() && $upcoming->isEmpty()) {
            return null;
        }

        $today = $now->copy()->setTimezone('Asia/Tokyo');
        $header = '📅 ' . (filled($label) ? "{$label} " : '') . $today->format('n/j') . '(' . self::WEEKDAYS[$today->dayOfWeek] . ') の配信予定';
        $site ??= url('/');

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
        $title = ($stream->is_members_only ? '🔒 ' : '') . trim(preg_replace('/\s+/u', ' ', $stream->title) ?? $stream->title);

        $budget = max(6, self::LINE_MAX - StreamAnnouncement::weightedLength($base));
        if (StreamAnnouncement::weightedLength($title) > $budget) {
            $title = StreamAnnouncement::shorten($title, $budget);
        }

        return $base . $title;
    }
}
