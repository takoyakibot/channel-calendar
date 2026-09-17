<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    protected $fillable = [
        'user_id',
        'channel_id',
        'title',
        'source_url',
        'scheduled_at',
        'is_all_day',
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
