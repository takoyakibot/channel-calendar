<?php

namespace App\Support;

use App\Models\Channel;
use App\Models\Setting;

/**
 * Keywords ORed into the "find announcements on X" search: a global list
 * (admin setting, falling back to X_SEARCH_KEYWORDS) plus per-channel extras.
 */
class XSearchKeywords
{
    public const SETTING_KEY = 'x_search_keywords';

    /** Split free-form input on commas and any whitespace (incl. full-width), trim, dedupe. */
    public static function parse(?string $raw): array
    {
        if ($raw === null) {
            return [];
        }

        $parts = preg_split('/[,\s、，\x{3000}]+/u', $raw) ?: [];
        $terms = array_values(array_unique(array_filter(array_map('trim', $parts), fn ($t) => $t !== '')));

        return $terms;
    }

    /** Canonical stored form: comma-joined, or null when empty. */
    public static function normalize(?string $raw): ?string
    {
        $terms = self::parse($raw);

        return $terms === [] ? null : implode(',', $terms);
    }

    public static function global(): array
    {
        $stored = self::parse(Setting::get(self::SETTING_KEY));
        if ($stored !== []) {
            return $stored;
        }

        return array_values(array_filter(array_map('trim', (array) config('services.x.search_keywords', [])), fn ($t) => $t !== ''));
    }

    public static function forChannel(Channel $channel): array
    {
        return array_values(array_unique(array_merge(self::global(), self::parse($channel->x_search_keywords))));
    }
}
