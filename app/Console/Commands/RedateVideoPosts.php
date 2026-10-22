<?php

namespace App\Console\Commands;

use App\Models\VideoPost;
use App\Services\YouTubeService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * One-off fix for posts registered before getVideoInfo dated broadcasts by when they aired:
 * those were dated by the archive's publish time, often the next day. 1 quota unit per post.
 */
class RedateVideoPosts extends Command
{
    protected $signature = 'video-posts:redate {--dry-run : Show what would change without saving}';

    protected $description = 'Re-date registered video posts from YouTube (a past broadcast moves to when it aired)';

    public function handle(YouTubeService $youtube): int
    {
        $changed = 0;
        foreach (VideoPost::orderBy('id')->get() as $post) {
            $info = $youtube->getVideoInfo($post->video_id);
            if ($info === null || ! $info['published_at']) {
                $this->warn("skip {$post->video_id}: not found on YouTube");
                continue;
            }

            $at = Carbon::parse($info['published_at'])->utc();
            if ($at->equalTo($post->published_at)) {
                continue;
            }

            $this->line("{$post->video_id}: {$post->published_at->setTimezone('Asia/Tokyo')->format('Y-m-d H:i')} → {$at->copy()->setTimezone('Asia/Tokyo')->format('Y-m-d H:i')} (JST)  {$post->title}");
            if (! $this->option('dry-run')) {
                $post->update(['published_at' => $at]);
            }
            $changed++;
        }

        $this->info(($this->option('dry-run') ? 'Would re-date' : 'Re-dated') . " {$changed} post(s).");

        return self::SUCCESS;
    }
}
