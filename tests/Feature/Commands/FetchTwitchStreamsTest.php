<?php

namespace Tests\Feature\Commands;

use App\Models\Channel;
use App\Models\Stream;
use App\Models\Tag;
use App\Services\TwitchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class FetchTwitchStreamsTest extends TestCase
{
    use RefreshDatabase;

    private function mockTwitch(bool $configured = true): MockInterface
    {
        $mock = Mockery::mock(TwitchService::class);
        $mock->shouldReceive('isConfigured')->andReturn($configured);
        $mock->shouldReceive('getLiveStream')->byDefault()->andReturn(null);
        $mock->shouldReceive('getSchedule')->byDefault()->andReturn([]);
        $mock->shouldReceive('getArchives')->byDefault()->andReturn([]);
        $this->app->instance(TwitchService::class, $mock);

        return $mock;
    }

    private function channel(): Channel
    {
        return Channel::factory()->create(['name' => '天和うる', 'twitch_login' => 'amawauru', 'twitch_user_id' => '42']);
    }

    public function test_resolves_the_twitch_user_id_once_and_stores_live_and_scheduled_broadcasts(): void
    {
        $channel = Channel::factory()->create(['twitch_login' => 'amawauru', 'twitch_user_id' => null]);
        Channel::factory()->create(['twitch_login' => null]);   // not a Twitch member
        $twitch = $this->mockTwitch();
        $twitch->shouldReceive('getUserByLogin')->once()->with('amawauru')->andReturn(['id' => '42', 'login' => 'amawauru', 'display_name' => 'A', 'profile_image_url' => null]);
        $twitch->shouldReceive('getLiveStream')->with('42')->andReturn(['id' => '9001', 'title' => '朝活【Minecraft】', 'started_at' => now()->subMinutes(20)->toIso8601String(), 'game_name' => 'Minecraft', 'thumbnail_url' => 'https://thumb/live.jpg']);
        $twitch->shouldReceive('getSchedule')->with('42')->andReturn([
            ['id' => 'segA', 'title' => '夜の配信', 'start_time' => now()->addDay()->toIso8601String(), 'end_time' => null, 'category' => 'Minecraft'],
            ['id' => 'segOld', 'title' => '昔', 'start_time' => now()->subDays(2)->toIso8601String(), 'end_time' => null, 'category' => null],
        ]);
        Tag::createWithAlias('Minecraft', 'game');

        $this->artisan('streams:fetch-twitch')->assertSuccessful();

        $this->assertSame('42', $channel->fresh()->twitch_user_id);
        $this->assertDatabaseHas('streams', ['video_id' => 'tw:9001', 'channel_id' => $channel->id, 'platform' => 'twitch', 'status' => 'live', 'type' => 'stream', 'url' => 'https://www.twitch.tv/amawauru', 'title' => '朝活【Minecraft】']);
        $this->assertSame(['Minecraft'], Stream::where('video_id', 'tw:9001')->first()->tags->pluck('name')->all());
        $this->assertSame(1, Stream::where('video_id', 'like', 'tw:sched:%')->count());
        $this->assertDatabaseHas('streams', ['channel_id' => $channel->id, 'platform' => 'twitch', 'status' => 'upcoming', 'title' => '夜の配信']);
        $this->assertDatabaseMissing('streams', ['title' => '昔']);

        // Second run: user id cached, nothing duplicated.
        $this->artisan('streams:fetch-twitch')->assertSuccessful();
        $this->assertSame(2, Stream::where('platform', 'twitch')->count());
    }

    public function test_the_vod_completes_the_live_row_and_a_covered_segment_disappears(): void
    {
        $channel = $this->channel();
        $startedAt = now()->subHours(3)->startOfSecond();
        Stream::factory()->create(['channel_id' => $channel->id, 'video_id' => 'tw:9001', 'platform' => 'twitch', 'status' => 'live', 'scheduled_at' => $startedAt, 'actual_start_at' => $startedAt, 'url' => 'https://www.twitch.tv/amawauru']);
        Stream::factory()->create(['channel_id' => $channel->id, 'video_id' => 'tw:sched:covered', 'platform' => 'twitch', 'status' => 'upcoming', 'scheduled_at' => $startedAt->copy()->addMinutes(30)]);
        Stream::factory()->create(['channel_id' => $channel->id, 'video_id' => 'tw:sched:future', 'platform' => 'twitch', 'status' => 'upcoming', 'scheduled_at' => now()->addDays(2), 'title' => '未来']);
        $twitch = $this->mockTwitch();
        $twitch->shouldReceive('getArchives')->with('42')->andReturn([
            ['id' => '555', 'stream_id' => '9001', 'title' => '朝活アーカイブ', 'created_at' => $startedAt->toIso8601String(), 'duration_seconds' => 7200, 'url' => 'https://www.twitch.tv/videos/555', 'thumbnail_url' => null],
            ['id' => '111', 'stream_id' => null, 'title' => '古すぎる', 'created_at' => now()->subDays(30)->toIso8601String(), 'duration_seconds' => 100, 'url' => 'https://www.twitch.tv/videos/111', 'thumbnail_url' => null],
        ]);
        $twitch->shouldReceive('getSchedule')->with('42')->andReturn([
            ['id' => 'future-id', 'title' => '未来', 'start_time' => now()->addDays(2)->toIso8601String(), 'end_time' => null, 'category' => null],
        ]);

        $this->artisan('streams:fetch-twitch')->assertSuccessful();

        $vod = Stream::where('video_id', 'tw:9001')->first();
        $this->assertSame('completed', $vod->status);
        $this->assertSame('https://www.twitch.tv/videos/555', $vod->url);
        $this->assertSame(7200, $vod->duration_seconds);
        $this->assertSame($startedAt->copy()->addSeconds(7200)->toDateTimeString(), $vod->actual_end_at->toDateTimeString());
        $this->assertDatabaseMissing('streams', ['video_id' => 'tw:sched:covered']);
        $this->assertDatabaseMissing('streams', ['title' => '古すぎる']);
        // The future segment is listed under a different key now; the stale hand-made one is gone.
        $this->assertSame(1, Stream::where('platform', 'twitch')->where('status', 'upcoming')->count());
    }

    public function test_a_live_row_that_ended_without_a_vod_is_closed_and_removed_segments_are_dropped(): void
    {
        $channel = $this->channel();
        Stream::factory()->create(['channel_id' => $channel->id, 'video_id' => 'tw:9001', 'platform' => 'twitch', 'status' => 'live', 'scheduled_at' => now()->subHours(2), 'actual_start_at' => now()->subHours(2)]);
        Stream::factory()->create(['channel_id' => $channel->id, 'video_id' => 'tw:sched:gone', 'platform' => 'twitch', 'status' => 'upcoming', 'scheduled_at' => now()->addDays(3)]);
        $this->mockTwitch();

        $this->artisan('streams:fetch-twitch')->assertSuccessful();

        $row = Stream::where('video_id', 'tw:9001')->first();
        $this->assertSame('completed', $row->status);
        $this->assertNotNull($row->actual_end_at);
        $this->assertDatabaseMissing('streams', ['video_id' => 'tw:sched:gone']);
    }

    public function test_skips_quietly_without_credentials_or_twitch_members(): void
    {
        $twitch = $this->mockTwitch(false);
        $twitch->shouldNotReceive('getLiveStream');
        $this->channel();
        $this->artisan('streams:fetch-twitch')->assertSuccessful();

        $twitch = $this->mockTwitch(true);
        Channel::query()->update(['twitch_login' => null]);
        $twitch->shouldNotReceive('getLiveStream');
        $this->artisan('streams:fetch-twitch')->assertSuccessful();
    }
}
