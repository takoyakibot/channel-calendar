<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Artisan;

class FetchController extends Controller
{
    public function fetch()
    {
        Artisan::call('streams:fetch');
        $output = trim(Artisan::output());

        $failed = substr_count($output, 'Failed for');
        $message = $failed > 0
            ? "配信予定を取得しました（{$failed} チャンネルで失敗。詳細はログを確認してください）。"
            : '配信予定を取得しました。';

        return redirect('/admin/channels')->with($failed > 0 ? 'error' : 'success', $message);
    }
}
