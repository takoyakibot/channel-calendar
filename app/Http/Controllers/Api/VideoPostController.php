<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Channel;
use App\Models\Group;
use App\Models\VideoPost;
use App\Services\YouTubeService;
use App\Support\MemberDetector;
use App\Support\VideoUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Clips and guest appearances: videos on other channels that feature members.
 * Anyone may register one from a URL (rate limited, one row per video); only
 * admins remove them (Admin\VideoPostController).
 */
class VideoPostController extends Controller
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

        [$start, $end] = self::range($request->start, $request->end);

        $posts = VideoPost::with('channels')
            ->whereHas('channels', fn (Builder $q) => $q->where('is_active', true)
                ->when($groupIds !== null, fn ($c) => $c->whereHas('groups', fn ($g) => $g->whereIn('groups.id', $groupIds))))
            ->whereBetween('published_at', [$start, $end])
            ->orderBy('published_at')
            ->get();

        return response()->json($posts->map(fn (VideoPost $p) => $p->toEvent())->values());
    }

    /** Resolve a pasted URL before registering: what the video is, and which members it seems to feature. */
    public function preview(Request $request, YouTubeService $youtube): JsonResponse
    {
        $request->validate(['url' => 'required|string|max:2048']);

        $videoId = VideoUrl::videoId($request->url);
        if ($videoId === null) {
            throw ValidationException::withMessages(['url' => 'YouTube の動画 URL を指定してください。']);
        }

        $info = $youtube->getVideoInfo($videoId);
        if ($info === null) {
            return response()->json(['message' => '動画が見つかりません（削除済み・非公開の可能性があります）。'], 404);
        }

        return response()->json([
            'video_id' => $videoId,
            'title' => $info['title'],
            'source_channel_name' => $info['channel_title'],
            'thumbnail_url' => $info['thumbnail_url'],
            'published_at' => $info['published_at'] ? Carbon::parse($info['published_at'])->toIso8601String() : null,
            'duration_seconds' => $info['duration_seconds'],
            'detected_channel_ids' => MemberDetector::detect($info['title'] . "\n" . $info['description'], Channel::active()->orderBy('id')->get()),
            'already_registered' => VideoPost::where('video_id', $videoId)->exists(),
            'source_is_member' => Channel::where('channel_id', $info['channel_id'])->exists(),
        ]);
    }

    public function store(Request $request, YouTubeService $youtube): JsonResponse
    {
        $user = $request->user();
        if ($user?->is_banned) {
            return response()->json(['message' => 'アカウントが停止されています。'], 403);
        }

        $validated = $request->validate([
            'url' => 'required|string|max:2048',
            'kind' => ['required', Rule::in(VideoPost::KINDS)],
            'channel_ids' => 'required|array|min:1|max:20',
            'channel_ids.*' => ['integer', 'distinct', Rule::exists('channels', 'id')->where('is_active', true)],
        ]);

        $videoId = VideoUrl::videoId($validated['url']);
        if ($videoId === null) {
            throw ValidationException::withMessages(['url' => 'YouTube の動画 URL を指定してください。']);
        }
        if (VideoPost::where('video_id', $videoId)->exists()) {
            return response()->json(['message' => 'この動画は登録済みです。'], 409);
        }

        $info = $youtube->getVideoInfo($videoId);
        if ($info === null || ! $info['published_at']) {
            throw ValidationException::withMessages(['url' => '動画が見つかりません（削除済み・非公開の可能性があります）。']);
        }
        if (Channel::where('channel_id', $info['channel_id'])->exists()) {
            throw ValidationException::withMessages(['url' => 'メンバー自身のチャンネルの動画は自動で取り込まれるため、ここでは登録できません。']);
        }

        $post = VideoPost::create([
            'video_id' => $videoId,
            'kind' => $validated['kind'],
            'title' => $info['title'],
            'thumbnail_url' => $info['thumbnail_url'],
            'source_channel_id' => $info['channel_id'],
            'source_channel_name' => $info['channel_title'],
            'published_at' => Carbon::parse($info['published_at'])->utc(),
            'duration_seconds' => $info['duration_seconds'],
            'submitted_by_user_id' => $user?->id,
            'submitter_hash' => hash('sha256', $request->ip() . config('app.key')),
        ]);
        $post->channels()->sync($validated['channel_ids']);

        ActivityLog::record($user?->id, 'create_video_post', VideoPost::class, $post->id, [
            'video_id' => $videoId,
            'kind' => $post->kind,
            'title' => $post->title,
            'source_channel_name' => $post->source_channel_name,
            'channel_ids' => array_values($validated['channel_ids']),
            'submitter' => substr($post->submitter_hash, 0, 12),
        ]);

        return response()->json($post->load('channels')->toEvent(), 201);
    }

    /**
     * Plain dates cover whole days; ISO instants (with offset) are used as-is.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private static function range(string $start, string $end): array
    {
        $isDate = fn (string $s) => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $s);
        $from = Carbon::parse($start);
        $to = Carbon::parse($end);
        if ($isDate($end)) {
            $to = $to->endOfDay();
        }

        return [$from->utc(), $to->utc()];
    }
}
