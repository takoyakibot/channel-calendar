<?php

namespace App\Models;

use App\Support\TermNormalizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A game or a category (雑談, 歌枠 …) streams are tagged with. */
class Tag extends Model
{
    public const KINDS = ['game', 'category'];

    public const KIND_LABELS = ['game' => 'ゲーム', 'category' => 'カテゴリ'];

    protected $fillable = ['name', 'kind', 'created_by_user_id'];

    protected static function booted(): void
    {
        static::deleting(function (self $tag) {
            $tag->streams()->detach();
            $tag->aliases()->delete();
        });
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(TagAlias::class);
    }

    public function streams(): BelongsToMany
    {
        return $this->belongsToMany(Stream::class, 'stream_tag')->withPivot('source');
    }

    /** Create a tag whose own name is immediately matchable. */
    public static function createWithAlias(string $name, string $kind, ?int $userId = null): self
    {
        $tag = self::create(['name' => trim($name), 'kind' => $kind, 'created_by_user_id' => $userId]);
        $tag->aliases()->create(['alias' => TermNormalizer::normalize($name), 'created_by_user_id' => $userId]);

        return $tag;
    }

    /** @return array{id: int, name: string, kind: string} */
    public function toArrayForApi(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'kind' => $this->kind];
    }
}
