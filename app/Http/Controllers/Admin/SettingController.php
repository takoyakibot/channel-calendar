<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\YouTubeService;
use App\Support\YouTubeApiKey;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    // The official "YouTube" channel — a stable id to probe the API key with.
    private const PROBE_CHANNEL_ID = 'UCBR8-60-B28hp2BmDPdntcQ';

    public function edit(YouTubeApiKey $apiKey)
    {
        return view('admin.settings.edit', [
            'maskedKey' => $apiKey->masked(),
            'keySource' => $apiKey->source(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'youtube_api_key' => 'nullable|string|max:255',
        ]);

        Setting::set(YouTubeApiKey::SETTING_KEY, trim((string) ($data['youtube_api_key'] ?? '')));

        return redirect('/admin/settings')->with('success', '設定を保存しました。');
    }

    public function test(YouTubeService $youtube)
    {
        try {
            $info = $youtube->getChannelInfo(self::PROBE_CHANNEL_ID);

            return redirect('/admin/settings')
                ->with('success', "接続テスト成功: チャンネル「{$info['name']}」を取得できました。");
        } catch (\Throwable $e) {
            return redirect('/admin/settings')
                ->with('error', '接続テスト失敗: ' . $e->getMessage());
        }
    }
}
