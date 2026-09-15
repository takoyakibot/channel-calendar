<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Models\Group;
use Illuminate\Database\Eloquent\Collection;

class CalendarController extends Controller
{
    public function index()
    {
        $children = Group::whereNull('parent_id')->orderBy('name')->get();

        return view('calendar.index', [
            'group' => null,
            'children' => $children,
            'childChannelMap' => $this->buildChildChannelMap($children),
        ]);
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
