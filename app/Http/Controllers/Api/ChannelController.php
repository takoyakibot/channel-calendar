<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\Group;
use App\Support\XSearchKeywords;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChannelController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['group' => 'nullable|string|max:255']);

        $groupIds = null;
        if ($request->filled('group')) {
            $group = Group::resolvePath($request->group);
            abort_unless($group, 404);
            $groupIds = $group->subtreeIds();
        }

        $channels = Channel::active()
            ->when($groupIds !== null, fn ($q) => $q->whereHas('groups', fn ($g) => $g->whereIn('groups.id', $groupIds)))
            ->select('id', 'name', 'color', 'thumbnail_url', 'x_handle', 'x_search_keywords')
            ->orderBy('name')
            ->get()
            ->map(fn (Channel $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'color' => $c->color,
                'thumbnail_url' => $c->thumbnail_url,
                'x_handle' => $c->x_handle,
                'x_search_keywords' => XSearchKeywords::parse($c->x_search_keywords),
            ]);

        return response()->json($channels);
    }
}
