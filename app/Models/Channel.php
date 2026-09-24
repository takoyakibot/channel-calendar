<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Channel extends Model
{
    use HasFactory;

    protected $fillable = [
        'channel_id',
        'handle',
        'x_handle',
        'x_search_keywords',
        'name',
        'short_name',
        'thumbnail_url',
        'color',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function streams(): HasMany
    {
        return $this->hasMany(Stream::class);
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'channel_group');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Name used where space is tight (the daily X digest): the admin-set one, or an abbreviation. */
    public function shortName(): string
    {
        return filled($this->short_name) ? $this->short_name : static::abbreviate($this->name);
    }

    /**
     * Best-effort abbreviation of a YouTube channel name: drop emoji, keep the part
     * next to a "Ch." / "Channel" marker, cut at separators, cap at 8 characters.
     * "Nakira Ch. 奈煌🐼" → "奈煌", "ゆゆ Channel" → "ゆゆ", "ノル / Nolu" → "ノル".
     */
    public static function abbreviate(string $name): string
    {
        $s = preg_replace('/[\p{So}\p{Sk}\p{Cs}\x{FE0F}\x{200D}\x{20E3}]/u', '', $name) ?? $name;
        $s = trim(preg_replace('/\s+/u', ' ', $s) ?? $s);

        if (preg_match('/^(.*?)\s*(?:\bch\b\.?|\bchannel\b|チャンネル)\s*(.*)$/iu', $s, $m)) {
            $head = trim($m[1]);
            $tail = trim($m[2]);
            $s = $tail !== '' ? $tail : $head;
        }

        $parts = preg_split('/\s*(?:\/|／|\||｜|【|「|\s[-–—]\s)\s*/u', $s);
        $s = trim($parts[0] ?? $s);
        if ($s === '') {
            $s = trim($name);
        }

        return mb_substr($s, 0, 8);
    }
}
