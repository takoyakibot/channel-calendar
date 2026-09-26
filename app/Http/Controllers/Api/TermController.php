<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UnmatchedTerm;
use App\Support\TermAlreadyClassified;
use App\Support\TermClassifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Anyone can teach the dictionary what an unclassified bracket term is (issue #51). */
class TermController extends Controller
{
    public function classify(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user?->is_banned) {
            return response()->json(['message' => 'アカウントが停止されています。'], 403);
        }

        $validated = $request->validate([
            'term' => 'required|string|max:80',
            'kind' => ['required', Rule::in(TermClassifier::KINDS)],
            'tag_id' => 'nullable|integer|exists:tags,id',
            'name' => 'nullable|string|max:60',
        ]);

        try {
            $tag = TermClassifier::classify($validated['term'], $validated['kind'], $validated['tag_id'] ?? null, $validated['name'] ?? null, $user?->id);
        } catch (TermAlreadyClassified $e) {
            return response()->json(['message' => 'この語句は分類済みです。変更は管理者に依頼してください。'], 409);
        }

        return response()->json([
            'tag' => $tag?->toArrayForApi(),
            'unmatched_remaining' => UnmatchedTerm::count(),
        ], 201);
    }
}
