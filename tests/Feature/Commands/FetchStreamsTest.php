<?php

namespace Tests\Feature\Commands;

use App\Models\Channel;
use App\Models\Setting;
use App\Models\Stream;
use App\Models\Tag;
use App\Services\YouTubeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class FetchStreamsTest extends TestCase
{
    use RefreshDatabase;

    /** YouTubeService mock with no members-only videos unless a test says otherwise. */
    private function mockYouTube(): YouTubeService
    {
        $mock = Mockery::mock(YouTubeService::class);
        $mock->shouldReceive('listMembersOnlyUploadIds')->byDefault()->andReturn([]);

        return $mock;
    }

    public function test_all_day_manual_schedule_is_removed_once_a_real_stream_exists_that_day(): void
    {
        $channel = Channel::factory()->create(['channel_id' => 'UC_test']);
        $user = \App\Models\User::factory()->create();
        $dayStart = now('Asia/Tokyo')->addDays(2)->startOfDay()->utc();
        $manual = \App\Models\ManualSchedule::create([
            'user_id' => $user->id, 'channel_id' => $channel->id, 'title' => '未定',
            'scheduled_at' => $dayStart, 'is_all_day' => true,
        ]);
        $otherDay = \App\Models\ManualSchedule::create([
            'user_id' => $user->id, 'channel_id' => $channel->id, 'title' => '別の日',
            'scheduled_at' => $dayStart->copy()->addDays(3), 'is_all_day' => true,
        ]);

        $mockService = $this->mockYouTube();
        $mockService->shouldReceive('listRecentUploadIds')->with('UC_test')->andReturn(['real1']);
        $mockService->shouldReceive('getVideoDetails')->with(['real1'])->andReturn([
            $this->detail('real1', ['scheduled_at' => $dayStart->copy()->addHours(20)->toIso8601String()]),
        ]);
        $this->app->instance(YouTubeService::class, $mockService);

        $this->artisan('streams:fetch')->assertSuccessful();

        $this->assertDatabaseMissing('manual_schedules', ['id' => $manual->id]);
        $this->assertDatabaseHas('manual_schedules', ['id' => $otherDay->id]);
    }

    public function test_all_day_manual_schedule_is_kept_during_its_day_and_removed_afterwards(): void
    {
        $channel = Channel::factory()->create(['channel_id' => 'UC_test']);
        $user = \App\Models\User::factory()->create();
        $today = \App\Models\ManualSchedule::create([
            'user_id' => $user->id, 'channel_id' => $channel->id, 'title' => '今日',
            'scheduled_at' => now('Asia/Tokyo')->startOfDay()->utc(), 'is_all_day' => true,
        ]);
        $yesterday = \App\Models\ManualSchedule::create([
            'user_id' => $user->id, 'channel_id' => $channel->id, 'title' => '昨日',
            'scheduled_at' => now('Asia/Tokyo')->subDay()->startOfDay()->utc(), 'is_all_day' => true,
        ]);

        $mockService = $this->mockYouTube();
        $mockService->shouldReceive('listRecentUploadIds')->andReturn([]);
        $this->app->instance(YouTubeService::class, $mockService);

        $this->artisan('streams:fetch')->assertSuccessful();

        $this->assertDatabaseHas('manual_schedules', ['id' => $today->id]);
        $this->assertDatabaseMissing('manual_schedules', ['id' => $yesterday->id]);
    }

    public function test_timed_manual_schedule_is_kept_for_three_hours_after_its_time(): void
    {
        $channel = Channel::factory()->create(['channel_id' => 'UC_test']);
        $user = \App\Models\User::factory()->create();
        $late = \App\Models\ManualSchedule::create([
            'user_id' => $user->id, 'channel_id' => $channel->id, 'title' => '遅刻中',
            'scheduled_at' => now()->subHours(2), 'is_all_day' => false,
        ]);
        $stale = \App\Models\ManualSchedule::create([
            'user_id' => $user->id, 'channel_id' => $channel->id, 'title' => '流れた',
            'scheduled_at' => now()->subHours(4), 'is_all_day' => false,
        ]);

        $mockService = $this->mockYouTube();
        $mockService->shouldReceive('listRecentUploadIds')->andReturn([]);
        $this->app->instance(YouTubeService::class, $mockService);

        $this->artisan('streams:fetch')->assertSuccessful();

        $this->assertDatabaseHas('manual_schedules', ['id' => $late->id]);
        $this->assertDatabaseMissing('manual_schedules', ['id' => $stale->id]);
    }

    public function test_watched_fetch_only_targets_channels_with_a_manual_schedule_in_its_watch_window(): void
    {
        $user = \App\Models\User::factory()->create();
        $make = function (string $suffix, ?\Carbon\CarbonInterface $at, bool $allDay = false) use ($user): Channel {
            $channel = Channel::factory()->create(['channel_id' => "UC_{$suffix}"]);
            if ($at !== null) {
                \App\Models\ManualSchedule::create([
                    'user_id' => $user->id, 'channel_id' => $channel->id, 'title' => $suffix,
                    'scheduled_at' => $at, 'is_all_day' => $allDay,
                ]);
            }

            return $channel;
        };
        $todayJst = now('Asia/Tokyo')->startOfDay()->utc();

        $make('soon', now()->addMinutes(10));                // 30 min before … → watched
        $make('later', now()->addHours(2));                   // too far ahead
        $make('running_late', now()->subHours(2));            // … 3 h after → watched
        $make('long_gone', now()->subHours(4));               // expired
        $make('allday_today', $todayJst, true);               // whole day → watched
        $make('allday_tomorrow', $todayJst->copy()->addDay(), true);
        $make('no_manual', null);

        $mockService = $this->mockYouTube();
        foreach (['UC_soon', 'UC_running_late', 'UC_allday_today'] as $id) {
            $mockService->shouldReceive('listRecentUploadIds')->once()->with($id)->andReturn([]);
        }
        foreach (['UC_later', 'UC_long_gone', 'UC_allday_tomorrow', 'UC_no_manual'] as $id) {
            $mockService->shouldReceive('listRecentUploadIds')->never()->with($id);
        }
        $this->app->instance(YouTubeService::class, $mockService);

        $this->artisan('streams:fetch', ['--watched' => true])->assertSuccessful();
    }

    public function test_watched_fetch_makes_no_api_calls_when_nothing_is_in_a_watch_window(): void
    {
        Channel::factory()->create(['channel_id' => 'UC_test']);

        $mockService = $this->mockYouTube();
        $mockService->shouldNotReceive('listRecentUploadIds');
        $this->app->instance(YouTubeService::class, $mockService);

        $this->artisan('streams:fetch', ['--watched' => true])->assertSuccessful();
    }

    public function test_watched_fetch_replaces_manual_schedule_with_the_real_stream_without_recording_a_full_fetch(): void
    {
        $channel = Channel::factory()->create(['channel_id' => 'UC_test']);
        $user = \App\Models\User::factory()->create();
        $at = now()->addMinutes(10)->startOfSecond();
        $manual = \App\Models\ManualSchedule::create([
            'user_id' => $user->id, 'channel_id' => $channel->id, 'title' => '未定',
            'scheduled_at' => $at, 'is_all_day' => false,
        ]);
        // Outside the reconcile window: only the age sweep would touch it.
        $oldUpcoming = Stream::factory()->create([
            'channel_id' => $channel->id, 'video_id' => 'old_upcoming', 'status' => 'upcoming', 'scheduled_at' => now()->subDays(20),
        ]);

        $mockService = $this->mockYouTube();
        $mockService->shouldReceive('listRecentUploadIds')->with('UC_test')->andReturn(['real1']);
        $mockService->shouldReceive('getVideoDetails')->andReturn([
            $this->detail('real1', ['scheduled_at' => $at->toIso8601String()]),
        ]);
        $this->app->instance(YouTubeService::class, $mockService);

        $this->artisan('streams:fetch', ['--watched' => true])->assertSuccessful();

        $this->assertDatabaseHas('streams', ['video_id' => 'real1', 'status' => 'upcoming']);
        $this->assertDatabaseMissing('manual_schedules', ['id' => $manual->id]);
        // The partial run neither counts as a full refresh nor runs the age sweep.
        $this->assertNull(Setting::get(\App\Console\Commands\FetchStreams::LAST_FETCHED_AT_KEY));
        $this->assertDatabaseHas('streams', ['id' => $oldUpcoming->id, 'status' => 'upcoming']);
    }

    public function test_watched_fetch_refreshes_due_upcoming_and_live_streams_by_video_id(): void
    {
        $channel = Channel::factory()->create(['channel_id' => 'UC_test']);
        Stream::factory()->create(['channel_id' => $channel->id, 'video_id' => 'due1', 'status' => 'upcoming', 'scheduled_at' => now()->subMinutes(10)]);
        Stream::factory()->create(['channel_id' => $channel->id, 'video_id' => 'soon1', 'status' => 'upcoming', 'scheduled_at' => now()->addMinutes(20)]);
        Stream::factory()->create(['channel_id' => $channel->id, 'video_id' => 'later1', 'status' => 'upcoming', 'scheduled_at' => now()->addHours(2)]);
        Stream::factory()->create(['channel_id' => $channel->id, 'video_id' => 'live1', 'status' => 'live', 'scheduled_at' => now()->subHours(5)]);
        Stream::factory()->create(['channel_id' => $channel->id, 'video_id' => 'done1', 'status' => 'completed', 'scheduled_at' => now()->subHours(1)]);

        $mockService = $this->mockYouTube();
        // No manual schedule → no channel-wide sync, just a videos.list on the due ids.
        $mockService->shouldNotReceive('listRecentUploadIds');
        $mockService->shouldReceive('getVideoDetails')
            ->once()
            ->with(Mockery::on(fn ($ids) => count($ids) === 3 && ! array_diff(['due1', 'soon1', 'live1'], $ids)))
            ->andReturn([
                $this->detail('due1', ['status' => 'live', 'scheduled_at' => now()->subMinutes(10)->toIso8601String(), 'actual_start_at' => now()->subMinutes(8)->toIso8601String()]),
                $this->detail('soon1', ['scheduled_at' => now()->addMinutes(20)->toIso8601String()]),
                $this->detail('live1', ['status' => 'completed', 'scheduled_at' => now()->subHours(5)->toIso8601String(), 'actual_end_at' => now()->toIso8601String()]),
            ]);
        $this->app->instance(YouTubeService::class, $mockService);

        $this->artisan('streams:fetch', ['--watched' => true])->assertSuccessful();

        $this->assertDatabaseHas('streams', ['video_id' => 'due1', 'status' => 'live']);
        $this->assertDatabaseHas('streams', ['video_id' => 'soon1', 'status' => 'upcoming']);
        $this->assertDatabaseHas('streams', ['video_id' => 'later1', 'status' => 'upcoming']);
        $this->assertDatabaseHas('streams', ['video_id' => 'live1', 'status' => 'completed']);
        $this->assertDatabaseHas('streams', ['video_id' => 'done1', 'status' => 'completed']);
    }

    public function test_watched_fetch_removes_due_stream_that_youtube_no_longer_serves(): void
    {
        $channel = Channel::factory()->create(['channel_id' => 'UC_test']);
        Stream::factory()->create(['channel_id' => $channel->id, 'video_id' => 'due1', 'status' => 'upcoming', 'scheduled_at' => now()->subMinutes(10)]);
        Stream::factory()->create(['channel_id' => $channel->id, 'video_id' => 'gone1', 'status' => 'upcoming', 'scheduled_at' => now()->subMinutes(5)]);

        $mockService = $this->mockYouTube();
        $mockService->shouldReceive('getVideoDetails')->once()->andReturn([$this->detail('due1')]);
        $this->app->instance(YouTubeService::class, $mockService);

        $this->artisan('streams:fetch', ['--watched' => true])->assertSuccessful();

        $this->assertDatabaseHas('streams', ['video_id' => 'due1']);
        $this->assertDatabaseMissing('streams', ['video_id' => 'gone1']);
    }

    public function test_watched_fetch_does_not_refresh_streams_of_inactive_channels(): void
    {
        $inactive = Channel::factory()->create(['channel_id' => 'UC_off', 'is_active' => false]);
        Stream::factory()->create(['channel_id' => $inactive->id, 'video_id' => 'due_off', 'status' => 'upcoming', 'scheduled_at' => now()->subMinutes(10)]);

        $mockService = $this->mockYouTube();
        $mockService->shouldNotReceive('getVideoDetails');
        $this->app->instance(YouTubeService::class, $mockService);

        $this->artisan('streams:fetch', ['--watched' => true])->assertSuccessful();
    }

    public function test_fetch_streams_tags_new_streams_from_the_dictionary_and_collects_unmatched_terms(): void
    {
        Channel::factory()->create(['channel_id' => 'UC_test']);
        Tag::createWithAlias('Minecraft', 'game');

        $mockService = $this->mockYouTube();
        $mockService->shouldReceive('listRecentUploadIds')->with('UC_test')->andReturn(['v1', 'v2']);
        $mockService->shouldReceive('getVideoDetails')->andReturn([
            $this->detail('v1', ['title' => '【Minecraft】建築する']),
            $this->detail('v2', ['title' => '【Woodo/初見歓迎】遊ぶ']),
        ]);
        $this->app->instance(YouTubeService::class, $mockService);

        $this->artisan('streams:fetch')->assertSuccessful();

        $this->assertSame(['Minecraft'], Stream::where('video_id', 'v1')->first()->tags->pluck('name')->all());
        $this->assertDatabaseHas('unmatched_terms', ['term' => 'woodo', 'count' => 1]);
        $this->assertDatabaseHas('unmatched_terms', ['term' => '初見歓迎', 'count' => 1]);
        $this->assertDatabaseMissing('unmatched_terms', ['term' => 'minecraft']);
    }

    public function test_fetch_streams_imports_members_only_streams_and_flags_them(): void
    {
        $channel = Channel::factory()->create(['channel_id' => 'UC_test']);

        $mockService = $this->mockYouTube();
        $mockService->shouldReceive('listRecentUploadIds')->with('UC_test')->andReturn(['pub1']);
        $mockService->shouldReceive('listMembersOnlyUploadIds')->with('UC_test')->andReturn(['mem1']);
        $mockService->shouldReceive('getVideoDetails')
            ->once()
            ->with(Mockery::on(fn ($ids) => count($ids) === 2 && in_array('pub1', $ids, true) && in_array('mem1', $ids, true)))
            ->andReturn([
                $this->detail('pub1', ['title' => 'Public stream']),
                $this->detail('mem1', ['title' => '【メン限】members stream']),
            ]);
        $this->app->instance(YouTubeService::class, $mockService);

        $this->artisan('streams:fetch')->assertSuccessful();

        $this->assertDatabaseHas('streams', ['video_id' => 'pub1', 'channel_id' => $channel->id, 'is_members_only' => false]);
        $this->assertDatabaseHas('streams', ['video_id' => 'mem1', 'channel_id' => $channel->id, 'is_members_only' => true]);
    }

    public function test_fetch_streams_keeps_members_only_stream_that_is_still_listed(): void
    {
        $channel = Channel::factory()->create(['channel_id' => 'UC_test']);
        Stream::factory()->create(['channel_id' => $channel->id, 'video_id' => 'mem1', 'status' => 'upcoming', 'is_members_only' => true, 'scheduled_at' => now()->addDay()]);

        $mockService = $this->mockYouTube();
        $mockService->shouldReceive('listRecentUploadIds')->with('UC_test')->andReturn([]);
        $mockService->shouldReceive('listMembersOnlyUploadIds')->with('UC_test')->andReturn(['mem1']);
        $mockService->shouldReceive('getVideoDetails')->once()->with(['mem1'])->andReturn([$this->detail('mem1')]);
        $this->app->instance(YouTubeService::class, $mockService);

        $this->artisan('streams:fetch')->assertSuccessful();

        $this->assertDatabaseHas('streams', ['video_id' => 'mem1', 'is_members_only' => true]);
    }

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
            'is_broadcast' => true,
            'published_at' => now()->toIso8601String(),
            'duration_seconds' => null,
        ], $overrides);
    }

    private function shortDetail(string $id, array $overrides = []): array
    {
        return array_merge([
            'video_id' => $id,
            'title' => "Short {$id}",
            'thumbnail_url' => null,
            'scheduled_at' => null,
            'actual_start_at' => null,
            'actual_end_at' => null,
            'status' => 'upcoming',
            'is_broadcast' => false,
            'published_at' => now()->toIso8601String(),
            'duration_seconds' => 30,
        ], $overrides);
    }

    public function test_fetch_streams_imports_shorts_with_type_short(): void
    {
        $channel = Channel::factory()->create(['channel_id' => 'UC_test']);

        $mockService = $this->mockYouTube();
        $mockService->shouldReceive('listRecentUploadIds')->with('UC_test')->andReturn(['s1', 'vid1']);
        $mockService->shouldReceive('getVideoDetails')->andReturn([
            $this->shortDetail('s1', ['published_at' => now()->subHours(2)->toIso8601String()]),
            $this->detail('vid1'),
        ]);
        $this->app->instance(YouTubeService::class, $mockService);

        $this->artisan('streams:fetch')->assertSuccessful();

        $this->assertDatabaseHas('streams', ['video_id' => 's1', 'type' => 'short', 'status' => 'completed', 'duration_seconds' => 30]);
        $this->assertDatabaseHas('streams', ['video_id' => 'vid1', 'type' => 'stream', 'status' => 'upcoming', 'duration_seconds' => null]);
    }

    public function test_fetch_streams_skips_shorts_older_than_backfill_window(): void
    {
        config(['services.youtube.backfill_days' => 14]);
        Channel::factory()->create(['channel_id' => 'UC_test']);

        $mockService = $this->mockYouTube();
        $mockService->shouldReceive('listRecentUploadIds')->with('UC_test')->andReturn(['old_short']);
        $mockService->shouldReceive('getVideoDetails')->andReturn([
            $this->shortDetail('old_short', ['published_at' => now()->subDays(20)->toIso8601String()]),
        ]);
        $this->app->instance(YouTubeService::class, $mockService);

        $this->artisan('streams:fetch')->assertSuccessful();

        $this->assertDatabaseMissing('streams', ['video_id' => 'old_short']);
    }

    public function test_fetch_streams_imports_regular_uploads_as_type_upload(): void
    {
        $channel = Channel::factory()->create(['channel_id' => 'UC_test']);

        $mockService = $this->mockYouTube();
        $mockService->shouldReceive('listRecentUploadIds')->with('UC_test')->andReturn(['long1']);
        $mockService->shouldReceive('getVideoDetails')->andReturn([
            $this->shortDetail('long1', ['duration_seconds' => 600]),
        ]);
        $this->app->instance(YouTubeService::class, $mockService);

        $this->artisan('streams:fetch')->assertSuccessful();

        $this->assertDatabaseHas('streams', ['video_id' => 'long1', 'type' => 'upload', 'status' => 'completed']);
    }

    public function test_short_does_not_remove_overlapping_manual_schedule(): void
    {
        $channel = Channel::factory()->create(['channel_id' => 'UC_test']);
        $user = \App\Models\User::factory()->create();
        $at = now()->addHours(3)->startOfSecond();
        $manual = \App\Models\ManualSchedule::create([
            'user_id' => $user->id, 'channel_id' => $channel->id, 'title' => '告知あり',
            'scheduled_at' => $at, 'is_all_day' => false,
        ]);

        $mockService = $this->mockYouTube();
        $mockService->shouldReceive('listRecentUploadIds')->with('UC_test')->andReturn(['s1']);
        $mockService->shouldReceive('getVideoDetails')->andReturn([
            $this->shortDetail('s1', ['published_at' => $at->toIso8601String()]),
        ]);
        $this->app->instance(YouTubeService::class, $mockService);

        $this->artisan('streams:fetch')->assertSuccessful();

        $this->assertDatabaseHas('manual_schedules', ['id' => $manual->id]);
    }

    public function test_fetch_streams_imports_live_stream_that_was_started_without_a_reservation(): void
    {
        $channel = Channel::factory()->create(['channel_id' => 'UC_test']);
        $startedAt = now()->subMinutes(50)->startOfSecond();

        $mockService = $this->mockYouTube();
        $mockService->shouldReceive('listRecentUploadIds')->with('UC_test')->andReturn(['guerrilla']);
        $mockService->shouldReceive('getVideoDetails')->with(['guerrilla'])->andReturn([
            // What YouTubeService produces for a stream with no scheduledStartTime.
            $this->detail('guerrilla', [
                'scheduled_at' => $startedAt->toIso8601String(),
                'actual_start_at' => $startedAt->toIso8601String(),
                'status' => 'live',
            ]),
        ]);
        $this->app->instance(YouTubeService::class, $mockService);

        $this->artisan('streams:fetch')->assertSuccessful();

        $this->assertDatabaseHas('streams', [
            'video_id' => 'guerrilla',
            'channel_id' => $channel->id,
            'status' => 'live',
            'scheduled_at' => $startedAt->toDateTimeString(),
        ]);
    }

    public function test_fetch_streams_skips_non_broadcast_without_published_at(): void
    {
        Channel::factory()->create(['channel_id' => 'UC_test']);

        $mockService = $this->mockYouTube();
        $mockService->shouldReceive('listRecentUploadIds')->with('UC_test')->andReturn(['orphan1']);
        $mockService->shouldReceive('getVideoDetails')->with(['orphan1'])->andReturn([
            $this->shortDetail('orphan1', ['published_at' => null]),
        ]);
        $this->app->instance(YouTubeService::class, $mockService);

        $this->artisan('streams:fetch')->assertSuccessful();

        $this->assertDatabaseMissing('streams', ['video_id' => 'orphan1']);
    }

    public function test_fetch_streams_creates_new_upcoming_stream_from_uploads(): void
    {
        $channel = Channel::factory()->create(['channel_id' => 'UC_test']);

        $mockService = $this->mockYouTube();
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

        $mockService = $this->mockYouTube();
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

        $mockService = $this->mockYouTube();
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

        $mockService = $this->mockYouTube();
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

        $mockService = $this->mockYouTube();
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

        $mockService = $this->mockYouTube();
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

        $mockService = $this->mockYouTube();
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

        $mockService = $this->mockYouTube();
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

        $mockService = $this->mockYouTube();
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

        $mockService = $this->mockYouTube();
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

        $mockService = $this->mockYouTube();
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

        $mockService = $this->mockYouTube();
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

        $mockService = $this->mockYouTube();
        $mockService->shouldReceive('listRecentUploadIds')->andReturn([]);
        $this->app->instance(YouTubeService::class, $mockService);

        $this->artisan('streams:fetch')->assertSuccessful();

        $this->assertNotNull(Setting::get('streams_last_fetched_at'));
    }
}
