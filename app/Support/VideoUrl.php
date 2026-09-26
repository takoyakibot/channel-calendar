<?php

namespace App\Support;

/** Pulls the 11-character video id out of whatever YouTube link someone pastes. */
final class VideoUrl
{
    private const ID = '[A-Za-z0-9_-]{11}';

    public static function videoId(string $input): ?string
    {
        $s = trim($input);
        if ($s === '') {
            return null;
        }
        if (preg_match('/^' . self::ID . '$/', $s)) {
            return $s;
        }
        if (! preg_match('~^https?://~i', $s)) {
            $s = 'https://' . $s;
        }

        $parts = parse_url($s);
        if (! $parts || empty($parts['host'])) {
            return null;
        }
        $host = strtolower(preg_replace('/^(?:www|m|music)\./i', '', $parts['host']) ?? $parts['host']);
        $path = $parts['path'] ?? '';

        if ($host === 'youtu.be') {
            return self::leadingId(ltrim($path, '/'));
        }
        if (in_array($host, ['youtube.com', 'youtube-nocookie.com'], true)) {
            if (preg_match('~^/(?:shorts|live|embed|v)/(' . self::ID . ')~', $path, $m)) {
                return $m[1];
            }
            if (rtrim($path, '/') === '/watch') {
                parse_str($parts['query'] ?? '', $query);

                return self::leadingId((string) ($query['v'] ?? ''));
            }
        }

        return null;
    }

    private static function leadingId(string $s): ?string
    {
        return preg_match('/^(' . self::ID . ')(?![A-Za-z0-9_-])/', $s, $m) ? $m[1] : null;
    }
}
