<?php

namespace Tests\Feature\Admin;

use App\Models\Channel;
use App\Models\Setting;
use App\Models\User;
use App\Services\YouTubeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class FetchNowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_trigger_fetch(): void
    {
        $this->post('/admin/streams/fetch')->assertRedirect('/login');
    }

    public function test_admin_can_trigger_fetch_now(): void
    {
        $admin = User::factory()->create();
        Channel::factory()->create(['channel_id' => 'UC_test']);

        $mockService = Mockery::mock(YouTubeService::class);
        $mockService->shouldReceive('listRecentUploadIds')->once()->with('UC_test')->andReturn([]);
        $this->app->instance(YouTubeService::class, $mockService);

        $response = $this->actingAs($admin)->from('/admin/channels')->post('/admin/streams/fetch');

        $response->assertRedirect('/admin/channels');
        $response->assertSessionHas('success');
        $this->assertNotNull(Setting::get('streams_last_fetched_at'));
    }

    public function test_channel_list_shows_last_fetched_time_and_fetch_button(): void
    {
        $admin = User::factory()->create();
        Setting::set('streams_last_fetched_at', '2026-09-15T10:00:00+00:00');

        $response = $this->actingAs($admin)->get('/admin/channels');

        $response->assertOk();
        $response->assertSee('今すぐ取得');
        $response->assertSee('最終取得');
        $response->assertSee('2026');
    }
}
