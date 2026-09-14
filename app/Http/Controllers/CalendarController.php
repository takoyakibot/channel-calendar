<?php

namespace App\Http\Controllers;

use App\Models\Group;

class CalendarController extends Controller
{
    public function index()
    {
        return view('calendar.index', [
            'group' => null,
            'children' => Group::whereNull('parent_id')->orderBy('name')->get(),
        ]);
    }

    public function show(string $path)
    {
        $group = Group::resolvePath($path);
        abort_unless($group, 404);

        return view('calendar.index', [
            'group' => $group,
            'children' => $group->children()->orderBy('name')->get(),
        ]);
    }
}
