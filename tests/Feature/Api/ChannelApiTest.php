<?php

namespace Tests\Feature\Api;

use App\Models\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChannelApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_channels_endpoint_returns_active_channels(): void
    {
        Channel::factory()->create(['name' => 'Active Ch', 'is_active' => true]);
        Channel::factory()->create(['name' => 'Inactive Ch', 'is_active' => false]);

        $response = $this->getJson('/api/channels');

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['name' => 'Active Ch']);
    }

    public function test_channels_endpoint_includes_x_handle(): void
    {
        Channel::factory()->create(['name' => 'With X', 'x_handle' => 'some_user']);
        Channel::factory()->create(['name' => 'Without X', 'x_handle' => null]);

        $response = $this->getJson('/api/channels');

        $response->assertOk();
        $response->assertJsonFragment(['name' => 'With X', 'x_handle' => 'some_user']);
        $response->assertJsonFragment(['name' => 'Without X', 'x_handle' => null]);
    }

    public function test_channels_endpoint_includes_channel_specific_x_search_keywords_as_a_list(): void
    {
        Channel::factory()->create(['name' => 'Kw', 'x_handle' => 'some_user', 'x_search_keywords' => '歌枠,雑談']);
        Channel::factory()->create(['name' => 'NoKw', 'x_handle' => 'other', 'x_search_keywords' => null]);

        $response = $this->getJson('/api/channels');

        $response->assertOk();
        $response->assertJsonFragment(['name' => 'Kw', 'x_search_keywords' => ['歌枠', '雑談']]);
        $response->assertJsonFragment(['name' => 'NoKw', 'x_search_keywords' => []]);
    }
}
