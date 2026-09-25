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
        $channel = Channel::factory()->create([
            'name' => 'Test Ch',
            'color' => '#FF0000',
            'thumbnail_url' => 'https://example.com/ch.jpg',
        ]);
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
            'channel_thumbnail_url' => 'https://example.com/ch.jpg',
        ]);
        $response->assertJsonStructure([
            ['id', 'title', 'start', 'url', 'color', 'extendedProps' => ['channel_name', 'channel_id', 'channel_thumbnail_url', 'status', 'type', 'is_members_only']],
        ]);
        $response->assertJsonFragment(['is_members_only' => false]);
    }

    public function test_streams_endpoint_exposes_created_at_so_the_page_can_badge_new_entries(): void
    {
        $channel = Channel::factory()->create();
        Stream::factory()->create([
            'channel_id' => $channel->id,
            'scheduled_at' => '2026-09-15 19:00:00',
            'created_at' => '2026-09-14 08:30:00',
        ]);

        $response = $this->getJson('/api/streams?start=2026-09-01&end=2026-09-30');

        $response->assertOk();
        $this->assertSame('2026-09-14T08:30:00+00:00', $response->json('0.extendedProps.created_at'));
    }

    public function test_streams_endpoint_marks_members_only_streams(): void
    {
        $channel = Channel::factory()->create();
        Stream::factory()->create([
            'channel_id' => $channel->id,
            'title' => '【メン限】secret',
            'scheduled_at' => '2026-09-15 19:00:00',
            'is_members_only' => true,
        ]);

        $this->getJson('/api/streams?start=2026-09-01&end=2026-09-30')
            ->assertOk()
            ->assertJsonFragment(['title' => '【メン限】secret', 'is_members_only' => true]);
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

    public function test_streams_endpoint_includes_streams_on_end_date(): void
    {
        $channel = Channel::factory()->create();
        Stream::factory()->create([
            'channel_id' => $channel->id,
            'scheduled_at' => '2026-09-30 19:00:00',
        ]);

        $response = $this->getJson('/api/streams?start=2026-09-01&end=2026-09-30');

        $response->assertOk();
        $response->assertJsonCount(1);
    }

    public function test_streams_endpoint_excludes_streams_after_end_date(): void
    {
        $channel = Channel::factory()->create();
        Stream::factory()->create([
            'channel_id' => $channel->id,
            'scheduled_at' => '2026-10-01 00:30:00',
        ]);

        $response = $this->getJson('/api/streams?start=2026-09-01&end=2026-09-30');

        $response->assertOk();
        $response->assertJsonCount(0);
    }

    public function test_streams_endpoint_returns_shorts_url_for_short_type(): void
    {
        $channel = Channel::factory()->create();
        Stream::factory()->create([
            'channel_id' => $channel->id,
            'video_id' => 'short_vid',
            'scheduled_at' => '2026-09-15 12:00:00',
            'type' => 'short',
            'status' => 'completed',
        ]);

        $response = $this->getJson('/api/streams?start=2026-09-01&end=2026-09-30');

        $response->assertOk();
        $response->assertJsonFragment(['url' => 'https://www.youtube.com/shorts/short_vid']);
        $response->assertJsonFragment(['type' => 'short']);
    }

    public function test_streams_endpoint_accepts_iso8601_start_with_offset(): void
    {
        $channel = Channel::factory()->create();
        // 2026-08-29 20:00:00 UTC = 2026-08-30 05:00:00 JST (inside the grid).
        Stream::factory()->create([
            'channel_id' => $channel->id,
            'scheduled_at' => '2026-08-29 20:00:00',
        ]);
        // 2026-08-29 10:00:00 UTC = 2026-08-29 19:00:00 JST (before the grid).
        Stream::factory()->create([
            'channel_id' => $channel->id,
            'scheduled_at' => '2026-08-29 10:00:00',
        ]);

        $response = $this->getJson(
            '/api/streams?start=' . urlencode('2026-08-30T00:00:00+09:00')
            . '&end=' . urlencode('2026-10-05T00:00:00+09:00')
        );

        $response->assertOk();
        $response->assertJsonCount(1);
    }
}
