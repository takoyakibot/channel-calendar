<?php

namespace App\Support;

/** Canonical form of a tag / bracket term so "ＭＩＮＥＣＲＡＦＴ", "#minecraft" and "Minecraft" compare equal. */
final class TermNormalizer
{
    public static function normalize(string $s): string
    {
        $s = trim($s);
        // NFKC: full-width Latin → ASCII, half-width kana → full-width, compatibility forms folded.
        $s = class_exists(\Normalizer::class)
            ? (\Normalizer::normalize($s, \Normalizer::FORM_KC) ?: $s)
            : mb_convert_kana($s, 'asKV');
        $s = mb_strtolower($s);
        $s = preg_replace('/^[#＃]+/u', '', $s) ?? $s;

        return trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
    }

    /** normalize() with every space removed — the haystack non-ASCII terms are matched against. */
    public static function compact(string $s): string
    {
        return preg_replace('/\s+/u', '', self::normalize($s)) ?? '';
    }

    /**
     * The stored / compared form of a term: ASCII keeps its spaces (it is matched
     * on word boundaries), anything else drops them so "卯乃花 露芭" and
     * "卯乃花露芭" are one term.
     */
    public static function key(string $s): string
    {
        $n = self::normalize($s);

        return self::isAscii($n) ? $n : (preg_replace('/\s+/u', '', $n) ?? $n);
    }

    /** ASCII-only terms are matched on word boundaries ("repo" must not hit "repository"). */
    public static function isAscii(string $s): bool
    {
        return preg_match('/^[\x20-\x7e]+$/', $s) === 1;
    }
}
