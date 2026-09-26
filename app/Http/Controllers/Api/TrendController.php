<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\Group;
use App\Models\Stream;
use App\Support\StreamTagger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * "What is the group up to this week": streams per game / category for a JST
 * Monday-to-Sunday week, who streamed it, and how that compares to the week
 * before — plus the bracket terms still waiting to be classified.
 */
class TrendController extends Controller
{
    private const TZ = 'Asia/Tokyo';

    public function __construct(private StreamTagger $tagger)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $request->validate([
            'group' => 'nullable|string|max:255',
            // "week" (Mon–Sun, the board) or "month" (the month view); "date" is any
            // day inside the period. "week" as a date is the older spelling of "date".
            'period' => ['nullable', Rule::in(['week', 'month'])],
            'date' => 'nullable|date',
            'week' => 'nullable|date',
            // Optional narrowing to the channels the page is currently showing
            // (sub-group toggles, hidden channels); never widens past the group.
            'channels' => 'nullable|string|max:2000',
        ]);

        $channelIds = $this->channelIds($request->input('group'));
        if ($request->filled('channels')) {
            $wanted = array_map('intval', array_filter(explode(',', $request->channels), 'is_numeric'));
            $channelIds = array_values(array_intersect($channelIds, $wanted));
        }

        $period = $request->input('period', 'week');
        $anchorInput = $request->input('date') ?? $request->input('week');
        $anchor = $anchorInput ? Carbon::parse($anchorInput, self::TZ) : now(self::TZ);
        if ($period === 'month') {
            $start = $anchor->copy()->startOfMonth()->startOfDay();
            $end = $start->copy()->addMonth();
            $prevStart = $start->copy()->subMonth();
        } else {
            $start = $anchor->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
            $end = $start->copy()->addWeek();
            $prevStart = $start->copy()->subWeek();
        }

        $current = $this->aggregate($this->streamsBetween($channelIds, $start, $end));
        $previous = $this->aggregate($this->streamsBetween($channelIds, $prevStart, $start));

        $tags = collect($current)->map(fn (array $row, int $tagId) => [
            'id' => $tagId,
            'name' => $row['name'],
            'kind' => $row['kind'],
            'count' => $row['count'],
            'prev_count' => $previous[$tagId]['count'] ?? 0,
            'members' => array_values($row['members']),
        ])->sortBy([['count', 'desc'], ['name', 'asc']])->values();

        return response()->json([
            'period' => $period,
            'start' => $start->toDateString(),
            'end' => $end->copy()->subDay()->toDateString(),
            'tags' => $tags,
            'members' => $this->memberActivity($channelIds, $start, $end, $prevStart),
            // Only terms from these channels' titles: a group page must not ask
            // people to classify another group's vocabulary.
            'unmatched' => collect($this->tagger->unmatchedIn(Stream::whereIn('channel_id', $channelIds)))
                ->map(fn (array $row, string $term) => ['term' => $term, 'display' => $row[0], 'count' => $row[1]])
                ->values()
                ->take(40),
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

    /**
     * How much each member put out in the period: streams, shorts, uploads on
     * their own channel, and clips / guest appearances registered for them.
     *
     * @param  array<int, int>  $channelIds
     * @return array<int, array<string, mixed>>
     */
    private function memberActivity(array $channelIds, Carbon $start, Carbon $end, Carbon $prevStart): array
    {
        if ($channelIds === []) {
            return [];
        }

        $streamCounts = function (Carbon $from, Carbon $to) use ($channelIds): array {
            $rows = Stream::whereIn('channel_id', $channelIds)
                ->where('scheduled_at', '>=', $from->copy()->utc())
                ->where('scheduled_at', '<', $to->copy()->utc())
                ->selectRaw('channel_id, type, count(*) as n')
                ->groupBy('channel_id', 'type')
                ->get();
            $out = [];
            foreach ($rows as $r) {
                $out[$r->channel_id][$r->type] = (int) $r->n;
            }

            return $out;
        };
        $current = $streamCounts($start, $end);
        $previous = $streamCounts($prevStart, $start);

        $posts = [];
        $postRows = DB::table('channel_video_post')
            ->join('video_posts', 'video_posts.id', '=', 'channel_video_post.video_post_id')
            ->whereIn('channel_video_post.channel_id', $channelIds)
            ->where('video_posts.published_at', '>=', $start->copy()->utc())
            ->where('video_posts.published_at', '<', $end->copy()->utc())
            ->selectRaw('channel_video_post.channel_id as channel_id, video_posts.kind as kind, count(*) as n')
            ->groupBy('channel_video_post.channel_id', 'video_posts.kind')
            ->get();
        foreach ($postRows as $r) {
            $posts[$r->channel_id][$r->kind] = (int) $r->n;
        }

        return Channel::whereIn('id', $channelIds)->orderBy('name')->get()
            ->map(fn (Channel $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'thumbnail_url' => $c->thumbnail_url,
                'color' => $c->color,
                'streams' => $current[$c->id]['stream'] ?? 0,
                'prev_streams' => $previous[$c->id]['stream'] ?? 0,
                'shorts' => $current[$c->id]['short'] ?? 0,
                'uploads' => $current[$c->id]['upload'] ?? 0,
                'clips' => $posts[$c->id]['clip'] ?? 0,
                'guests' => $posts[$c->id]['guest'] ?? 0,
            ])
            ->sortBy([['streams', 'desc'], ['shorts', 'desc'], ['name', 'asc']])
            ->values()
            ->all();
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
