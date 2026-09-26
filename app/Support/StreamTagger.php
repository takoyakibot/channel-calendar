<?php

namespace App\Support;

use App\Models\Channel;
use App\Models\IgnoredTerm;
use App\Models\Stream;
use App\Models\TagAlias;
use App\Models\UnmatchedTerm;
use Illuminate\Database\Eloquent\Builder;

/**
 * Applies the tag dictionary to stream titles and keeps the list of bracket
 * terms nobody has classified yet.
 *
 * Matching looks at the whole title (not just the 【…】 parts), so "マイクラみたいな
 * ゲーム" gets Minecraft too — accepted; admins can remove a wrong tag. New
 * candidate terms, on the other hand, are only taken from inside 【…】.
 */
class StreamTagger
{
    /** @var array<int, array{alias: string, tag_id: int, ascii: bool}> */
    private array $dictionary = [];

    /** @var array<int, string> */
    private array $ignored = [];

    public function __construct()
    {
        $this->reload();
    }

    public function reload(): void
    {
        $this->dictionary = TagAlias::query()->get(['alias', 'tag_id'])
            ->map(fn (TagAlias $a) => ['alias' => $a->alias, 'tag_id' => (int) $a->tag_id, 'ascii' => TermNormalizer::isAscii($a->alias)])
            ->all();
        $this->ignored = IgnoredTerm::pluck('term')->all();
    }

    /** @return array<int, int> tag ids whose alias occurs in the title */
    public function matchTagIds(string $title): array
    {
        $haystack = TermNormalizer::normalize($title);
        $ids = [];
        foreach ($this->dictionary as $entry) {
            if (mb_strlen($entry['alias']) < 2) {
                continue;
            }
            $hit = $entry['ascii']
                ? preg_match('/(?<![a-z0-9])' . preg_quote($entry['alias'], '/') . '(?![a-z0-9])/u', $haystack) === 1
                : str_contains($haystack, $entry['alias']);
            if ($hit) {
                $ids[$entry['tag_id']] = true;
            }
        }

        return array_keys($ids);
    }

    /** Sync the stream's automatic tags with the dictionary; tags people added by hand are left alone. */
    public function tag(Stream $stream): void
    {
        $matched = $this->matchTagIds($stream->title);
        $current = $stream->tags()->get();
        $manual = $current->filter(fn ($t) => $t->pivot->source === 'manual')->pluck('id')->all();
        $auto = $current->filter(fn ($t) => $t->pivot->source === 'auto')->pluck('id')->all();

        $stale = array_values(array_diff($auto, $matched));
        if ($stale !== []) {
            $stream->tags()->detach($stale);
        }
        foreach (array_diff($matched, $auto, $manual) as $tagId) {
            $stream->tags()->attach($tagId, ['source' => 'auto']);
        }
    }

    /** Re-tag every stream against the current dictionary and rebuild the unmatched list. */
    public function retagAll(): int
    {
        $this->reload();
        $n = 0;
        Stream::query()->select('id', 'title')->chunkById(200, function ($streams) use (&$n) {
            foreach ($streams as $stream) {
                $this->tag($stream);
                $n++;
            }
        });
        $this->rebuildUnmatched();

        return $n;
    }

    /**
     * Recount, from every title, the 【…】 pieces that no alias, ignore rule or
     * member name explains. Rebuilt from scratch so counts stay exact.
     */
    public function rebuildUnmatched(): void
    {
        $found = $this->unmatchedIn(Stream::query());

        UnmatchedTerm::query()->delete();
        $now = now();
        foreach ($found as $term => [$display, $count]) {
            UnmatchedTerm::create(['term' => $term, 'display' => mb_substr($display, 0, 80), 'count' => $count, 'last_seen_at' => $now]);
        }
    }

    /**
     * Unclassified bracket terms in the titles the query yields, most frequent
     * first — the trends panel uses this scoped to one group's channels.
     *
     * @return array<string, array{0: string, 1: int}> term => [display, count]
     */
    public function unmatchedIn(Builder $streams): array
    {
        $channels = Channel::all();
        $found = [];

        $streams->select('streams.id', 'streams.title')->chunkById(200, function ($chunk) use (&$found, $channels) {
            foreach ($chunk as $stream) {
                foreach ($this->bracketPieces($stream->title) as $display) {
                    $term = TermNormalizer::normalize($display);
                    if (mb_strlen($term) < 2 || in_array($term, $this->ignored, true)) {
                        continue;
                    }
                    if ($this->matchTagIds($display) !== [] || MemberDetector::detect($display, $channels) !== []) {
                        continue;
                    }
                    $found[$term] = [$found[$term][0] ?? $display, ($found[$term][1] ?? 0) + 1];
                }
            }
        });

        uksort($found, fn ($a, $b) => [$found[$b][1], $a] <=> [$found[$a][1], $b]);

        return $found;
    }

    /** @return array<int, string> */
    private function bracketPieces(string $title): array
    {
        if (! preg_match_all('/【([^】]{1,60})】/u', $title, $m)) {
            return [];
        }
        $pieces = [];
        foreach ($m[1] as $segment) {
            foreach (preg_split('~\s*[/／|｜]\s*~u', $segment) ?: [] as $piece) {
                $piece = trim($piece);
                if ($piece !== '') {
                    $pieces[] = $piece;
                }
            }
        }

        return $pieces;
    }
}
