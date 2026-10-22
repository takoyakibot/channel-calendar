<?php

namespace Tests\Feature\Commands;

use App\Models\VideoPost;
use App\Services\YouTubeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class RedateVideoPostsTest extends TestCase
{
    use RefreshDatabase;

    private function videoPost(string $videoId, string $publishedAt): VideoPost
    {
        return VideoPost::create(['video_id' => $videoId, 'kind' => 'guest', 'title' => 'コラボ', 'source_channel_id' => 'UC_host', 'source_channel_name' => 'ホスト', 'published_at' => $publishedAt]);
    }

    private function mockInfo(array $publishedAtById): void
    {
        $mock = Mockery::mock(YouTubeService::class);
        foreach ($publishedAtById as $id => $at) {
            $mock->shouldReceive('getVideoInfo')->with($id)->andReturn($at === null ? null : ['published_at' => $at]);
        }
        $this->app->instance(YouTubeService::class, $mock);
    }

    public function test_it_moves_a_past_broadcast_to_when_it_aired(): void
    {
        $collab = $this->videoPost('r9N79JmTW7g', '2026-10-18 01:57:11');
        $clip = $this->videoPost('clip0000001', '2026-10-01 10:00:00');
        $gone = $this->videoPost('deleted0001', '2026-10-02 10:00:00');
        $this->mockInfo([
            'r9N79JmTW7g' => '2026-10-17T12:01:34Z',
            'clip0000001' => '2026-10-01T10:00:00Z',
            'deleted0001' => null,
        ]);

        $this->artisan('video-posts:redate')->assertSuccessful();

        $this->assertSame('2026-10-17 12:01:34', $collab->fresh()->published_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-01 10:00:00', $clip->fresh()->published_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-02 10:00:00', $gone->fresh()->published_at->format('Y-m-d H:i:s'));
    }

    public function test_dry_run_changes_nothing(): void
    {
        $collab = $this->videoPost('r9N79JmTW7g', '2026-10-18 01:57:11');
        $this->mockInfo(['r9N79JmTW7g' => '2026-10-17T12:01:34Z']);

        $this->artisan('video-posts:redate', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame('2026-10-18 01:57:11', $collab->fresh()->published_at->format('Y-m-d H:i:s'));
    }
}
