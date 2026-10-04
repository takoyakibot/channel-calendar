<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\Group;
use App\Support\StreamDigest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Digest text for the X share button, built when the button is pressed. */
class ShareDigestController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $request->validate(['group' => 'nullable|string|max:255', 'channels' => 'nullable|boolean']);
        $withChannel = $request->boolean('channels', true);

        if (! $request->filled('group')) {
            return response()->json(['text' => StreamDigest::current(withChannel: $withChannel)]);
        }

        $group = Group::resolvePath($request->input('group'));
        abort_unless($group, 404);

        $channelIds = Channel::active()
            ->whereHas('groups', fn ($g) => $g->whereIn('groups.id', $group->subtreeIds()))
            ->pluck('id')
            ->all();

        return response()->json([
            'text' => StreamDigest::current($channelIds, url('/' . $group->path), $group->name, $withChannel),
        ]);
    }
}
