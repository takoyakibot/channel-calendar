<?php

namespace Tests\Feature\Commands;

use App\Models\Channel;
use App\Models\Stream;
use App\Models\Tag;
use App\Services\YouTubeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class BackfillAndRefreshStreamsTest extends TestCase
{
    use RefreshDatabase;

    private function detail(string $id, array $over = []): array
    {
        return array_merge([
            'video_id' => $id, 'title' => "video {$id}", 'thumbnail_url' => null,
            'is_broadcast' => false, 'scheduled_at' => null, 'actual_start_at' => null, 'actual_end_at' => null,
            'status' => 'completed', 'published_at' => '2019-05-01T12:00:00Z', 'duration_seconds' => 600,
        ], $over);
    }

    private function details(array $ids): array
    {
        return array_map(fn ($id) => $this->detail($id), $ids);
    }

    public function test_backfill_walks_all_pages_of_both_playlists_and_keeps_old_videos(): void
    {
        $channel = Channel::factory()->create(['channel_id' => 'UC_old']);
        Tag::createWithAlias('Minecraft', 'game');

        $yt = Mockery::mock(YouTubeService::class);
        $yt->shouldReceive('listUploadIdsPage')->with('UC_old', false, null)->once()->andReturn(['ids' => ['a1', 'a2'], 'next' => 'P2']);
        $yt->shouldReceive('listUploadIdsPage')->with('UC_old', false, 'P2')->once()->andReturn(['ids' => ['a3'], 'next' => null]);
        $yt->shouldReceive('listUploadIdsPage')->with('UC_old', true, null)->once()->andReturn(['ids' => ['m1'], 'next' => null]);
        $yt->shouldReceive('getVideoDetails')->with(['a1', 'a2'])->andReturn([
            $this->detail('a1', ['title' => '【Minecraft】昔の配信', 'is_broadcast' => true, 'scheduled_at' => '2018-01-01T10:00:00Z', 'actual_start_at' => '2018-01-01T10:00:00Z', 'actual_end_at' => '2018-01-01T12:00:00Z']),
            $this->detail('a2', ['duration_seconds' => 30]),
        ]);
        $yt->shouldReceive('getVideoDetails')->with(['a3'])->andReturn($this->details(['a3']));
        $yt->shouldReceive('getVideoDetails')->with(['m1'])->andReturn($this->details(['m1']));
        $this->app->instance(YouTubeService::class, $yt);

        $this->artisan('streams:backfill')->assertSuccessful();

        $this->assertSame(['a1' => 'stream', 'a2' => 'short', 'a3' => 'upload', 'm1' => 'upload'], Stream::orderBy('video_id')->pluck('type', 'video_id')->all());
        $this->assertTrue(Stream::where('video_id', 'm1')->value('is_members_only'));
        $this->assertNotNull(Stream::where('video_id', 'a1')->value('verified_at'));
        $this->assertSame(['Minecraft'], Stream::where('video_id', 'a1')->first()->tags->pluck('name')->all());
        $this->assertNotNull($channel->fresh()->backfill_completed_at);

        // Finished channels are skipped on later runs (the mock allows each page only once).
        $this->artisan('streams:backfill')->assertSuccessful();
    }

    public function test_backfill_stops_at_the_page_budget_and_resumes_from_the_saved_token(): void
    {
        $channel = Channel::factory()->create(['channel_id' => 'UC_big']);

        $yt = Mockery::mock(YouTubeService::class);
        $yt->shouldReceive('listUploadIdsPage')->with('UC_big', false, null)->once()->andReturn(['ids' => ['b1'], 'next' => 'P2']);
        $yt->shouldReceive('listUploadIdsPage')->with('UC_big', false, 'P2')->once()->andReturn(['ids' => ['b2'], 'next' => null]);
        $yt->shouldReceive('listUploadIdsPage')->with('UC_big', true, null)->once()->andReturn(['ids' => [], 'next' => null]);
        $yt->shouldReceive('getVideoDetails')->andReturnUsing(fn ($ids) => $this->details($ids));
        $this->app->instance(YouTubeService::class, $yt);

        $this->artisan('streams:backfill --pages=1')->assertSuccessful();
        $this->assertSame(['b1'], Stream::pluck('video_id')->all());
        $this->assertSame(['uploads' => ['token' => 'P2']], $channel->fresh()->backfill_state);
        $this->assertNull($channel->fresh()->backfill_completed_at);

        $this->artisan('streams:backfill --pages=5')->assertSuccessful();
        $this->assertSame(['b1', 'b2'], Stream::orderBy('video_id')->pluck('video_id')->all());
        $this->assertNotNull($channel->fresh()->backfill_completed_at);
    }

    public function test_backfill_reset_requires_channel_ids(): void
    {
        $this->app->instance(YouTubeService::class, Mockery::mock(YouTubeService::class));
        $this->artisan('streams:backfill --reset')->assertFailed();
    }

    public function test_backfill_keeps_progress_when_the_api_fails_midway(): void
    {
        $channel = Channel::factory()->create(['channel_id' => 'UC_err']);

        $yt = Mockery::mock(YouTubeService::class);
        $yt->shouldReceive('listUploadIdsPage')->with('UC_err', false, null)->andReturn(['ids' => ['e1'], 'next' => 'P2']);
        $yt->shouldReceive('listUploadIdsPage')->with('UC_err', false, 'P2')->andThrow(new \RuntimeException('quotaExceeded'));
        $yt->shouldReceive('getVideoDetails')->andReturnUsing(fn ($ids) => $this->details($ids));
        $this->app->instance(YouTubeService::class, $yt);

        $this->artisan('streams:backfill')->assertSuccessful();
        $this->assertSame(['e1'], Stream::pluck('video_id')->all());
        $this->assertSame(['uploads' => ['token' => 'P2']], $channel->fresh()->backfill_state);
    }

    public function test_refresh_updates_the_stalest_streams_and_deletes_ones_youtube_no_longer_serves(): void
    {
        config(['services.youtube.refresh_after_days' => 20]);
        $channel = Channel::factory()->create();
        $fresh = Stream::factory()->create(['channel_id' => $channel->id, 'video_id' => 'fresh', 'verified_at' => now()->subDays(1)]);
        $never = Stream::factory()->create(['channel_id' => $channel->id, 'video_id' => 'never', 'title' => '古いタイトル', 'verified_at' => null]);
        $stale = Stream::factory()->create(['channel_id' => $channel->id, 'video_id' => 'stale', 'verified_at' => now()->subDays(25)]);
        Stream::factory()->create(['channel_id' => $channel->id, 'video_id' => 'tw1', 'platform' => 'twitch', 'verified_at' => null]);

        $yt = Mockery::mock(YouTubeService::class);
        $yt->shouldReceive('getVideoDetails')->once()->with(['never', 'stale'])
            ->andReturn([$this->detail('never', ['title' => '新しいタイトル'])]);
        $this->app->instance(YouTubeService::class, $yt);

        $this->artisan('streams:refresh')->assertSuccessful();

        $this->assertSame('新しいタイトル', $never->fresh()->title);
        $this->assertTrue($never->fresh()->verified_at->gt(now()->subMinute()));
        $this->assertNull($stale->fresh());
        $this->assertNotNull($fresh->fresh());
        $this->assertSame(1, Stream::where('platform', 'twitch')->count());
    }

    public function test_refresh_respects_the_limit_oldest_first(): void
    {
        $channel = Channel::factory()->create();
        Stream::factory()->create(['channel_id' => $channel->id, 'video_id' => 'v30', 'verified_at' => now()->subDays(30)]);
        Stream::factory()->create(['channel_id' => $channel->id, 'video_id' => 'v40', 'verified_at' => now()->subDays(40)]);

        $yt = Mockery::mock(YouTubeService::class);
        $yt->shouldReceive('getVideoDetails')->once()->with(['v40'])->andReturn($this->details(['v40']));
        $this->app->instance(YouTubeService::class, $yt);

        $this->artisan('streams:refresh --limit=1')->assertSuccessful();
    }

    public function test_refresh_deletes_nothing_when_the_api_fails_or_most_of_a_batch_vanishes(): void
    {
        $channel = Channel::factory()->create();
        foreach (range(1, 30) as $i) {
            Stream::factory()->create(['channel_id' => $channel->id, 'video_id' => "x{$i}", 'verified_at' => null]);
        }

        $yt = Mockery::mock(YouTubeService::class);
        $yt->shouldReceive('getVideoDetails')->once()->andThrow(new \RuntimeException('backendError'));
        $this->app->instance(YouTubeService::class, $yt);
        $this->artisan('streams:refresh')->assertFailed();
        $this->assertSame(30, Stream::count());

        $yt = Mockery::mock(YouTubeService::class);
        $yt->shouldReceive('getVideoDetails')->andReturn([]);
        $this->app->instance(YouTubeService::class, $yt);
        $this->artisan('streams:refresh')->assertSuccessful();
        $this->assertSame(30, Stream::count());
    }

    public function test_regular_fetch_marks_streams_verified(): void
    {
        $channel = Channel::factory()->create(['channel_id' => 'UC_reg']);
        $yt = Mockery::mock(YouTubeService::class);
        $yt->shouldReceive('listRecentUploadIds')->andReturn(['r1']);
        $yt->shouldReceive('listMembersOnlyUploadIds')->andReturn([]);
        $yt->shouldReceive('getVideoDetails')->andReturn([$this->detail('r1', ['published_at' => now()->subDay()->toIso8601String()])]);
        $this->app->instance(YouTubeService::class, $yt);

        $this->artisan('streams:fetch')->assertSuccessful();
        $this->assertNotNull(Stream::where('video_id', 'r1')->value('verified_at'));
    }
}
