<?php

namespace App\Support;

/** "amawauru", "@amawauru" or "https://www.twitch.tv/amawauru?…" → "amawauru". */
final class TwitchLogin
{
    public static function normalize(string $input): string
    {
        $s = trim($input);
        if (preg_match('~^(?:https?://)?(?:www\.|m\.)?twitch\.tv/([A-Za-z0-9_]+)~i', $s, $m)) {
            $s = $m[1];
        }
        $s = ltrim($s, '@');
        if (! preg_match('/^[A-Za-z0-9_]{3,25}$/', $s)) {
            throw new \InvalidArgumentException('Twitch のログイン名（twitch.tv/ の後ろ）か、チャンネル URL を入力してください。');
        }

        return strtolower($s);
    }

    public static function url(string $login): string
    {
        return "https://www.twitch.tv/{$login}";
    }
}
