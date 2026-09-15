<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\Group;
use App\Models\ManualSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ManualScheduleController extends Controller
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

        $start = Carbon::parse($request->start)->utc();
        $end = Carbon::parse($request->end)->utc();

        $schedules = ManualSchedule::with('channel', 'user')
            ->whereHas('channel', function ($q) use ($groupIds) {
                $q->where('is_active', true);
                if ($groupIds !== null) {
                    $q->whereHas('groups', fn ($g) => $g->whereIn('groups.id', $groupIds));
                }
            })
            ->whereBetween('scheduled_at', [$start, $end])
            ->orderBy('scheduled_at')
            ->get();

        $events = $schedules->map(fn (ManualSchedule $s) => [
            'id' => 'ms_' . $s->id,
            'title' => $s->title,
            'start' => $s->scheduled_at->toIso8601String(),
            'color' => $s->channel->color,
            'extendedProps' => [
                'channel_id' => $s->channel_id,
                'channel_name' => $s->channel->name,
                'channel_thumbnail_url' => $s->channel->thumbnail_url,
                'status' => 'manual',
                'registered_by' => $s->user->name,
                'manual_schedule_id' => $s->id,
            ],
        ]);

        return response()->json($events);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'channel_id' => 'required|integer|exists:channels,id',
            'title' => 'required|string|max:255',
            'scheduled_at' => 'required|date|after:now',
        ]);

        $schedule = ManualSchedule::create([
            'user_id' => $request->user()->id,
            'channel_id' => $validated['channel_id'],
            'title' => $validated['title'],
            'scheduled_at' => Carbon::parse($validated['scheduled_at'])->utc(),
        ]);

        return response()->json($schedule->load('channel', 'user'), 201);
    }

    public function destroy(Request $request, ManualSchedule $manualSchedule): JsonResponse
    {
        $user = $request->user();

        if ($manualSchedule->user_id !== $user->id && ! $user->is_admin) {
            abort(403);
        }

        $manualSchedule->delete();

        return response()->json(['message' => '削除しました。']);
    }
}
