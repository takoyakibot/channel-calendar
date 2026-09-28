<?php

namespace App\Http\Controllers\Admin;

use App\Console\Commands\FetchStreams;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreChannelRequest;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use App\Http\Requests\UpdateChannelRequest;
use App\Models\ActivityLog;
use App\Models\Channel;
use App\Models\ChannelAlias;
use App\Services\YouTubeService;
use App\Support\ChannelInput;
use App\Support\TermNormalizer;
use App\Support\XSearchKeywords;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $channel->load('aliases');

        return view('admin.channels.edit', compact('channel'));
    }

    public function update(UpdateChannelRequest $request, Channel $channel)
    {
        $channel->update([
            'color' => $request->color,
            'is_active' => $request->boolean('is_active'),
            'short_name' => trim((string) $request->input('short_name')) ?: null,
            'twitch_login' => $twitchLogin = $request->normalizedTwitchLogin(),
            // The cached Twitch user id belongs to the login; a new login is resolved on the next fetch.
            'twitch_user_id' => $twitchLogin === $channel->twitch_login ? $channel->twitch_user_id : null,
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

    public function addAlias(Request $request, Channel $channel): RedirectResponse
    {
        $validated = $request->validate(['alias' => 'required|string|max:80']);
        $alias = TermNormalizer::normalize($validated['alias']);
        // Member detection ignores tokens shorter than two characters, so such an alias would never match.
        if (mb_strlen($alias) < 2) {
            return redirect("/admin/channels/{$channel->id}/edit")->with('error', 'エイリアスは 2 文字以上にしてください。');
        }
        if ($existing = ChannelAlias::with('channel')->where('alias', $alias)->first()) {
            return redirect("/admin/channels/{$channel->id}/edit")->with('error', "「{$alias}」は既に「{$existing->channel->name}」のエイリアスです。");
        }
        $channel->aliases()->create(['alias' => $alias]);
        ActivityLog::record($request->user()->id, 'add_channel_alias', Channel::class, $channel->id, ['alias' => $alias]);

        return redirect("/admin/channels/{$channel->id}/edit")->with('success', "「{$alias}」を追加しました。");
    }

    public function destroyAlias(Request $request, ChannelAlias $channelAlias): RedirectResponse
    {
        $channelId = $channelAlias->channel_id;
        ActivityLog::record($request->user()->id, 'remove_channel_alias', Channel::class, $channelId, ['alias' => $channelAlias->alias]);
        $channelAlias->delete();

        return redirect("/admin/channels/{$channelId}/edit")->with('success', 'エイリアスを削除しました。');
    }
}
