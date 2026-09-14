<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChannelController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['group' => 'nullable|string|max:50']);

        $group = $request->filled('group')
            ? Group::where('slug', $request->group)->firstOrFail()
            : null;

        $channels = Channel::active()
            ->when($group, fn ($q) => $q->whereHas('groups', fn ($g) => $g->where('groups.id', $group->id)))
            ->select('id', 'name', 'color', 'thumbnail_url')
            ->orderBy('name')
            ->get();

        return response()->json($channels);
    }
}
