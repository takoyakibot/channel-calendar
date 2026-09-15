<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Group extends Model
{
    use HasFactory;

    public const RESERVED_SLUGS = [
        'admin', 'api', 'auth', 'login', 'logout', 'dashboard', 'profile', 'register',
        'forgot-password', 'reset-password', 'verify-email', 'email',
        'confirm-password', 'up', 'storage', 'build', 'privacy', 'terms',
    ];

    public const SLUG_PATTERN = '[a-z0-9](?:[a-z0-9-]*[a-z0-9])?';

    protected $fillable = ['name', 'slug', 'parent_id', 'thumbnail_url'];

    // SQLite cannot add a foreign key via ALTER TABLE, so the DB-level cascade
    // on parent_id only exists on MySQL; cascade in the model to stay portable.
    protected static function booted(): void
    {
        static::deleting(function (self $group) {
            $group->children()->get()->each->delete();
        });
    }

    public function channels(): BelongsToMany
    {
        return $this->belongsToMany(Channel::class, 'channel_group');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** Ancestors from the root down to (excluding) this group. */
    public function ancestors(): array
    {
        $chain = [];
        $node = $this->parent;
        while ($node) {
            array_unshift($chain, $node);
            $node = $node->parent;
        }

        return $chain;
    }

    protected function path(): Attribute
    {
        return Attribute::get(function () {
            $slugs = array_map(fn (self $g) => $g->slug, $this->ancestors());
            $slugs[] = $this->slug;

            return implode('/', $slugs);
        });
    }

    /** Ids of this group and every descendant. */
    public function subtreeIds(): array
    {
        $ids = [$this->id];
        $frontier = [$this->id];
        while ($frontier) {
            $frontier = self::whereIn('parent_id', $frontier)->pluck('id')->all();
            $ids = array_merge($ids, $frontier);
        }

        return $ids;
    }

    /** Resolve "aaaa/bbbb" to the group at that position in the tree. */
    public static function resolvePath(string $path): ?self
    {
        $parentId = null;
        $group = null;
        foreach (explode('/', trim($path, '/')) as $slug) {
            if ($slug === '') {
                return null;
            }
            $group = self::where('parent_id', $parentId)->where('slug', $slug)->first();
            if (! $group) {
                return null;
            }
            $parentId = $group->id;
        }

        return $group;
    }
}
