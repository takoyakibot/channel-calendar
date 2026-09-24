<?php

namespace App\Support;

use App\Models\Stream;

/** Builds the X post announcing a newly reserved stream. */
class StreamAnnouncement
{
    /** X counts weighted characters (CJK and emoji = 2, URLs = 23) up to this. */
    public const MAX_WEIGHTED_LENGTH = 280;

    private const URL_WEIGHT = 23;

    private const WEEKDAYS = ['日', '月', '火', '水', '木', '金', '土'];

    public static function text(Stream $stream): string
    {
        $stream->loadMissing('channel.groups');

        $group = $stream->channel->groups->sortBy('id')->first();
        $page = $group ? url('/' . $group->path) : url('/');

        $when = $stream->scheduled_at->copy()->setTimezone('Asia/Tokyo');
        $time = $when->format('n/j') . '(' . self::WEEKDAYS[$when->dayOfWeek] . ') ' . $when->format('H:i');

        $video = "https://www.youtube.com/watch?v={$stream->video_id}";
        $channel = trim($stream->channel->name);

        $build = fn (string $title): string => implode("\n", [
            '📢 新しい配信予定',
            "🎬 {$title}",
            "📺 {$channel}",
            "🕐 {$time}〜",
            "🔗 {$video}",
            $page,
        ]);

        // Give the title whatever room the fixed parts leave, shortening it with "…".
        $title = trim(preg_replace('/\s+/u', ' ', $stream->title) ?? '');
        $budget = self::MAX_WEIGHTED_LENGTH - self::weightedLength($build(''));
        if (self::weightedLength($title) > $budget) {
            $title = self::shorten($title, $budget);
        }

        return $build($title);
    }

    private static function shorten(string $text, int $budget): string
    {
        $chars = mb_str_split($text);
        $ellipsisWeight = self::weightedLength('…');
        $out = '';
        $used = 0;
        foreach ($chars as $ch) {
            $w = self::weightedLength($ch);
            if ($used + $w + $ellipsisWeight > $budget) {
                break;
            }
            $out .= $ch;
            $used += $w;
        }

        return rtrim($out) . '…';
    }

    /**
     * Approximation of twitter-text's weighted length: URLs weigh 23, characters in
     * the Latin/Cyrillic/general-punctuation ranges weigh 1, everything else 2.
     */
    public static function weightedLength(string $text): int
    {
        $text = preg_replace('~https?://\S+~u', str_repeat('x', self::URL_WEIGHT), $text) ?? $text;

        $length = 0;
        foreach (mb_str_split($text) as $ch) {
            $cp = mb_ord($ch);
            $light = $cp <= 4351
                || ($cp >= 8192 && $cp <= 8205)
                || ($cp >= 8208 && $cp <= 8223)
                || ($cp >= 8242 && $cp <= 8247);
            $length += $light ? 1 : 2;
        }

        return $length;
    }
}
