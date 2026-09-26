<?php

namespace Tests\Feature\Api;

use App\Models\Channel;
use App\Models\Stream;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StreamTagApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_add_a_new_tag_by_name_and_it_shows_up_in_the_streams_api(): void
    {
        $channel = Channel::factory()->create();
        $stream = Stream::factory()->create(['channel_id' => $channel->id, 'title' => '久しぶりにあの歌う枠', 'scheduled_at' => '2026-09-22 11:00:00']);
        $other = Stream::factory()->create(['channel_id' => $channel->id, 'title' => '【歌枠】別の配信', 'scheduled_at' => '2026-09-23 11:00:00']);

        $response = $this->postJson("/api/streams/{$stream->id}/tags", ['name' => '歌枠', 'kind' => 'category']);

        $response->assertCreated()->assertJsonPath('tags.0.name', '歌枠');
        $this->assertDatabaseHas('stream_tag', ['stream_id' => $stream->id, 'source' => 'manual']);
        $this->assertDatabaseHas('activity_logs', ['user_id' => null, 'action' => 'add_stream_tag', 'target_id' => $stream->id]);
        // The new name is a dictionary entry now: the other stream that mentions it is tagged automatically.
        $this->assertDatabaseHas('stream_tag', ['stream_id' => $other->id, 'source' => 'auto']);

        $events = collect($this->getJson('/api/streams?start=2026-09-01&end=2026-09-30')->json());
        $this->assertSame([['id' => Tag::first()->id, 'name' => '歌枠', 'kind' => 'category']], $events->firstWhere('id', $stream->id)['extendedProps']['tags']);
    }

    public function test_existing_tag_by_id_and_duplicate_is_refused(): void
    {
        $stream = Stream::factory()->create();
        $tag = Tag::createWithAlias('Minecraft', 'game');

        $this->postJson("/api/streams/{$stream->id}/tags", ['tag_id' => $tag->id])->assertCreated();
        $this->postJson("/api/streams/{$stream->id}/tags", ['tag_id' => $tag->id])->assertStatus(409);
        $this->postJson("/api/streams/{$stream->id}/tags", [])->assertStatus(422)->assertJsonValidationErrors('tag_id');
    }

    public function test_only_admins_can_remove_a_tag(): void
    {
        $stream = Stream::factory()->create();
        $tag = Tag::createWithAlias('Minecraft', 'game');
        $stream->tags()->attach($tag->id, ['source' => 'manual']);
        $admin = User::factory()->admin()->create();

        $this->deleteJson("/api/streams/{$stream->id}/tags/{$tag->id}")->assertUnauthorized();
        $this->actingAs(User::factory()->create())->deleteJson("/api/streams/{$stream->id}/tags/{$tag->id}")->assertForbidden();
        $this->assertDatabaseHas('stream_tag', ['stream_id' => $stream->id, 'tag_id' => $tag->id]);

        $this->actingAs($admin)->deleteJson("/api/streams/{$stream->id}/tags/{$tag->id}")->assertOk();
        $this->assertDatabaseMissing('stream_tag', ['stream_id' => $stream->id, 'tag_id' => $tag->id]);
        $this->assertDatabaseHas('activity_logs', ['user_id' => $admin->id, 'action' => 'remove_stream_tag']);
    }
}
