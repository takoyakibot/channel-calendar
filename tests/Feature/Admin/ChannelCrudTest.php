<?php

namespace Tests\Feature\Admin;

use App\Models\Channel;
use App\Models\User;
use App\Services\YouTubeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ChannelCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
    }

    public function test_guest_cannot_access_admin_channels(): void
    {
        $response = $this->get('/admin/channels');
        $response->assertRedirect('/login');
    }

    public function test_admin_can_view_channel_list(): void
    {
        Channel::factory()->create(['name' => 'Test Channel']);

        $response = $this->actingAs($this->admin)->get('/admin/channels');

        $response->assertOk();
        $response->assertSee('Test Channel');
    }

    public function test_admin_can_create_channel(): void
    {
        $mockService = Mockery::mock(YouTubeService::class);
        $mockService->shouldReceive('getChannelInfo')
            ->with('UC_new')
            ->andReturn(['name' => 'New Channel', 'thumbnail_url' => 'https://example.com/new.jpg']);
        $this->app->instance(YouTubeService::class, $mockService);

        $response = $this->actingAs($this->admin)->post('/admin/channels', [
            'channel_id' => 'UC_new',
            'color' => '#FF0000',
        ]);

        $response->assertRedirect('/admin/channels');
        $this->assertDatabaseHas('channels', [
            'channel_id' => 'UC_new',
            'name' => 'New Channel',
            'color' => '#FF0000',
        ]);
    }

    public function test_create_channel_shows_error_when_youtube_lookup_fails(): void
    {
        $mockService = Mockery::mock(YouTubeService::class);
        $mockService->shouldReceive('getChannelInfo')
            ->with('UC_bad')
            ->andThrow(new \RuntimeException('Channel not found: UC_bad'));
        $this->app->instance(YouTubeService::class, $mockService);

        $response = $this->actingAs($this->admin)
            ->from('/admin/channels/create')
            ->post('/admin/channels', [
                'channel_id' => 'UC_bad',
                'color' => '#FF0000',
            ]);

        $response->assertRedirect('/admin/channels/create');
        $response->assertSessionHasErrors('channel_id');
        $this->assertDatabaseMissing('channels', ['channel_id' => 'UC_bad']);
    }

    public function test_admin_can_update_channel(): void
    {
        $channel = Channel::factory()->create();

        $response = $this->actingAs($this->admin)->put("/admin/channels/{$channel->id}", [
            'color' => '#00FF00',
            'is_active' => false,
        ]);

        $response->assertRedirect('/admin/channels');
        $this->assertDatabaseHas('channels', [
            'id' => $channel->id,
            'color' => '#00FF00',
            'is_active' => false,
        ]);
    }

    public function test_admin_can_delete_channel(): void
    {
        $channel = Channel::factory()->create();

        $response = $this->actingAs($this->admin)->delete("/admin/channels/{$channel->id}");

        $response->assertRedirect('/admin/channels');
        $this->assertDatabaseMissing('channels', ['id' => $channel->id]);
    }
}
