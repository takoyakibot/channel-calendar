<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A video on someone else's channel that features one or more members: a clip
 * (切り抜き) or a guest appearance (コラボ出演). Registered by anyone from a URL.
 */
class VideoPost extends Model
{
    public const KINDS = ['clip', 'guest'];

    public const KIND_LABELS = ['clip' => '切り抜き', 'guest' => '出演'];

    protected $fillable = [
        'video_id',
        'kind',
        'title',
        'thumbnail_url',
        'source_channel_id',
        'source_channel_name',
        'published_at',
        'duration_seconds',
        'submitted_by_user_id',
        'submitter_hash',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(fn (self $post) => $post->channels()->detach());
    }

    public function channels(): BelongsToMany
    {
        return $this->belongsToMany(Channel::class, 'channel_video_post');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function url(): string
    {
        return "https://www.youtube.com/watch?v={$this->video_id}";
    }

    /** FullCalendar-shaped event, same keys the streams API uses so the page can treat it as a card. */
    public function toEvent(): array
    {
        $members = $this->channels->sortBy('id')->values();
        $first = $members->first();

        return [
            'id' => 'vp_' . $this->id,
            'title' => $this->title,
            'start' => $this->published_at->toIso8601String(),
            'end' => $this->duration_seconds ? $this->published_at->copy()->addSeconds($this->duration_seconds)->toIso8601String() : null,
            'url' => $this->url(),
            'color' => $first?->color,
            'extendedProps' => [
                'type' => $this->kind,
                'status' => 'posted',
                'channel_id' => $first?->id,
                'channel_name' => $first?->name,
                'channel_thumbnail_url' => $first?->thumbnail_url,
                'thumbnail_url' => $this->thumbnail_url,
                'source_channel_name' => $this->source_channel_name,
                'members' => $members->map(fn (Channel $c) => ['id' => $c->id, 'name' => $c->name])->all(),
                'video_post_id' => $this->id,
                'created_at' => $this->created_at?->toIso8601String(),
            ],
        ];
    }
}
