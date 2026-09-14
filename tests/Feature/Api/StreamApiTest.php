<?php

namespace Tests\Feature\Api;

use App\Models\Channel;
use App\Models\Stream;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StreamApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_streams_endpoint_returns_fullcalendar_format(): void
    {
        $channel = Channel::factory()->create(['name' => 'Test Ch', 'color' => '#FF0000']);
        Stream::factory()->create([
            'channel_id' => $channel->id,
            'video_id' => 'vid1',
            'title' => 'Test Stream',
            'scheduled_at' => '2026-09-15 19:00:00',
            'status' => 'upcoming',
        ]);

        $response = $this->getJson('/api/streams?start=2026-09-01&end=2026-09-30');

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonFragment([
            'title' => 'Test Stream',
            'color' => '#FF0000',
        ]);
        $response->assertJsonFragment([
            'channel_id' => $channel->id,
        ]);
        $response->assertJsonStructure([
            ['id', 'title', 'start', 'url', 'color', 'extendedProps' => ['channel_name', 'channel_id', 'status']],
        ]);
    }

    public function test_streams_endpoint_filters_by_date_range(): void
    {
        $channel = Channel::factory()->create();
        Stream::factory()->create([
            'channel_id' => $channel->id,
            'scheduled_at' => '2026-09-15 19:00:00',
        ]);
        Stream::factory()->create([
            'channel_id' => $channel->id,
            'scheduled_at' => '2026-10-15 19:00:00',
        ]);

        $response = $this->getJson('/api/streams?start=2026-09-01&end=2026-09-30');

        $response->assertOk();
        $response->assertJsonCount(1);
    }

    public function test_streams_endpoint_excludes_inactive_channels(): void
    {
        $active = Channel::factory()->create(['is_active' => true]);
        $inactive = Channel::factory()->create(['is_active' => false]);
        Stream::factory()->create([
            'channel_id' => $active->id,
            'scheduled_at' => '2026-09-15 19:00:00',
        ]);
        Stream::factory()->create([
            'channel_id' => $inactive->id,
            'scheduled_at' => '2026-09-15 19:00:00',
        ]);

        $response = $this->getJson('/api/streams?start=2026-09-01&end=2026-09-30');

        $response->assertOk();
        $response->assertJsonCount(1);
    }
}
