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
        $groups = Group::with('parent')->withCount('channels')->get()->sortBy('path')->values();

        return view('admin.groups.index', compact('groups'));
    }

    public function create()
    {
        return view('admin.groups.create', [
            'channels' => Channel::orderBy('name')->get(),
            'parents' => $this->parentOptions(),
        ]);
    }

    public function store(StoreGroupRequest $request)
    {
        $group = Group::create([
            'name' => $request->name,
            'slug' => $request->slug,
            'parent_id' => $request->input('parent_id') ?: null,
        ]);
        $group->channels()->sync($request->input('channels', []));

        return redirect('/admin/groups')->with('success', 'グループを追加しました。');
    }

    public function edit(Group $group)
    {
        return view('admin.groups.edit', [
            'group' => $group,
            'channels' => Channel::orderBy('name')->get(),
            'selected' => $group->channels()->pluck('channels.id')->all(),
            'parents' => $this->parentOptions($group),
        ]);
    }

    public function update(UpdateGroupRequest $request, Group $group)
    {
        $group->update([
            'name' => $request->name,
            'slug' => $request->slug,
            'parent_id' => $request->input('parent_id') ?: null,
        ]);
        $group->channels()->sync($request->input('channels', []));

        return redirect('/admin/groups')->with('success', 'グループを更新しました。');
    }

    public function destroy(Group $group)
    {
        $group->delete();

        return redirect('/admin/groups')->with('success', 'グループを削除しました。');
    }

    /** Groups that may be chosen as a parent: everything except the group itself and its descendants. */
    private function parentOptions(?Group $exclude = null)
    {
        $groups = Group::with('parent')->get();
        if ($exclude) {
            $excluded = $exclude->subtreeIds();
            $groups = $groups->reject(fn (Group $g) => in_array($g->id, $excluded, true));
        }

        return $groups->sortBy('path')->values();
    }
}
