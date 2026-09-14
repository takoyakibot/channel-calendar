<?php

namespace Tests\Feature\Commands;

use App\Models\Channel;
use App\Models\Stream;
use App\Services\YouTubeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class FetchStreamsTest extends TestCase
{
    use RefreshDatabase;

    public function test_fetch_streams_creates_new_streams(): void
    {
        $channel = Channel::factory()->create(['channel_id' => 'UC_test']);

        $mockService = Mockery::mock(YouTubeService::class);
        $mockService->shouldReceive('searchStreams')
            ->with('UC_test', 'upcoming')
            ->andReturn([
                ['video_id' => 'vid1', 'title' => 'Stream 1', 'thumbnail_url' => 'https://example.com/1.jpg'],
            ]);
        $mockService->shouldReceive('searchStreams')
            ->with('UC_test', 'live')
            ->andReturn([]);
        $mockService->shouldReceive('getVideoDetails')
            ->with(['vid1'])
            ->andReturn([
                [
                    'video_id' => 'vid1',
                    'title' => 'Stream 1 Full',
                    'scheduled_at' => '2026-09-15T19:00:00Z',
                    'actual_start_at' => null,
                    'actual_end_at' => null,
                    'status' => 'upcoming',
                ],
            ]);

        $this->app->instance(YouTubeService::class, $mockService);

        $this->artisan('streams:fetch')->assertSuccessful();

        $this->assertDatabaseHas('streams', [
            'video_id' => 'vid1',
            'title' => 'Stream 1 Full',
            'status' => 'upcoming',
        ]);
    }

    public function test_fetch_streams_updates_existing_stream(): void
    {
        $channel = Channel::factory()->create(['channel_id' => 'UC_test']);
        Stream::factory()->create([
            'channel_id' => $channel->id,
            'video_id' => 'vid1',
            'title' => 'Old Title',
            'status' => 'upcoming',
        ]);

        $mockService = Mockery::mock(YouTubeService::class);
        $mockService->shouldReceive('searchStreams')
            ->with('UC_test', 'upcoming')
            ->andReturn([]);
        $mockService->shouldReceive('searchStreams')
            ->with('UC_test', 'live')
            ->andReturn([
                ['video_id' => 'vid1', 'title' => 'Now Live', 'thumbnail_url' => 'https://example.com/1.jpg'],
            ]);
        $mockService->shouldReceive('getVideoDetails')
            ->with(['vid1'])
            ->andReturn([
                [
                    'video_id' => 'vid1',
                    'title' => 'Now Live',
                    'scheduled_at' => '2026-09-15T19:00:00Z',
                    'actual_start_at' => '2026-09-15T19:02:00Z',
                    'actual_end_at' => null,
                    'status' => 'live',
                ],
            ]);

        $this->app->instance(YouTubeService::class, $mockService);

        $this->artisan('streams:fetch')->assertSuccessful();

        $this->assertDatabaseHas('streams', [
            'video_id' => 'vid1',
            'title' => 'Now Live',
            'status' => 'live',
        ]);
        $this->assertDatabaseCount('streams', 1);
    }

    public function test_fetch_streams_skips_inactive_channels(): void
    {
        Channel::factory()->create(['channel_id' => 'UC_inactive', 'is_active' => false]);

        $mockService = Mockery::mock(YouTubeService::class);
        $mockService->shouldNotReceive('searchStreams');

        $this->app->instance(YouTubeService::class, $mockService);

        $this->artisan('streams:fetch')->assertSuccessful();
    }

    public function test_fetch_streams_marks_old_upcoming_as_completed(): void
    {
        $channel = Channel::factory()->create(['channel_id' => 'UC_test']);
        Stream::factory()->create([
            'channel_id' => $channel->id,
            'video_id' => 'vid_old',
            'status' => 'upcoming',
            'scheduled_at' => now()->subHours(25),
        ]);

        $mockService = Mockery::mock(YouTubeService::class);
        $mockService->shouldReceive('searchStreams')->andReturn([]);
        $mockService->shouldReceive('getVideoDetails')->andReturn([]);

        $this->app->instance(YouTubeService::class, $mockService);

        $this->artisan('streams:fetch')->assertSuccessful();

        $this->assertDatabaseHas('streams', [
            'video_id' => 'vid_old',
            'status' => 'completed',
        ]);
    }

    public function test_fetch_streams_marks_old_live_stream_not_returned_as_completed(): void
    {
        $channel = Channel::factory()->create(['channel_id' => 'UC_test']);
        Stream::factory()->create([
            'channel_id' => $channel->id,
            'video_id' => 'vid_old_live',
            'status' => 'live',
            'scheduled_at' => now()->subHours(25),
        ]);

        $mockService = Mockery::mock(YouTubeService::class);
        $mockService->shouldReceive('searchStreams')->andReturn([]);
        $mockService->shouldReceive('getVideoDetails')->andReturn([]);

        $this->app->instance(YouTubeService::class, $mockService);

        $this->artisan('streams:fetch')->assertSuccessful();

        $this->assertDatabaseHas('streams', [
            'video_id' => 'vid_old_live',
            'status' => 'completed',
        ]);
    }

    public function test_fetch_streams_does_not_complete_old_stream_returned_this_run(): void
    {
        $oldScheduledAt = now()->subHours(25);

        $channel = Channel::factory()->create(['channel_id' => 'UC_test']);
        Stream::factory()->create([
            'channel_id' => $channel->id,
            'video_id' => 'vid_delayed',
            'status' => 'upcoming',
            'scheduled_at' => $oldScheduledAt,
        ]);

        $mockService = Mockery::mock(YouTubeService::class);
        $mockService->shouldReceive('searchStreams')
            ->with('UC_test', 'upcoming')
            ->andReturn([
                ['video_id' => 'vid_delayed', 'title' => 'Delayed Stream', 'thumbnail_url' => null],
            ]);
        $mockService->shouldReceive('searchStreams')
            ->with('UC_test', 'live')
            ->andReturn([]);
        $mockService->shouldReceive('getVideoDetails')
            ->with(['vid_delayed'])
            ->andReturn([
                [
                    'video_id' => 'vid_delayed',
                    'title' => 'Delayed Stream',
                    'scheduled_at' => $oldScheduledAt->toIso8601String(),
                    'actual_start_at' => null,
                    'actual_end_at' => null,
                    'status' => 'upcoming',
                ],
            ]);

        $this->app->instance(YouTubeService::class, $mockService);

        $this->artisan('streams:fetch')->assertSuccessful();

        $this->assertDatabaseHas('streams', [
            'video_id' => 'vid_delayed',
            'status' => 'upcoming',
        ]);
    }

    public function test_fetch_streams_does_not_sweep_streams_of_channel_whose_fetch_failed(): void
    {
        Log::spy();

        $channel = Channel::factory()->create(['channel_id' => 'UC_failing']);
        Stream::factory()->create([
            'channel_id' => $channel->id,
            'video_id' => 'vid_untouched',
            'status' => 'upcoming',
            'scheduled_at' => now()->subHours(25),
        ]);

        $mockService = Mockery::mock(YouTubeService::class);
        $mockService->shouldReceive('searchStreams')
            ->with('UC_failing', 'upcoming')
            ->andThrow(new \RuntimeException('quota'));

        $this->app->instance(YouTubeService::class, $mockService);

        $this->artisan('streams:fetch')->assertSuccessful();

        $this->assertDatabaseHas('streams', [
            'video_id' => 'vid_untouched',
            'status' => 'upcoming',
        ]);

        Log::shouldHaveReceived('error')->once();
    }
}
