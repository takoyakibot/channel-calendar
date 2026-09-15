<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Models\Group;
use Illuminate\Database\Eloquent\Collection;

class CalendarController extends Controller
{
    public function index()
    {
        $groups = Group::whereNull('parent_id')->orderBy('name')->get();

        // A top-level card represents the whole subtree, so gather the active
        // channels of the group and every descendant (each channel once).
        foreach ($groups as $group) {
            $channels = Channel::active()
                ->whereHas('groups', fn ($g) => $g->whereIn('groups.id', $group->subtreeIds()))
                ->orderBy('name')
                ->get();
            $group->setRelation('channels', $channels);
            $group->channels_count = $channels->count();
        }

        return view('landing', compact('groups'));
    }

    public function show(string $path)
    {
        $group = Group::resolvePath($path);
        abort_unless($group, 404);

        $children = $group->children()->orderBy('name')->get();

        return view('calendar.index', [
            'group' => $group,
            'children' => $children,
            'childChannelMap' => $this->buildChildChannelMap($children),
        ]);
    }

    /** @return array<int, list<int>> child group id => channel ids in its subtree */
    private function buildChildChannelMap(Collection $children): array
    {
        $map = [];
        foreach ($children as $child) {
            $map[$child->id] = Channel::active()
                ->whereHas('groups', fn ($g) => $g->whereIn('groups.id', $child->subtreeIds()))
                ->pluck('id')
                ->all();
        }

        return $map;
    }
}
