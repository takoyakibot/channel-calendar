<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Support\TikTokHandle;
use Illuminate\Support\Carbon;

class ManualSchedule extends Model
{
    use HasFactory;

    /**
     * How long around a timed manual schedule we expect the real stream to
     * show up: streamers go live a little early and, more often, run late.
     */
    public const WATCH_BEFORE_MINUTES = 30;
    public const WATCH_AFTER_HOURS = 3;

    /** Platforms we can't fetch, so the entry stands on its own instead of waiting for a real stream. */
    public const PLATFORMS = ['tiktok'];

    protected $fillable = [
        'user_id',
        'channel_id',
        'title',
        'source_url',
        'scheduled_at',
        'is_all_day',
        'platform',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'is_all_day' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /** Entries a fetched YouTube / Twitch stream replaces: everything but TikTok LIVE and the like. */
    public function scopeReplaceable(Builder $query): Builder
    {
        return $query->whereNull('platform');
    }

    /** Where the entry links to: its source, else the member's TikTok live page for a TikTok entry. */
    public function linkUrl(): ?string
    {
        if ($this->source_url) {
            return $this->source_url;
        }

        return $this->platform === 'tiktok' && $this->channel?->tiktok_handle
            ? TikTokHandle::liveUrl($this->channel->tiktok_handle)
            : null;
    }

    /**
     * Schedules whose real stream could appear right now: timed entries from
     * WATCH_BEFORE_MINUTES before to WATCH_AFTER_HOURS after their time, and
     * all-day entries for the whole (local) day they stand for.
     */
    public function scopeInWatchWindow(Builder $query, ?Carbon $now = null): Builder
    {
        $now = $now ?? now();

        return $query->where(function (Builder $q) use ($now) {
            $q->where(fn (Builder $timed) => $timed
                ->where('is_all_day', false)
                ->whereBetween('scheduled_at', [
                    $now->copy()->subHours(self::WATCH_AFTER_HOURS),
                    $now->copy()->addMinutes(self::WATCH_BEFORE_MINUTES),
                ]))
              ->orWhere(fn (Builder $allDay) => $allDay
                ->where('is_all_day', true)
                ->where('scheduled_at', '>', $now->copy()->subDay())
                ->where('scheduled_at', '<=', $now));
        });
    }
}
