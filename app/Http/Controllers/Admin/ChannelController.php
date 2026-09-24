<?php

namespace App\Http\Controllers\Admin;

use App\Console\Commands\FetchStreams;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreChannelRequest;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use App\Http\Requests\UpdateChannelRequest;
use App\Models\Channel;
use App\Services\YouTubeService;
use App\Support\ChannelInput;
use App\Support\XSearchKeywords;
use Illuminate\Support\Facades\Log;

class ChannelController extends Controller
{
    public function index()
    {
        $channels = Channel::orderBy('name')->get();
        $lastFetchedAt = Setting::get(FetchStreams::LAST_FETCHED_AT_KEY);
        $lastFetchedAt = $lastFetchedAt ? Carbon::parse($lastFetchedAt)->setTimezone('Asia/Tokyo') : null;

        return view('admin.channels.index', compact('channels', 'lastFetchedAt'));
    }

    public function create()
    {
        return view('admin.channels.create');
    }

    public function store(StoreChannelRequest $request, YouTubeService $youtube)
    {
        try {
            $parsed = ChannelInput::parse($request->channel);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['channel' => $e->getMessage()]);
        }

        try {
            $info = $youtube->findChannel($parsed['type'], $parsed['value']);
        } catch (\Throwable $e) {
            Log::warning("Channel lookup failed for {$parsed['value']}: {$e->getMessage()}");

            return back()->withInput()->withErrors(['channel' => 'チャンネル情報を取得できませんでした。ハンドルまたはIDを確認してください。']);
        }

        if (Channel::where('channel_id', $info['channel_id'])->exists()) {
            return back()->withInput()->withErrors(['channel' => "「{$info['name']}」は既に登録されています。"]);
        }

        Channel::create([
            'channel_id' => $info['channel_id'],
            'handle' => $info['handle'],
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
            'short_name' => trim((string) $request->input('short_name')) ?: null,
            'x_handle' => $request->normalizedXHandle(),
            'x_search_keywords' => XSearchKeywords::normalize($request->input('x_search_keywords')),
        ]);

        return redirect('/admin/channels')->with('success', 'チャンネルを更新しました。');
    }

    public function destroy(Channel $channel)
    {
        $channel->delete();
        return redirect('/admin/channels')->with('success', 'チャンネルを削除しました。');
    }
}
