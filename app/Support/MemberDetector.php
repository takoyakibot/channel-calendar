<?php

namespace App\Support;

use App\Models\Channel;

/**
 * Guesses which registered members a video is about from its title and
 * description: channel name, short name, abbreviated name, YouTube handle and
 * X handle, case-insensitively. Suggestions only — the submitter confirms.
 */
final class MemberDetector
{
    /** Tokens shorter than this ("ゆ") match everything and are ignored. */
    private const MIN_TOKEN_LENGTH = 2;

    /**
     * @param  iterable<Channel>  $channels
     * @return array<int, int> channel ids, in the order given
     */
    public static function detect(string $text, iterable $channels): array
    {
        $haystack = mb_strtolower(preg_replace('/\s+/u', ' ', $text) ?? $text);

        $ids = [];
        foreach ($channels as $channel) {
            foreach (self::tokens($channel) as $token) {
                if (mb_strlen($token) >= self::MIN_TOKEN_LENGTH && str_contains($haystack, mb_strtolower($token))) {
                    $ids[] = $channel->id;
                    break;
                }
            }
        }

        return $ids;
    }

    /** @return array<int, string> */
    private static function tokens(Channel $channel): array
    {
        $tokens = [
            trim(preg_replace('/\s+/u', ' ', $channel->name) ?? $channel->name),
            $channel->short_name,
            Channel::abbreviate($channel->name),
        ];
        if ($channel->handle) {
            $tokens[] = '@' . ltrim($channel->handle, '@');
        }
        if ($channel->x_handle) {
            $tokens[] = '@' . $channel->x_handle;
            $tokens[] = 'x.com/' . $channel->x_handle;
            $tokens[] = 'twitter.com/' . $channel->x_handle;
        }

        return array_values(array_unique(array_filter($tokens, fn ($t) => is_string($t) && trim($t) !== '')));
    }
}
