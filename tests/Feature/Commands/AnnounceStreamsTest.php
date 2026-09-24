<?php

namespace Tests\Feature\Commands;

use App\Models\Channel;
use App\Models\Stream;
use App\Services\XPoster;
use App\Services\XPosterException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class AnnounceStreamsTest extends TestCase
{
    use RefreshDatabase;

    private function mockPoster(bool $configured = true): MockInterface
    {
        $mock = Mockery::mock(XPoster::class);
        $mock->shouldReceive('isConfigured')->andReturn($configured);
        $this->app->instance(XPoster::class, $mock);

        return $mock;
    }

    private function upcoming(Channel $channel, string $videoId, array $overrides = []): Stream
    {
        return Stream::factory()->create(array_merge([
            'channel_id' => $channel->id,
            'video_id' => $videoId,
            'title' => "Stream {$videoId}",
            'status' => 'upcoming',
            'scheduled_at' => now()->addHours(3),
            'is_members_only' => false,
            'announced_at' => null,
        ], $overrides));
    }

    public function test_announces_new_public_reservations_oldest_first_and_marks_them(): void
    {
        $channel = Channel::factory()->create(['name' => 'Test Ch']);
        $newer = $this->upcoming($channel, 'newer', ['created_at' => now()->subMinutes(5)]);
        $older = $this->upcoming($channel, 'older', ['created_at' => now()->subMinutes(30)]);

        $poster = $this->mockPoster();
        $poster->shouldReceive('post')->once()->ordered()
            ->with(Mockery::on(fn ($t) => str_contains($t, 'Stream older') && str_contains($t, 'watch?v=older')))
            ->andReturn('111');
        $poster->shouldReceive('post')->once()->ordered()
            ->with(Mockery::on(fn ($t) => str_contains($t, 'Stream newer')))
            ->andReturn('222');

        $this->artisan('streams:announce')->assertSuccessful();

        $this->assertDatabaseHas('streams', ['id' => $older->id, 'announced_tweet_id' => '111']);
        $this->assertDatabaseHas('streams', ['id' => $newer->id, 'announced_tweet_id' => '222']);
        $this->assertNotNull($older->fresh()->announced_at);
    }

    public function test_skips_everything_that_is_not_a_fresh_public_reservation(): void
    {
        $channel = Channel::factory()->create();
        $inactive = Channel::factory()->create(['is_active' => false]);
        $this->upcoming($channel, 'members', ['is_members_only' => true]);
        $this->upcoming($channel, 'live_now', ['status' => 'live']);
        $this->upcoming($channel, 'done', ['status' => 'completed']);
        $this->upcoming($channel, 'already', ['announced_at' => now()->subHour()]);
        $this->upcoming($channel, 'started', ['scheduled_at' => now()->subMinutes(10)]);
        $this->upcoming($channel, 'stale', ['created_at' => now()->subDays(4)]);
        $this->upcoming($inactive, 'off', []);

        $poster = $this->mockPoster();
        $poster->shouldNotReceive('post');

        $this->artisan('streams:announce')->assertSuccessful();

        $this->assertNull(Stream::where('video_id', 'members')->first()->announced_at);
    }

    public function test_respects_the_daily_limit_counting_only_real_posts(): void
    {
        config(['services.x.announce.daily_limit' => 2]);
        $channel = Channel::factory()->create();
        // Backfilled-as-announced rows (no tweet id) must not eat into the limit.
        $this->upcoming($channel, 'baseline', ['announced_at' => now()->subHour(), 'announced_tweet_id' => null]);
        // One real post within the last 24 h leaves room for exactly one more.
        $this->upcoming($channel, 'posted', ['announced_at' => now()->subHours(2), 'announced_tweet_id' => '9']);
        $this->upcoming($channel, 'a', ['created_at' => now()->subMinutes(20)]);
        $this->upcoming($channel, 'b', ['created_at' => now()->subMinutes(10)]);

        $poster = $this->mockPoster();
        $poster->shouldReceive('post')->once()->with(Mockery::on(fn ($t) => str_contains($t, 'watch?v=a')))->andReturn('10');

        $this->artisan('streams:announce')->assertSuccessful();

        $this->assertNull(Stream::where('video_id', 'b')->first()->announced_at);
    }

    public function test_dry_run_posts_nothing_and_marks_nothing(): void
    {
        $channel = Channel::factory()->create();
        $stream = $this->upcoming($channel, 'dry');

        $poster = $this->mockPoster();
        $poster->shouldNotReceive('post');

        $this->artisan('streams:announce', ['--dry-run' => true])
            ->expectsOutputToContain('watch?v=dry')
            ->assertSuccessful();

        $this->assertNull($stream->fresh()->announced_at);
    }

    public function test_does_nothing_when_x_credentials_are_missing(): void
    {
        $channel = Channel::factory()->create();
        $this->upcoming($channel, 'nocreds');

        $poster = $this->mockPoster(false);
        $poster->shouldNotReceive('post');

        $this->artisan('streams:announce')->assertSuccessful();
    }

    public function test_stops_the_run_when_x_rate_limits_and_leaves_the_stream_unannounced(): void
    {
        $channel = Channel::factory()->create();
        $first = $this->upcoming($channel, 'first', ['created_at' => now()->subMinutes(20)]);
        $second = $this->upcoming($channel, 'second', ['created_at' => now()->subMinutes(10)]);

        $poster = $this->mockPoster();
        $poster->shouldReceive('post')->once()->andThrow(new XPosterException('Too Many Requests', 429));

        $this->artisan('streams:announce')->assertSuccessful();

        $this->assertNull($first->fresh()->announced_at);
        $this->assertNull($second->fresh()->announced_at);
    }

    public function test_a_failed_post_does_not_block_the_next_one(): void
    {
        $channel = Channel::factory()->create();
        $first = $this->upcoming($channel, 'first', ['created_at' => now()->subMinutes(20)]);
        $second = $this->upcoming($channel, 'second', ['created_at' => now()->subMinutes(10)]);

        $poster = $this->mockPoster();
        $poster->shouldReceive('post')->once()->ordered()->andThrow(new XPosterException('Forbidden', 403));
        $poster->shouldReceive('post')->once()->ordered()->andReturn('2');

        $this->artisan('streams:announce')->assertSuccessful();

        $this->assertNull($first->fresh()->announced_at);
        $this->assertSame('2', $second->fresh()->announced_tweet_id);
    }
}
