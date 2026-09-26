<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\VideoPost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VideoPostController extends Controller
{
    public function index(): View
    {
        $posts = VideoPost::with('channels', 'submitter')->latest('published_at')->paginate(50);

        return view('admin.video-posts.index', compact('posts'));
    }

    public function destroy(Request $request, VideoPost $videoPost): RedirectResponse
    {
        ActivityLog::record($request->user()->id, 'delete_video_post', VideoPost::class, $videoPost->id, [
            'video_id' => $videoPost->video_id,
            'kind' => $videoPost->kind,
            'title' => $videoPost->title,
            'submitted_by_user_id' => $videoPost->submitted_by_user_id,
        ]);

        $videoPost->delete();

        return redirect('/admin/video-posts')->with('success', '削除しました。');
    }
}
