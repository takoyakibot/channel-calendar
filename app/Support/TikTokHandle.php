<?php

namespace App\Support;

/** "petit.petit", "@petit.petit" or "https://www.tiktok.com/@petit.petit/live?…" → "petit.petit". */
final class TikTokHandle
{
    public static function normalize(string $input): string
    {
        $s = trim($input);
        if (preg_match('~^(?:https?://)?(?:www\.|m\.)?tiktok\.com/@([A-Za-z0-9_.]+)~i', $s, $m)) {
            $s = $m[1];
        }
        $s = ltrim($s, '@');
        if (! preg_match('/^[A-Za-z0-9_.]{2,24}$/', $s)) {
            throw new \InvalidArgumentException('TikTok のユーザー名（@ の後ろ）か、プロフィール URL を入力してください。');
        }

        return strtolower($s);
    }

    public static function liveUrl(string $handle): string
    {
        return "https://www.tiktok.com/@{$handle}/live";
    }
}
