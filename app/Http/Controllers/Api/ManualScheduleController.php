<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
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
            'allDay' => (bool) $s->is_all_day,
            'color' => $s->channel->color,
            'extendedProps' => [
                'is_all_day' => (bool) $s->is_all_day,
                'channel_id' => $s->channel_id,
                'channel_name' => $s->channel->name,
                'channel_thumbnail_url' => $s->channel->thumbnail_url,
                'status' => 'manual',
                'registered_by' => $s->user->name,
                'manual_schedule_id' => $s->id,
                'source_url' => $s->source_url,
                'created_at' => $s->created_at?->toIso8601String(),
            ],
        ]);

        return response()->json($events);
    }

    public function store(Request $request): JsonResponse
    {
        if ($request->user()->is_banned) {
            return response()->json(['message' => 'アカウントが停止されています。'], 403);
        }

        $validated = $request->validate([
            'channel_id' => 'required|integer|exists:channels,id',
            'title' => 'required|string|max:255',
            'source_url' => 'nullable|url:http,https|max:2048',
            'is_all_day' => 'nullable|boolean',
            'scheduled_at' => [
                'required',
                'date',
                // A timed entry must be in the future; an all-day entry (sent as the
                // local midnight) is fine as long as its day has not ended yet.
                function (string $attribute, mixed $value, \Closure $fail) use ($request) {
                    $at = Carbon::parse($value);
                    $deadline = $request->boolean('is_all_day') ? $at->copy()->addDay() : $at;
                    if ($deadline->lte(now())) {
                        $fail('過去の日時は登録できません。');
                    }
                },
            ],
        ]);

        $schedule = ManualSchedule::create([
            'user_id' => $request->user()->id,
            'channel_id' => $validated['channel_id'],
            'title' => $validated['title'],
            'source_url' => $validated['source_url'] ?? null,
            'scheduled_at' => Carbon::parse($validated['scheduled_at'])->utc(),
            'is_all_day' => $request->boolean('is_all_day'),
        ]);

        ActivityLog::record($request->user()->id, 'create_schedule', ManualSchedule::class, $schedule->id, [
            'title' => $schedule->title,
            'channel_id' => $schedule->channel_id,
            'source_url' => $schedule->source_url,
            'scheduled_at' => $schedule->scheduled_at->toIso8601String(),
        ]);

        return response()->json($schedule->load('channel', 'user'), 201);
    }

    public function destroy(Request $request, ManualSchedule $manualSchedule): JsonResponse
    {
        $user = $request->user();

        if ($manualSchedule->user_id !== $user->id && ! $user->is_admin) {
            abort(403);
        }

        ActivityLog::record($user->id, 'delete_schedule', ManualSchedule::class, $manualSchedule->id, [
            'title' => $manualSchedule->title,
            'channel_id' => $manualSchedule->channel_id,
            'owner_user_id' => $manualSchedule->user_id,
        ]);

        $manualSchedule->delete();

        return response()->json(['message' => '削除しました。']);
    }
}
