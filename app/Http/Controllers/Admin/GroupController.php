<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGroupRequest;
use App\Http\Requests\UpdateGroupRequest;
use App\Models\Channel;
use App\Models\Group;

class GroupController extends Controller
{
    public function index()
    {
        $groups = Group::withCount('channels')->orderBy('name')->get();

        return view('admin.groups.index', compact('groups'));
    }

    public function create()
    {
        $channels = Channel::orderBy('name')->get();

        return view('admin.groups.create', compact('channels'));
    }

    public function store(StoreGroupRequest $request)
    {
        $group = Group::create($request->only('name', 'slug'));
        $group->channels()->sync($request->input('channels', []));

        return redirect('/admin/groups')->with('success', 'グループを追加しました。');
    }

    public function edit(Group $group)
    {
        $channels = Channel::orderBy('name')->get();
        $selected = $group->channels()->pluck('channels.id')->all();

        return view('admin.groups.edit', compact('group', 'channels', 'selected'));
    }

    public function update(UpdateGroupRequest $request, Group $group)
    {
        $group->update($request->only('name', 'slug'));
        $group->channels()->sync($request->input('channels', []));

        return redirect('/admin/groups')->with('success', 'グループを更新しました。');
    }

    public function destroy(Group $group)
    {
        $group->delete();

        return redirect('/admin/groups')->with('success', 'グループを削除しました。');
    }
}
