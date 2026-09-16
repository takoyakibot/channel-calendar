<?php

namespace App\Support;

use InvalidArgumentException;

/** X (Twitter) account handles: normalisation of admin input and the announcement search URL. */
class XHandle
{
    private const HANDLE_PATTERN = '/^[A-Za-z0-9_]{1,15}$/';
    private const PROFILE_URL = '~^(?:https?://)?(?:www\.|mobile\.)?(?:x\.com|twitter\.com)/([^/?#]+)(/[^?#]*)?~i';

    /** Accepts "name", "@name", or a profile URL; returns the bare handle. */
    public static function normalize(string $raw): string
    {
        $input = trim($raw);
        if ($input === '') {
            throw new InvalidArgumentException('X のアカウントを入力してください。');
        }

        if (preg_match(self::PROFILE_URL, $input, $m)) {
            $rest = $m[2] ?? '';
            // Only a profile URL (optionally with a tab like /with_replies) — not a status or search URL.
            if ($rest !== '' && ! preg_match('~^/(with_replies|media|likes|highlights|articles)/?$~i', $rest)) {
                throw new InvalidArgumentException('X のプロフィール URL（https://x.com/ユーザー名）を指定してください。');
            }
            $input = $m[1];
        } elseif (preg_match('~^[a-z][a-z0-9+.-]*://~i', $input) || preg_match('~^[a-z0-9.-]+\.[a-z]{2,}/~i', $input)) {
            throw new InvalidArgumentException('X のプロフィール URL ではありません。');
        }

        $handle = ltrim($input, '@');
        if (! preg_match(self::HANDLE_PATTERN, $handle)) {
            throw new InvalidArgumentException('X のユーザー名は15文字以内の英数字とアンダースコアで指定してください。');
        }

        return $handle;
    }

    /** X search for the account's own posts containing any of the keywords, newest first. */
    public static function searchUrl(string $handle, array $keywords): string
    {
        $terms = implode(' OR ', array_map('trim', array_filter($keywords, fn ($k) => trim((string) $k) !== '')));
        $query = 'from:' . $handle . ($terms !== '' ? ' (' . $terms . ')' : '');

        return 'https://x.com/search?q=' . rawurlencode($query) . '&f=live';
    }
}
