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
}
