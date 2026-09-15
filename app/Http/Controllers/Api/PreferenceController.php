<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PreferenceController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json($request->user()->preferences ?? []);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'hidden_channels' => 'nullable|array|max:500',
            'hidden_channels.*' => 'integer',
            'view' => 'nullable|string|in:board,month',
            'filter_collapsed' => 'nullable|boolean',
        ]);

        $user = $request->user();
        $prefs = $user->preferences ?? [];
        $user->update(['preferences' => array_merge($prefs, $validated)]);

        return response()->json($user->preferences);
    }
}
