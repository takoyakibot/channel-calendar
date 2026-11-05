<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGroupRequest;
use App\Http\Requests\UpdateGroupRequest;
use App\Models\Channel;
use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
            'thumbnail_url' => $this->thumbnailUrl($request),
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
        $oldThumbnail = $group->thumbnail_url;
        $group->update([
            'name' => $request->name,
            'slug' => $request->slug,
            'parent_id' => $request->input('parent_id') ?: null,
            'thumbnail_url' => $this->thumbnailUrl($request),
        ]);
        if ($oldThumbnail !== $group->thumbnail_url) {
            $this->deleteUploadedThumbnail($oldThumbnail);
        }
        $group->channels()->sync($request->input('channels', []));

        return redirect('/admin/groups')->with('success', 'グループを更新しました。');
    }

    public function destroy(Group $group)
    {
        // Children go with the parent via ON DELETE CASCADE, so collect their uploads first.
        $thumbnails = Group::whereIn('id', $group->subtreeIds())->pluck('thumbnail_url');
        $group->delete();
        $thumbnails->each(fn (?string $url) => $this->deleteUploadedThumbnail($url));

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

    /** An uploaded file wins over the URL field; otherwise the URL as typed (empty clears it). */
    private function thumbnailUrl(Request $request): ?string
    {
        if ($request->hasFile('thumbnail_file')) {
            $path = $request->file('thumbnail_file')->store('groups', 'uploads');

            return Storage::disk('uploads')->url($path);
        }

        return $request->input('thumbnail_url') ?: null;
    }

    /** Removes a file we stored ourselves; external URLs are left alone. */
    private function deleteUploadedThumbnail(?string $url): void
    {
        $prefix = rtrim(Storage::disk('uploads')->url(''), '/') . '/';
        if ($url && str_starts_with($url, $prefix)) {
            Storage::disk('uploads')->delete(substr($url, strlen($prefix)));
        }
    }
}
