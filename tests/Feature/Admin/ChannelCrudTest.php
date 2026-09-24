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
        $this->admin = User::factory()->admin()->create();
    }

    public function test_guest_cannot_access_admin_channels(): void
    {
        $response = $this->get('/admin/channels');
        $response->assertRedirect(route('auth.google'));
    }

    public function test_admin_can_view_channel_list(): void
    {
        Channel::factory()->create(['name' => 'Test Channel', 'x_handle' => 'some_user']);

        $response = $this->actingAs($this->admin)->get('/admin/channels');

        $response->assertOk();
        $response->assertSee('Test Channel');
        $response->assertSee('@some_user');
        $response->assertSee('https://x.com/some_user', false);
        // A stray "@{{" would make Blade print the expression literally.
        $response->assertDontSee('{{ $channel', false);
    }

    public function test_admin_can_create_channel_by_handle(): void
    {
        $mockService = Mockery::mock(YouTubeService::class);
        $mockService->shouldReceive('findChannel')
            ->with('handle', '@new_channel')
            ->andReturn([
                'channel_id' => 'UC_new',
                'handle' => '@new_channel',
                'name' => 'New Channel',
                'thumbnail_url' => 'https://example.com/new.jpg',
            ]);
        $this->app->instance(YouTubeService::class, $mockService);

        $response = $this->actingAs($this->admin)->post('/admin/channels', [
            'channel' => 'https://www.youtube.com/@new_channel',
            'color' => '#FF0000',
        ]);

        $response->assertRedirect('/admin/channels');
        $this->assertDatabaseHas('channels', [
            'channel_id' => 'UC_new',
            'handle' => '@new_channel',
            'name' => 'New Channel',
            'color' => '#FF0000',
        ]);
    }

    public function test_admin_can_create_channel_by_id(): void
    {
        $mockService = Mockery::mock(YouTubeService::class);
        $mockService->shouldReceive('findChannel')
            ->with('id', 'UCBR8-60-B28hp2BmDPdntcQ')
            ->andReturn([
                'channel_id' => 'UCBR8-60-B28hp2BmDPdntcQ',
                'handle' => '@youtube',
                'name' => 'YouTube',
                'thumbnail_url' => null,
            ]);
        $this->app->instance(YouTubeService::class, $mockService);

        $response = $this->actingAs($this->admin)->post('/admin/channels', [
            'channel' => 'UCBR8-60-B28hp2BmDPdntcQ',
            'color' => '#FF0000',
        ]);

        $response->assertRedirect('/admin/channels');
        $this->assertDatabaseHas('channels', ['channel_id' => 'UCBR8-60-B28hp2BmDPdntcQ', 'handle' => '@youtube']);
    }

    public function test_create_channel_rejects_unparseable_input_without_calling_api(): void
    {
        $mockService = Mockery::mock(YouTubeService::class);
        $mockService->shouldNotReceive('findChannel');
        $this->app->instance(YouTubeService::class, $mockService);

        $response = $this->actingAs($this->admin)
            ->from('/admin/channels/create')
            ->post('/admin/channels', [
                'channel' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'color' => '#FF0000',
            ]);

        $response->assertRedirect('/admin/channels/create');
        $response->assertSessionHasErrors('channel');
        $this->assertDatabaseCount('channels', 0);
    }

    public function test_create_channel_shows_error_when_youtube_lookup_fails(): void
    {
        $mockService = Mockery::mock(YouTubeService::class);
        $mockService->shouldReceive('findChannel')
            ->with('handle', '@nobody')
            ->andThrow(new \RuntimeException('Channel not found: @nobody'));
        $this->app->instance(YouTubeService::class, $mockService);

        $response = $this->actingAs($this->admin)
            ->from('/admin/channels/create')
            ->post('/admin/channels', [
                'channel' => '@nobody',
                'color' => '#FF0000',
            ]);

        $response->assertRedirect('/admin/channels/create');
        $response->assertSessionHasErrors('channel');
        $this->assertDatabaseCount('channels', 0);
    }

    public function test_create_channel_rejects_already_registered_channel(): void
    {
        Channel::factory()->create(['channel_id' => 'UC_existing', 'handle' => '@existing']);

        $mockService = Mockery::mock(YouTubeService::class);
        $mockService->shouldReceive('findChannel')
            ->with('handle', '@existing')
            ->andReturn(['channel_id' => 'UC_existing', 'handle' => '@existing', 'name' => 'Existing', 'thumbnail_url' => null]);
        $this->app->instance(YouTubeService::class, $mockService);

        $response = $this->actingAs($this->admin)
            ->from('/admin/channels/create')
            ->post('/admin/channels', [
                'channel' => '@existing',
                'color' => '#FF0000',
            ]);

        $response->assertRedirect('/admin/channels/create');
        $response->assertSessionHasErrors('channel');
        $this->assertDatabaseCount('channels', 1);
    }

    public function test_admin_can_set_x_handle_from_profile_url(): void
    {
        $channel = Channel::factory()->create();

        $response = $this->actingAs($this->admin)->put("/admin/channels/{$channel->id}", [
            'color' => '#00FF00',
            'is_active' => true,
            'x_handle' => 'https://x.com/Some_User?s=21',
        ]);

        $response->assertRedirect('/admin/channels');
        $this->assertDatabaseHas('channels', ['id' => $channel->id, 'x_handle' => 'Some_User']);
    }

    public function test_admin_can_set_and_clear_the_short_name_used_in_x_posts(): void
    {
        $channel = Channel::factory()->create(['name' => 'Nakira Ch. 奈煌🐼']);

        $this->actingAs($this->admin)->put("/admin/channels/{$channel->id}", [
            'color' => '#00FF00', 'is_active' => true, 'short_name' => ' 奈煌ちゃん ',
        ])->assertRedirect('/admin/channels');
        $this->assertDatabaseHas('channels', ['id' => $channel->id, 'short_name' => '奈煌ちゃん']);

        $this->actingAs($this->admin)->put("/admin/channels/{$channel->id}", [
            'color' => '#00FF00', 'is_active' => true, 'short_name' => '',
        ])->assertRedirect('/admin/channels');
        $this->assertDatabaseHas('channels', ['id' => $channel->id, 'short_name' => null]);

        $this->actingAs($this->admin)->put("/admin/channels/{$channel->id}", [
            'color' => '#00FF00', 'is_active' => true, 'short_name' => str_repeat('あ', 21),
        ])->assertSessionHasErrors('short_name');
    }

    public function test_admin_can_clear_x_handle(): void
    {
        $channel = Channel::factory()->create(['x_handle' => 'old_user']);

        $this->actingAs($this->admin)->put("/admin/channels/{$channel->id}", [
            'color' => '#00FF00',
            'is_active' => true,
            'x_handle' => '',
        ])->assertRedirect('/admin/channels');

        $this->assertDatabaseHas('channels', ['id' => $channel->id, 'x_handle' => null]);
    }

    public function test_invalid_x_handle_is_rejected(): void
    {
        $channel = Channel::factory()->create();

        $response = $this->actingAs($this->admin)->from("/admin/channels/{$channel->id}/edit")->put("/admin/channels/{$channel->id}", [
            'color' => '#00FF00',
            'is_active' => true,
            'x_handle' => 'https://x.com/some_user/status/123',
        ]);

        $response->assertRedirect("/admin/channels/{$channel->id}/edit");
        $response->assertSessionHasErrors('x_handle');
    }

    public function test_admin_can_set_channel_specific_x_search_keywords(): void
    {
        $channel = Channel::factory()->create(['x_handle' => 'some_user']);

        $this->actingAs($this->admin)->put("/admin/channels/{$channel->id}", [
            'color' => '#00FF00',
            'is_active' => true,
            'x_handle' => 'some_user',
            'x_search_keywords' => ' 歌枠, 雑談　歌枠 ',
        ])->assertRedirect('/admin/channels');

        $this->assertDatabaseHas('channels', ['id' => $channel->id, 'x_search_keywords' => '歌枠,雑談']);

        $this->actingAs($this->admin)->put("/admin/channels/{$channel->id}", [
            'color' => '#00FF00',
            'is_active' => true,
            'x_handle' => 'some_user',
            'x_search_keywords' => '',
        ])->assertRedirect('/admin/channels');

        $this->assertDatabaseHas('channels', ['id' => $channel->id, 'x_search_keywords' => null]);
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
