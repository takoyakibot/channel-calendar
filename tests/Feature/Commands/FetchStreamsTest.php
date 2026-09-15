<?php

namespace Tests\Feature\Commands;

use App\Models\Channel;
use App\Models\Setting;
use App\Models\Stream;
use App\Services\YouTubeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class FetchStreamsTest extends TestCase
{
    use RefreshDatabase;

    private function detail(string $id, array $overrides = []): array
    {
        return array_merge([
            'video_id' => $id,
            'title' => "Title {$id}",
            'thumbnail_url' => null,
            'scheduled_at' => now()->addDay()->toIso8601String(),
            'actual_start_at' => null,
            'actual_end_at' => null,
            'status' => 'upcoming',
        ], $overrides);
    }

    public function test_fetch_streams_creates_new_upcoming_stream_from_uploads(): void
    {
        $channel = Channel::factory()->create(['channel_id' => 'UC_test']);

        $mockService = Mockery::mock(YouTubeService::class);
        $mockService->shouldReceive('listRecentUploadIds')->with('UC_test')->andReturn(['vid1']);
        $mockService->shouldReceive('getVideoDetails')->with(['vid1'])->andReturn([
            $this->detail('vid1', ['title' => 'Stream 1 Full', 'thumbnail_url' => 'https://example.com/1.jpg']),
        ]);
        $this->app->instance(YouTubeService::class, $mockService);

        $this->artisan('streams:fetch')->assertSuccessful();

        $this->assertDatabaseHas('streams', [
            'video_id' => 'vid1',
            'channel_id' => $channel->id,
            'title' => 'Stream 1 Full',
            'thumbnail_url' => 'https://example.com/1.jpg',
            'status' => 'upcoming',
        ]);
    }

    public function test_fetch_streams_updates_existing_stream(): void
    {
        $channel = Channel::factory()->create(['channel_id' => 'UC_test']);
        Stream::factory()->create(['channel_id' => $channel->id, 'video_id' => 'vid1', 'title' => 'Old Title', 'status' => 'upcoming']);

        $mockService = Mockery::mock(YouTubeService::class);
        $mockService->shouldReceive('listRecentUploadIds')->with('UC_test')->andReturn(['vid1']);
        $mockService->shouldReceive('getVideoDetails')->with(['vid1'])->andReturn([
            $this->detail('vid1', [
                'title' => 'Now Live',
                'scheduled_at' => now()->subMinutes(5)->toIso8601String(),
                'actual_start_at' => now()->subMinutes(3)->toIso8601String(),
                'status' => 'live',
            ]),
        ]);
        $this->app->instance(YouTubeService::class, $mockService);

        $this->artisan('streams:fetch')->assertSuccessful();

        $this->assertDatabaseHas('streams', ['video_id' => 'vid1', 'title' => 'Now Live', 'status' => 'live']);
        $this->assertDatabaseCount('streams', 1);
    }

    public function test_fetch_streams_backfills_recent_completed_streams(): void
    {
        config(['services.youtube.backfill_days' => 14]);
        $channel = Channel::factory()->create(['channel_id' => 'UC_test']);

        $mockService = Mockery::mock(YouTubeService::class);
        $mockService->shouldReceive('listRecentUploadIds')->with('UC_test')->andReturn(['recent', 'ancient', 'plain_video']);
        $mockService->shouldReceive('getVideoDetails')->with(['recent', 'ancient', 'plain_video'])->andReturn([
            $this->detail('recent', [
                'scheduled_at' => now()->subDays(3)->toIso8601String(),
                'actual_start_at' => now()->subDays(3)->toIso8601String(),
                'actual_end_at' => now()->subDays(3)->addHours(2)->toIso8601String(),
                'status' => 'completed',
            ]),
            $this->detail('ancient', [
                'scheduled_at' => now()->subDays(40)->toIso8601String(),
                'actual_start_at' => now()->subDays(40)->toIso8601String(),
                'actual_end_at' => now()->subDays(40)->addHours(1)->toIso8601String(),
                'status' => 'completed',
            ]),
            $this->detail('plain_video', ['scheduled_at' => null, 'status' => 'upcoming']),
        ]);
        $this->app->instance(YouTubeService::class, $mockService);

        $this->artisan('streams:fetch')->assertSuccessful();

        $this->assertDatabaseHas('streams', ['video_id' => 'recent', 'status' => 'completed']);
        $this->assertDatabaseMissing('streams', ['video_id' => 'ancient']);
        $this->assertDatabaseMissing('streams', ['video_id' => 'plain_video']);
    }

    public function test_fetch_streams_skips_inactive_channels(): void
    {
        Channel::factory()->create(['channel_id' => 'UC_inactive', 'is_active' => false]);

        $mockService = Mockery::mock(YouTubeService::class);
        $mockService->shouldNotReceive('listRecentUploadIds');
        $this->app->instance(YouTubeService::class, $mockService);

        $this->artisan('streams:fetch')->assertSuccessful();
    }

    public function test_fetch_streams_marks_stale_upcoming_and_live_outside_window_as_completed(): void
    {
        config(['services.youtube.backfill_days' => 14]);
        $channel = Channel::factory()->create(['channel_id' => 'UC_test']);
        Stream::factory()->create(['channel_id' => $channel->id, 'video_id' => 'vid_old', 'status' => 'upcoming', 'scheduled_at' => now()->subDays(20)]);
        Stream::factory()->create(['channel_id' => $channel->id, 'video_id' => 'vid_old_live', 'status' => 'live', 'scheduled_at' => now()->subDays(20)]);

        $mockService = Mockery::mock(YouTubeService::class);
        $mockService->shouldReceive('listRecentUploadIds')->andReturn([]);
        $mockService->shouldNotReceive('getVideoDetails');
        $this->app->instance(YouTubeService::class, $mockService);

        $this->artisan('streams:fetch')->assertSuccessful();

        $this->assertDatabaseHas('streams', ['video_id' => 'vid_old', 'status' => 'completed']);
        $this->assertDatabaseHas('streams', ['video_id' => 'vid_old_live', 'status' => 'completed']);
    }

    public function test_fetch_streams_deletes_upcoming_stream_removed_from_youtube(): void
    {
        $channel = Channel::factory()->create(['channel_id' => 'UC_test']);
        Stream::factory()->create(['channel_id' => $channel->id, 'video_id' => 'vid_cancelled', 'status' => 'upcoming', 'scheduled_at' => now()->addDays(2)]);

        $mockService = Mockery::mock(YouTubeService::class);
        $mockService->shouldReceive('listRecentUploadIds')->with('UC_test')->andReturn([]);
        $mockService->shouldReceive('getVideoDetails')->once()->with(['vid_cancelled'])->andReturn([]);
        $this->app->instance(YouTubeService::class, $mockService);

        $this->artisan('streams:fetch')->assertSuccessful();

        $this->assertDatabaseMissing('streams', ['video_id' => 'vid_cancelled']);
    }

    public function test_fetch_streams_deletes_recent_completed_stream_removed_from_youtube(): void
    {
        config(['services.youtube.backfill_days' => 14]);
        $channel = Channel::factory()->create(['channel_id' => 'UC_test']);
        Stream::factory()->create(['channel_id' => $channel->id, 'video_id' => 'vid_privated', 'status' => 'completed', 'scheduled_at' => now()->subDays(3)]);

        $mockService = Mockery::mock(YouTubeService::class);
        $mockService->shouldReceive('listRecentUploadIds')->with('UC_test')->andReturn([]);
        $mockService->shouldReceive('getVideoDetails')->once()->with(['vid_privated'])->andReturn([]);
        $this->app->instance(YouTubeService::class, $mockService);

        $this->artisan('streams:fetch')->assertSuccessful();

        $this->assertDatabaseMissing('streams', ['video_id' => 'vid_privated']);
    }

    public function test_fetch_streams_keeps_and_refreshes_stream_that_fell_off_the_uploads_list(): void
    {
        config(['services.youtube.backfill_days' => 14]);
        $channel = Channel::factory()->create(['channel_id' => 'UC_test']);
        Stream::factory()->create(['channel_id' => $channel->id, 'video_id' => 'vid_buried', 'title' => 'Old Title', 'status' => 'upcoming', 'scheduled_at' => now()->subDays(5)]);

        $mockService = Mockery::mock(YouTubeService::class);
        $mockService->shouldReceive('listRecentUploadIds')->with('UC_test')->andReturn([]);
        $mockService->shouldReceive('getVideoDetails')->once()->with(['vid_buried'])->andReturn([
            $this->detail('vid_buried', [
                'title' => 'Still Here',
                'scheduled_at' => now()->subDays(5)->toIso8601String(),
                'actual_start_at' => now()->subDays(5)->toIso8601String(),
                'actual_end_at' => now()->subDays(5)->addHour()->toIso8601String(),
                'status' => 'completed',
            ]),
        ]);
        $this->app->instance(YouTubeService::class, $mockService);

        $this->artisan('streams:fetch')->assertSuccessful();

        $this->assertDatabaseHas('streams', ['video_id' => 'vid_buried', 'title' => 'Still Here', 'status' => 'completed']);
    }

    public function test_fetch_streams_does_not_verify_or_delete_history_outside_window(): void
    {
        config(['services.youtube.backfill_days' => 14]);
        $channel = Channel::factory()->create(['channel_id' => 'UC_test']);
        Stream::factory()->create(['channel_id' => $channel->id, 'video_id' => 'vid_ancient', 'status' => 'completed', 'scheduled_at' => now()->subDays(40)]);

        $mockService = Mockery::mock(YouTubeService::class);
        $mockService->shouldReceive('listRecentUploadIds')->with('UC_test')->andReturn([]);
        $mockService->shouldNotReceive('getVideoDetails');
        $this->app->instance(YouTubeService::class, $mockService);

        $this->artisan('streams:fetch')->assertSuccessful();

        $this->assertDatabaseHas('streams', ['video_id' => 'vid_ancient', 'status' => 'completed']);
    }

    public function test_fetch_streams_does_not_complete_old_stream_returned_this_run(): void
    {
        $oldScheduledAt = now()->subHours(25);
        $channel = Channel::factory()->create(['channel_id' => 'UC_test']);
        Stream::factory()->create(['channel_id' => $channel->id, 'video_id' => 'vid_delayed', 'status' => 'upcoming', 'scheduled_at' => $oldScheduledAt]);

        $mockService = Mockery::mock(YouTubeService::class);
        $mockService->shouldReceive('listRecentUploadIds')->with('UC_test')->andReturn(['vid_delayed']);
        $mockService->shouldReceive('getVideoDetails')->with(['vid_delayed'])->andReturn([
            $this->detail('vid_delayed', ['scheduled_at' => $oldScheduledAt->toIso8601String()]),
        ]);
        $this->app->instance(YouTubeService::class, $mockService);

        $this->artisan('streams:fetch')->assertSuccessful();

        $this->assertDatabaseHas('streams', ['video_id' => 'vid_delayed', 'status' => 'upcoming']);
    }

    public function test_fetch_streams_chunks_video_ids_by_fifty(): void
    {
        Channel::factory()->create(['channel_id' => 'UC_test']);
        $ids = array_map(fn ($i) => "v{$i}", range(1, 60));

        $mockService = Mockery::mock(YouTubeService::class);
        $mockService->shouldReceive('listRecentUploadIds')->with('UC_test')->andReturn($ids);
        $mockService->shouldReceive('getVideoDetails')->once()->with(Mockery::on(fn ($c) => count($c) === 50))->andReturn([]);
        $mockService->shouldReceive('getVideoDetails')->once()->with(Mockery::on(fn ($c) => count($c) === 10))->andReturn([]);
        $this->app->instance(YouTubeService::class, $mockService);

        $this->artisan('streams:fetch')->assertSuccessful();
    }

    public function test_fetch_streams_does_not_sweep_streams_of_channel_whose_fetch_failed(): void
    {
        Log::spy();
        $channel = Channel::factory()->create(['channel_id' => 'UC_failing']);
        Stream::factory()->create(['channel_id' => $channel->id, 'video_id' => 'vid_untouched', 'status' => 'upcoming', 'scheduled_at' => now()->subHours(25)]);

        $mockService = Mockery::mock(YouTubeService::class);
        $mockService->shouldReceive('listRecentUploadIds')->with('UC_failing')->andThrow(new \RuntimeException('quota'));
        $mockService->shouldNotReceive('getVideoDetails');
        $this->app->instance(YouTubeService::class, $mockService);

        $this->artisan('streams:fetch')->assertSuccessful();

        $this->assertDatabaseHas('streams', ['video_id' => 'vid_untouched', 'status' => 'upcoming']);
        Log::shouldHaveReceived('error')->once();
    }

    public function test_fetch_streams_records_last_fetched_at(): void
    {
        Channel::factory()->create(['channel_id' => 'UC_test']);

        $mockService = Mockery::mock(YouTubeService::class);
        $mockService->shouldReceive('listRecentUploadIds')->andReturn([]);
        $this->app->instance(YouTubeService::class, $mockService);

        $this->artisan('streams:fetch')->assertSuccessful();

        $this->assertNotNull(Setting::get('streams_last_fetched_at'));
    }
}
