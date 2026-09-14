<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Normalises what an admin pastes into "YouTube channel" — a bare handle,
 * a handle or channel URL, or a raw UC… id — into something the API can look up.
 */
class ChannelInput
{
    private const ID_PATTERN = '/^UC[A-Za-z0-9_-]{22}$/';
    private const HANDLE_PATTERN = '/^[A-Za-z0-9._-]{3,30}$/';
    private const YOUTUBE_URL = '~^(?:https?://)?(?:www\.|m\.)?youtube\.com/(.*)$~i';

    /** @return array{type: 'id'|'handle', value: string} */
    public static function parse(string $raw): array
    {
        $input = trim($raw);
        if ($input === '') {
            throw new InvalidArgumentException('チャンネルを入力してください。');
        }

        if (preg_match(self::YOUTUBE_URL, $input, $m)) {
            return self::parseYoutubePath($m[1]);
        }

        if (preg_match('~^[a-z][a-z0-9+.-]*://~i', $input) || preg_match('~^[a-z0-9.-]+\.[a-z]{2,}/~i', $input)) {
            throw new InvalidArgumentException('YouTube のチャンネルURLではありません。');
        }

        return self::parseBare($input);
    }

    private static function parseYoutubePath(string $path): array
    {
        $path = preg_replace('/[?#].*$/', '', $path);
        $segments = array_values(array_filter(explode('/', $path), fn ($s) => $s !== ''));

        if (count($segments) >= 2 && strtolower($segments[0]) === 'channel' && preg_match(self::ID_PATTERN, $segments[1])) {
            return ['type' => 'id', 'value' => $segments[1]];
        }

        if (count($segments) >= 1 && str_starts_with($segments[0], '@')) {
            return self::handle(substr($segments[0], 1));
        }

        throw new InvalidArgumentException('チャンネルのURL（/@handle または /channel/UC…）を指定してください。');
    }

    private static function parseBare(string $input): array
    {
        if (preg_match(self::ID_PATTERN, $input)) {
            return ['type' => 'id', 'value' => $input];
        }

        return self::handle(ltrim($input, '@'));
    }

    private static function handle(string $name): array
    {
        if (! preg_match(self::HANDLE_PATTERN, $name)) {
            throw new InvalidArgumentException('ハンドルは3〜30文字の英数字・ピリオド・アンダースコア・ハイフンで指定してください。');
        }

        return ['type' => 'handle', 'value' => '@' . $name];
    }
}
