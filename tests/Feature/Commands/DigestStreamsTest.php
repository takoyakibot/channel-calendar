<?php

namespace Tests\Feature\Commands;

use App\Console\Commands\DigestStreams;
use App\Models\Channel;
use App\Models\Setting;
use App\Models\Stream;
use App\Services\XPoster;
use App\Services\XPosterException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class DigestStreamsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-25 03:00:00', 'UTC')); // 12:00 JST
    }

    private function mockPoster(bool $configured = true): MockInterface
    {
        $mock = Mockery::mock(XPoster::class);
        $mock->shouldReceive('isConfigured')->andReturn($configured);
        $this->app->instance(XPoster::class, $mock);

        return $mock;
    }

    private function seedStreams(): void
    {
        $channel = Channel::factory()->create(['short_name' => '奈煌']);
        $members = Channel::factory()->create(['short_name' => 'メン限']);
        $inactive = Channel::factory()->create(['short_name' => '停止中', 'is_active' => false]);
        Stream::factory()->create(['channel_id' => $channel->id, 'status' => 'live', 'title' => 'いま配信中', 'scheduled_at' => now()->subHour()]);
        Stream::factory()->create(['channel_id' => $channel->id, 'status' => 'upcoming', 'title' => 'こんばんの枠', 'scheduled_at' => now()->addHours(8)]);
        Stream::factory()->create(['channel_id' => $channel->id, 'status' => 'upcoming', 'title' => 'すぎた枠', 'scheduled_at' => now()->subMinutes(30)]);
        Stream::factory()->create(['channel_id' => $channel->id, 'status' => 'completed', 'title' => 'おわった枠', 'scheduled_at' => now()->subHours(5)]);
        Stream::factory()->create(['channel_id' => $members->id, 'status' => 'upcoming', 'title' => 'メン限の枠', 'scheduled_at' => now()->addHours(3), 'is_members_only' => true]);
        Stream::factory()->create(['channel_id' => $inactive->id, 'status' => 'upcoming', 'title' => '停止中の枠', 'scheduled_at' => now()->addHours(3)]);
    }

    public function test_posts_one_digest_of_live_and_future_public_streams_and_remembers_the_day(): void
    {
        $this->seedStreams();

        $poster = $this->mockPoster();
        $poster->shouldReceive('post')->once()->with(Mockery::on(function ($t) {
            return str_starts_with($t, '📅 9/25(金) の配信予定')
                && str_contains($t, '🔴 配信中 奈煌 / いま配信中')
                && str_contains($t, '20:00 奈煌 / こんばんの枠')
                && ! str_contains($t, 'すぎた枠') && ! str_contains($t, 'おわった枠')
                && ! str_contains($t, 'メン限') && ! str_contains($t, '停止中');
        }))->andReturn('555');

        $this->artisan('streams:digest')->assertSuccessful();

        $this->assertSame('2026-09-25', Setting::get(DigestStreams::POSTED_ON_KEY));
    }

    public function test_does_not_post_twice_on_the_same_jst_day_unless_forced(): void
    {
        $this->seedStreams();
        Setting::set(DigestStreams::POSTED_ON_KEY, '2026-09-25');

        $poster = $this->mockPoster();
        $poster->shouldReceive('post')->once()->andReturn('556');

        $this->artisan('streams:digest')->assertSuccessful();   // skipped
        $this->artisan('streams:digest', ['--force' => true])->assertSuccessful(); // posted
    }

    public function test_posts_nothing_when_there_is_nothing_to_announce(): void
    {
        Channel::factory()->create();

        $poster = $this->mockPoster();
        $poster->shouldNotReceive('post');

        $this->artisan('streams:digest')->assertSuccessful();

        $this->assertNull(Setting::get(DigestStreams::POSTED_ON_KEY));
    }

    public function test_dry_run_prints_the_digest_without_posting(): void
    {
        $this->seedStreams();

        $poster = $this->mockPoster();
        $poster->shouldNotReceive('post');

        $this->artisan('streams:digest', ['--dry-run' => true])
            ->expectsOutputToContain('こんばんの枠')
            ->assertSuccessful();

        $this->assertNull(Setting::get(DigestStreams::POSTED_ON_KEY));
    }

    public function test_does_nothing_without_credentials(): void
    {
        $this->seedStreams();

        $poster = $this->mockPoster(false);
        $poster->shouldNotReceive('post');

        $this->artisan('streams:digest')->assertSuccessful();
    }

    public function test_a_failed_post_is_logged_and_the_day_stays_open_for_a_retry(): void
    {
        $this->seedStreams();

        $poster = $this->mockPoster();
        $poster->shouldReceive('post')->once()->andThrow(new XPosterException('credits depleted', 402));

        $this->artisan('streams:digest')->assertSuccessful();

        $this->assertNull(Setting::get(DigestStreams::POSTED_ON_KEY));
    }
}
