<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreChannelRequest;
use App\Http\Requests\UpdateChannelRequest;
use App\Models\Channel;
use App\Services\YouTubeService;
use Illuminate\Support\Facades\Log;

class ChannelController extends Controller
{
    public function index()
    {
        $channels = Channel::orderBy('name')->get();
        return view('admin.channels.index', compact('channels'));
    }

    public function create()
    {
        return view('admin.channels.create');
    }

    public function store(StoreChannelRequest $request, YouTubeService $youtube)
    {
        try {
            $info = $youtube->getChannelInfo($request->channel_id);
        } catch (\Throwable $e) {
            Log::warning("Channel lookup failed for {$request->channel_id}: {$e->getMessage()}");

            return back()->withInput()->withErrors(['channel_id' => 'チャンネル情報を取得できませんでした。チャンネルIDを確認してください。']);
        }

        Channel::create([
            'channel_id' => $request->channel_id,
            'name' => $info['name'],
            'thumbnail_url' => $info['thumbnail_url'],
            'color' => $request->color,
        ]);

        return redirect('/admin/channels')->with('success', 'チャンネルを追加しました。');
    }

    public function edit(Channel $channel)
    {
        return view('admin.channels.edit', compact('channel'));
    }

    public function update(UpdateChannelRequest $request, Channel $channel)
    {
        $channel->update([
            'color' => $request->color,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect('/admin/channels')->with('success', 'チャンネルを更新しました。');
    }

    public function destroy(Channel $channel)
    {
        $channel->delete();
        return redirect('/admin/channels')->with('success', 'チャンネルを削除しました。');
    }
}
