<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\Group;
use App\Models\Stream;
use App\Models\UnmatchedTerm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * "What is the group up to this week": streams per game / category for a JST
 * Monday-to-Sunday week, who streamed it, and how that compares to the week
 * before — plus the bracket terms still waiting to be classified.
 */
class TrendController extends Controller
{
    private const TZ = 'Asia/Tokyo';

    public function show(Request $request): JsonResponse
    {
        $request->validate([
            'group' => 'nullable|string|max:255',
            'week' => 'nullable|date',
        ]);

        $channelIds = $this->channelIds($request->input('group'));

        $anchor = $request->filled('week') ? Carbon::parse($request->week, self::TZ) : now(self::TZ);
        $weekStart = $anchor->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $weekEnd = $weekStart->copy()->addWeek();
        $prevStart = $weekStart->copy()->subWeek();

        $current = $this->aggregate($this->streamsBetween($channelIds, $weekStart, $weekEnd));
        $previous = $this->aggregate($this->streamsBetween($channelIds, $prevStart, $weekStart));

        $tags = collect($current)->map(fn (array $row, int $tagId) => [
            'id' => $tagId,
            'name' => $row['name'],
            'kind' => $row['kind'],
            'count' => $row['count'],
            'prev_count' => $previous[$tagId]['count'] ?? 0,
            'members' => array_values($row['members']),
        ])->sortBy([['count', 'desc'], ['name', 'asc']])->values();

        return response()->json([
            'week_start' => $weekStart->toDateString(),
            'week_end' => $weekEnd->copy()->subDay()->toDateString(),
            'tags' => $tags,
            'unmatched' => UnmatchedTerm::orderByDesc('count')->orderBy('term')->limit(40)->get()
                ->map(fn (UnmatchedTerm $t) => ['term' => $t->term, 'display' => $t->display, 'count' => $t->count])
                ->values(),
        ]);
    }

    /** @return array<int, int> */
    private function channelIds(?string $groupPath): array
    {
        $query = Channel::active();
        if ($groupPath) {
            $group = Group::resolvePath($groupPath);
            abort_unless($group, 404);
            $ids = $group->subtreeIds();
            $query->whereHas('groups', fn ($g) => $g->whereIn('groups.id', $ids));
        }

        return $query->pluck('id')->all();
    }

    /** @param  array<int, int>  $channelIds */
    private function streamsBetween(array $channelIds, Carbon $from, Carbon $to): Collection
    {
        return Stream::with('tags', 'channel')
            ->where('type', 'stream')
            ->whereIn('channel_id', $channelIds)
            ->where('scheduled_at', '>=', $from->copy()->utc())
            ->where('scheduled_at', '<', $to->copy()->utc())
            ->get();
    }

    /** @return array<int, array{name: string, kind: string, count: int, members: array<int, array>}> keyed by tag id */
    private function aggregate(Collection $streams): array
    {
        $rows = [];
        foreach ($streams as $stream) {
            foreach ($stream->tags as $tag) {
                $rows[$tag->id] ??= ['name' => $tag->name, 'kind' => $tag->kind, 'count' => 0, 'members' => []];
                $rows[$tag->id]['count']++;
                $rows[$tag->id]['members'][$stream->channel_id] ??= [
                    'id' => $stream->channel->id,
                    'name' => $stream->channel->name,
                    'thumbnail_url' => $stream->channel->thumbnail_url,
                    'color' => $stream->channel->color,
                ];
            }
        }

        return $rows;
    }
}
