<?php

namespace Tests\Feature\Services;

use App\Services\TwitchService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TwitchServiceTest extends TestCase
{
    private TwitchService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->service = new TwitchService('cid', 'secret');
    }

    public function test_not_configured_without_credentials(): void
    {
        $this->assertFalse((new TwitchService(null, 'x'))->isConfigured());
        $this->assertTrue($this->service->isConfigured());
    }

    public function test_fetches_an_app_token_once_and_sends_it_with_the_client_id(): void
    {
        Http::fake([
            'id.twitch.tv/oauth2/token' => Http::response(['access_token' => 'tok123', 'expires_in' => 5000000, 'token_type' => 'bearer']),
            'api.twitch.tv/helix/users*' => Http::response(['data' => [['id' => '42', 'login' => 'amawauru', 'display_name' => 'Amawa Uru', 'profile_image_url' => 'https://img/p.png']]]),
        ]);

        $user = $this->service->getUserByLogin('amawauru');
        $this->service->getUserByLogin('amawauru');

        $this->assertSame(['id' => '42', 'login' => 'amawauru', 'display_name' => 'Amawa Uru', 'profile_image_url' => 'https://img/p.png'], $user);
        Http::assertSentCount(3); // one token, two lookups
        Http::assertSent(fn ($req) => str_contains($req->url(), 'helix/users')
            && $req->hasHeader('Authorization', 'Bearer tok123') && $req->hasHeader('Client-Id', 'cid'));
    }

    public function test_live_stream_schedule_and_archives_are_normalised(): void
    {
        Http::fake([
            'id.twitch.tv/oauth2/token' => Http::response(['access_token' => 'tok', 'expires_in' => 5000000]),
            'api.twitch.tv/helix/streams*' => Http::response(['data' => [[
                'id' => '9001', 'title' => '朝活', 'started_at' => '2026-09-27T00:05:00Z', 'game_name' => 'Just Chatting',
                'thumbnail_url' => 'https://static-cdn.jtvnw.net/previews-ttv/live_user_amawauru-{width}x{height}.jpg',
            ]]]),
            'api.twitch.tv/helix/schedule*' => Http::response(['data' => ['segments' => [
                ['id' => 'segA', 'title' => '夜の配信', 'start_time' => '2026-09-28T11:00:00Z', 'end_time' => '2026-09-28T13:00:00Z', 'category' => ['name' => 'Minecraft'], 'canceled_until' => null],
                ['id' => 'segB', 'title' => '中止', 'start_time' => '2026-09-29T11:00:00Z', 'end_time' => null, 'category' => null, 'canceled_until' => '2026-09-29T11:00:00Z'],
            ]]]),
            'api.twitch.tv/helix/videos*' => Http::response(['data' => [[
                'id' => '555', 'stream_id' => '9000', 'title' => '昨日の配信', 'created_at' => '2026-09-26T10:00:00Z', 'duration' => '3h2m1s',
                'url' => 'https://www.twitch.tv/videos/555', 'thumbnail_url' => 'https://static-cdn.jtvnw.net/cf_vods/x/thumb/thumb0-%{width}x%{height}.jpg',
            ]]]),
        ]);

        $live = $this->service->getLiveStream('42');
        $this->assertSame('9001', $live['id']);
        $this->assertSame('https://static-cdn.jtvnw.net/previews-ttv/live_user_amawauru-320x180.jpg', $live['thumbnail_url']);

        $schedule = $this->service->getSchedule('42');
        $this->assertCount(1, $schedule);
        $this->assertSame(['id' => 'segA', 'title' => '夜の配信', 'start_time' => '2026-09-28T11:00:00Z', 'end_time' => '2026-09-28T13:00:00Z', 'category' => 'Minecraft'], $schedule[0]);

        $vods = $this->service->getArchives('42');
        $this->assertSame('9000', $vods[0]['stream_id']);
        $this->assertSame(10921, $vods[0]['duration_seconds']);
        $this->assertSame('https://static-cdn.jtvnw.net/cf_vods/x/thumb/thumb0-320x180.jpg', $vods[0]['thumbnail_url']);
    }

    public function test_missing_schedule_and_unknown_user_are_empty_not_errors(): void
    {
        Http::fake([
            'id.twitch.tv/oauth2/token' => Http::response(['access_token' => 'tok', 'expires_in' => 5000000]),
            'api.twitch.tv/helix/schedule*' => Http::response(['error' => 'Not Found', 'status' => 404], 404),
            'api.twitch.tv/helix/users*' => Http::response(['data' => []]),
            'api.twitch.tv/helix/streams*' => Http::response(['data' => []]),
        ]);

        $this->assertSame([], $this->service->getSchedule('42'));
        $this->assertNull($this->service->getUserByLogin('nobody'));
        $this->assertNull($this->service->getLiveStream('42'));
    }

    public function test_a_rejected_token_is_refreshed_and_the_call_retried(): void
    {
        Cache::put('twitch.app_token', 'stale', 60);
        Http::fake([
            'id.twitch.tv/oauth2/token' => Http::response(['access_token' => 'fresh', 'expires_in' => 5000000]),
            'api.twitch.tv/helix/users*' => Http::sequence()
                ->push(['error' => 'Unauthorized', 'status' => 401], 401)
                ->push(['data' => [['id' => '42', 'login' => 'amawauru', 'display_name' => 'A']]]),
        ]);

        $this->assertSame('42', $this->service->getUserByLogin('amawauru')['id']);
        $this->assertSame('fresh', Cache::get('twitch.app_token'));
    }

    public function test_duration_parsing(): void
    {
        $this->assertSame(10921, TwitchService::durationToSeconds('3h2m1s'));
        $this->assertSame(125, TwitchService::durationToSeconds('2m5s'));
        $this->assertSame(0, TwitchService::durationToSeconds(''));
    }
}
