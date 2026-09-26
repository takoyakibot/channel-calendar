<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\IgnoredTerm;
use App\Models\Tag;
use App\Models\TagAlias;
use Illuminate\Validation\ValidationException;

/**
 * "This bracket term is a game / a category / noise" — the one-time teaching
 * step after which the dictionary applies to every stream, past and future.
 */
final class TermClassifier
{
    public const KINDS = ['game', 'category', 'ignore'];

    /**
     * @return ?Tag the tag the term now maps to (null when ignored)
     *
     * @throws TermAlreadyClassified when the term already has an alias or an ignore rule
     */
    public static function classify(string $rawTerm, string $kind, ?int $tagId, ?string $newName, ?int $userId): ?Tag
    {
        $display = trim($rawTerm);
        $term = TermNormalizer::normalize($display);
        if (mb_strlen($term) < 2) {
            throw ValidationException::withMessages(['term' => '語句が短すぎます。']);
        }
        if (TagAlias::where('alias', $term)->exists() || IgnoredTerm::where('term', $term)->exists()) {
            throw new TermAlreadyClassified("Already classified: {$term}");
        }

        $tag = null;
        if ($kind === 'ignore') {
            IgnoredTerm::create(['term' => $term, 'display' => mb_substr($display, 0, 80), 'created_by_user_id' => $userId]);
        } else {
            $tag = $tagId ? Tag::findOrFail($tagId) : self::findOrCreateTag(trim((string) $newName) ?: $display, $kind, $userId);
            if (! TagAlias::where('alias', $term)->exists()) {
                TagAlias::create(['tag_id' => $tag->id, 'alias' => $term, 'created_by_user_id' => $userId]);
            }
        }

        ActivityLog::record($userId, 'classify_term', Tag::class, $tag?->id, [
            'term' => $display,
            'kind' => $kind,
            'tag' => $tag?->name,
        ]);

        app(StreamTagger::class)->retagAll();

        return $tag;
    }

    /** By exact name, then by an alias of the normalised name, else a new tag. */
    public static function findOrCreateTag(string $name, string $kind, ?int $userId): Tag
    {
        $name = mb_substr(trim($name), 0, 60);
        if ($existing = Tag::where('name', $name)->first()) {
            return $existing;
        }
        if ($alias = TagAlias::with('tag')->where('alias', TermNormalizer::normalize($name))->first()) {
            return $alias->tag;
        }

        return Tag::createWithAlias($name, $kind, $userId);
    }
}
