<?php

namespace Tests\Feature\Api;

use App\Models\Channel;
use App\Models\Group;
use App\Models\Stream;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupApiTest extends TestCase
{
    use RefreshDatabase;

    private Group $group;
    private Channel $inGroup;
    private Channel $outOfGroup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->group = Group::factory()->create(['slug' => 'aaaa']);
        $this->inGroup = Channel::factory()->create(['name' => 'In Group']);
        $this->outOfGroup = Channel::factory()->create(['name' => 'Out Of Group']);
        $this->group->channels()->attach($this->inGroup);

        Stream::factory()->create(['channel_id' => $this->inGroup->id, 'scheduled_at' => '2026-09-15 19:00:00']);
        Stream::factory()->create(['channel_id' => $this->outOfGroup->id, 'scheduled_at' => '2026-09-15 20:00:00']);
    }

    public function test_streams_endpoint_filters_by_group_slug(): void
    {
        $response = $this->getJson('/api/streams?start=2026-09-01&end=2026-09-30&group=aaaa');

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['channel_id' => $this->inGroup->id]);
    }

    public function test_streams_endpoint_without_group_returns_all(): void
    {
        $response = $this->getJson('/api/streams?start=2026-09-01&end=2026-09-30');

        $response->assertOk();
        $response->assertJsonCount(2);
    }

    public function test_streams_endpoint_returns_404_for_unknown_group(): void
    {
        $this->getJson('/api/streams?start=2026-09-01&end=2026-09-30&group=nope')->assertNotFound();
    }

    public function test_channels_endpoint_filters_by_group_slug(): void
    {
        $response = $this->getJson('/api/channels?group=aaaa');

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['name' => 'In Group']);
    }

    public function test_channels_endpoint_returns_404_for_unknown_group(): void
    {
        $this->getJson('/api/channels?group=nope')->assertNotFound();
    }
}
