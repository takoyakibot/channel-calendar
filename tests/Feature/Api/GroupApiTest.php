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

    public function test_parent_group_includes_descendant_channels(): void
    {
        $child = Group::factory()->create(['slug' => 'bbbb', 'parent_id' => $this->group->id]);
        $grandchild = Group::factory()->create(['slug' => 'cccc', 'parent_id' => $child->id]);
        $childChannel = Channel::factory()->create(['name' => 'Child Ch']);
        $grandchildChannel = Channel::factory()->create(['name' => 'Grandchild Ch']);
        $child->channels()->attach($childChannel);
        $grandchild->channels()->attach($grandchildChannel);
        Stream::factory()->create(['channel_id' => $childChannel->id, 'scheduled_at' => '2026-09-16 19:00:00']);
        Stream::factory()->create(['channel_id' => $grandchildChannel->id, 'scheduled_at' => '2026-09-17 19:00:00']);

        $this->getJson('/api/channels?group=aaaa')
            ->assertOk()
            ->assertJsonCount(3);

        $this->getJson('/api/streams?start=2026-09-01&end=2026-09-30&group=aaaa')
            ->assertOk()
            ->assertJsonCount(3);
    }

    public function test_nested_group_path_shows_only_its_subtree(): void
    {
        $child = Group::factory()->create(['slug' => 'bbbb', 'parent_id' => $this->group->id]);
        $childChannel = Channel::factory()->create(['name' => 'Child Ch']);
        $child->channels()->attach($childChannel);
        Stream::factory()->create(['channel_id' => $childChannel->id, 'scheduled_at' => '2026-09-16 19:00:00']);

        $this->getJson('/api/channels?group=aaaa/bbbb')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['name' => 'Child Ch']);

        $this->getJson('/api/streams?start=2026-09-01&end=2026-09-30&group=aaaa/bbbb')
            ->assertOk()
            ->assertJsonCount(1);

        $this->getJson('/api/channels?group=bbbb')->assertNotFound();
    }
}
