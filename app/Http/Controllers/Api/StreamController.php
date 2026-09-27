<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Stream;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class StreamController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'start' => 'required|date',
            'end' => 'required|date',
            'group' => 'nullable|string|max:255',
        ]);

        $groupIds = null;
        if ($request->filled('group')) {
            $group = Group::resolvePath($request->group);
            abort_unless($group, 404);
            $groupIds = $group->subtreeIds();
        }

        $start = Carbon::parse($request->start);
        $end = Carbon::parse($request->end);

        if (strlen($request->start) <= 10) {
            $start = $start->startOfDay();
        }

        if (strlen($request->end) <= 10) {
            $end = $end->endOfDay();
        }

        // Query bindings serialize a Carbon instance using its own timezone, not
        // the app's — an ISO-8601 string with an explicit offset (e.g. +09:00)
        // would otherwise be compared against UTC-stored timestamps unconverted.
        $start = $start->utc();
        $end = $end->utc();

        $streams = Stream::with('channel', 'tags')
            ->whereHas('channel', function ($q) use ($groupIds) {
                $q->where('is_active', true);
                if ($groupIds !== null) {
                    $q->whereHas('groups', fn ($g) => $g->whereIn('groups.id', $groupIds));
                }
            })
            ->whereBetween('scheduled_at', [$start, $end])
            ->orderBy('scheduled_at')
            ->get();

        $events = $streams->map(fn (Stream $stream) => [
            'id' => $stream->id,
            'title' => $stream->title,
            'start' => $stream->scheduled_at->toIso8601String(),
            // Real end when known: the broadcast's end, or start + length for videos.
            'end' => ($stream->actual_end_at
                ?? ($stream->duration_seconds ? $stream->scheduled_at->copy()->addSeconds($stream->duration_seconds) : null)
            )?->toIso8601String(),
            'url' => $stream->url(),
            'color' => $stream->channel->color,
            'extendedProps' => [
                'channel_id' => $stream->channel_id,
                'channel_name' => $stream->channel->name,
                'channel_thumbnail_url' => $stream->channel->thumbnail_url,
                'thumbnail_url' => $stream->thumbnail_url,
                'status' => $stream->status,
                'type' => $stream->type ?? 'stream',
                'platform' => $stream->platform ?? 'youtube',
                'is_members_only' => $stream->is_members_only,
                'tags' => $stream->tags->map(fn ($t) => $t->toArrayForApi())->values(),
                'created_at' => $stream->created_at?->toIso8601String(),
            ],
        ]);

        return response()->json($events);
    }

    /**
     * Title search within the group, newest first — used to see which streams a
     * bracket term came from before classifying it, and as a plain search box.
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:1|max:100',
            'group' => 'nullable|string|max:255',
        ]);

        $groupIds = null;
        if ($request->filled('group')) {
            $group = Group::resolvePath($request->group);
            abort_unless($group, 404);
            $groupIds = $group->subtreeIds();
        }

        $needle = addcslashes(trim($request->q), '%_\\');

        $streams = Stream::with('channel', 'tags')
            ->whereHas('channel', function ($q) use ($groupIds) {
                $q->where('is_active', true);
                if ($groupIds !== null) {
                    $q->whereHas('groups', fn ($g) => $g->whereIn('groups.id', $groupIds));
                }
            })
            ->where('title', 'like', "%{$needle}%")
            ->orderByDesc('scheduled_at')
            ->limit(50)
            ->get();

        return response()->json($streams->map(fn (Stream $s) => [
            'id' => $s->id,
            'title' => $s->title,
            'start' => $s->scheduled_at->toIso8601String(),
            'url' => $s->url(),
            'status' => $s->status,
            'type' => $s->type ?? 'stream',
            'platform' => $s->platform ?? 'youtube',
            'channel' => [
                'id' => $s->channel->id,
                'name' => $s->channel->name,
                'thumbnail_url' => $s->channel->thumbnail_url,
                'color' => $s->channel->color,
            ],
            'tags' => $s->tags->map(fn ($t) => $t->toArrayForApi())->values(),
        ])->values());
    }
}
