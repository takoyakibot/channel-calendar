<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Stream;
use App\Models\Tag;
use App\Support\StreamTagger;
use App\Support\TermClassifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Tags on one stream: anyone may add (issue #51), only admins remove. */
class StreamTagController extends Controller
{
    public function store(Request $request, Stream $stream): JsonResponse
    {
        $user = $request->user();
        if ($user?->is_banned) {
            return response()->json(['message' => 'アカウントが停止されています。'], 403);
        }

        $validated = $request->validate([
            'tag_id' => 'nullable|integer|exists:tags,id',
            'name' => 'nullable|string|max:60',
            'kind' => ['nullable', Rule::in(Tag::KINDS)],
        ]);
        if (empty($validated['tag_id']) && blank($validated['name'] ?? null)) {
            throw ValidationException::withMessages(['tag_id' => 'タグを選ぶか、新しいタグ名を入力してください。']);
        }

        $created = false;
        if (! empty($validated['tag_id'])) {
            $tag = Tag::findOrFail($validated['tag_id']);
        } else {
            $before = Tag::count();
            $tag = TermClassifier::findOrCreateTag($validated['name'], $validated['kind'] ?? 'game', $user?->id);
            $created = Tag::count() > $before;
        }

        if ($stream->tags()->where('tags.id', $tag->id)->exists()) {
            return response()->json(['message' => 'このタグは付いています。'], 409);
        }
        $stream->tags()->attach($tag->id, ['source' => 'manual']);

        ActivityLog::record($user?->id, 'add_stream_tag', Stream::class, $stream->id, [
            'tag' => $tag->name, 'kind' => $tag->kind, 'video_id' => $stream->video_id, 'title' => $stream->title,
        ]);

        // A brand-new tag name is also a dictionary entry: give it to every other stream that mentions it.
        if ($created) {
            app(StreamTagger::class)->retagAll();
        }

        return response()->json([
            'tags' => $stream->tags()->get()->map(fn (Tag $t) => $t->toArrayForApi())->values(),
        ], 201);
    }

    public function destroy(Request $request, Stream $stream, Tag $tag): JsonResponse
    {
        $stream->tags()->detach($tag->id);

        ActivityLog::record($request->user()->id, 'remove_stream_tag', Stream::class, $stream->id, [
            'tag' => $tag->name, 'video_id' => $stream->video_id,
        ]);

        return response()->json(['message' => '外しました。']);
    }
}
