<?php

namespace Tests\Feature\Api;

use App\Models\Channel;
use App\Models\Stream;
use App\Models\Tag;
use App\Models\TagAlias;
use App\Models\User;
use App\Support\StreamTagger;
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

    public function test_a_new_tag_tags_only_matching_streams_now_and_leaves_the_full_retag_for_the_next_fetch(): void
    {
        $channel = Channel::factory()->create();
        $stream = Stream::factory()->create(['channel_id' => $channel->id, 'title' => '久しぶりの配信']);
        $other = Stream::factory()->create(['channel_id' => $channel->id, 'title' => '【歌枠】別の配信']);
        \App\Models\UnmatchedTerm::create(['term' => '歌枠', 'display' => '歌枠', 'count' => 1, 'last_seen_at' => now()]);
        \App\Models\UnmatchedTerm::create(['term' => '雑談', 'display' => '雑談', 'count' => 1, 'last_seen_at' => now()]);

        $this->postJson("/api/streams/{$stream->id}/tags", ['name' => '歌枠', 'kind' => 'category'])->assertCreated();

        $this->assertDatabaseHas('stream_tag', ['stream_id' => $other->id, 'source' => 'auto']);
        // The new tag's own term leaves the unmatched list at once; the rest waits for the full rebuild.
        $this->assertDatabaseMissing('unmatched_terms', ['term' => '歌枠']);
        $this->assertDatabaseHas('unmatched_terms', ['term' => '雑談']);
        $this->assertNotNull(\App\Models\Setting::get(StreamTagger::FULL_RETAG_PENDING_KEY));

        $tagger = new StreamTagger();
        $this->assertTrue($tagger->retagAllIfPending());
        $this->assertNull(\App\Models\Setting::get(StreamTagger::FULL_RETAG_PENDING_KEY));
        $this->assertDatabaseMissing('unmatched_terms', ['term' => '雑談']);   // rebuilt from titles: no 【雑談】 anywhere
        $this->assertFalse($tagger->retagAllIfPending());
    }

    public function test_adding_an_existing_tag_does_not_schedule_a_full_retag(): void
    {
        $stream = Stream::factory()->create();
        $tag = Tag::createWithAlias('Minecraft', 'game');

        $this->postJson("/api/streams/{$stream->id}/tags", ['tag_id' => $tag->id])->assertCreated();

        $this->assertNull(\App\Models\Setting::get(StreamTagger::FULL_RETAG_PENDING_KEY));
    }

    public function test_existing_tag_by_id_and_duplicate_is_refused(): void
    {
        $stream = Stream::factory()->create();
        $tag = Tag::createWithAlias('Minecraft', 'game');

        $this->postJson("/api/streams/{$stream->id}/tags", ['tag_id' => $tag->id])->assertCreated();
        $this->postJson("/api/streams/{$stream->id}/tags", ['tag_id' => $tag->id])->assertStatus(409);
        $this->postJson("/api/streams/{$stream->id}/tags", [])->assertStatus(422)->assertJsonValidationErrors('tag_id');
    }

    public function test_tag_list_includes_aliases_for_the_picker(): void
    {
        $tag = Tag::createWithAlias('Minecraft', 'game');
        TagAlias::create(['tag_id' => $tag->id, 'alias' => 'マイクラ']);

        $this->getJson('/api/tags')->assertOk()->assertExactJson([
            ['id' => $tag->id, 'name' => 'Minecraft', 'kind' => 'game', 'aliases' => ['minecraft', 'マイクラ']],
        ]);
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

    public function test_a_removed_dictionary_tag_stays_off_until_someone_adds_it_back(): void
    {
        $tag = Tag::createWithAlias('Minecraft', 'game');
        $stream = Stream::factory()->create(['title' => '【Minecraft】建築']);
        $tagger = app(StreamTagger::class);
        $tagger->retagAll();
        $this->assertSame(['Minecraft'], $stream->fresh()->tags->pluck('name')->all());

        $this->actingAs(User::factory()->admin()->create())
            ->deleteJson("/api/streams/{$stream->id}/tags/{$tag->id}")->assertOk()->assertJsonPath('tags', []);

        // The title still matches, but the next fetch / retag must not put it back.
        $tagger->retagAll();
        $this->assertSame([], $stream->fresh()->tags->pluck('name')->all());

        // Adding it by hand clears the removal.
        $this->postJson("/api/streams/{$stream->id}/tags", ['tag_id' => $tag->id])->assertCreated();
        $this->assertDatabaseMissing('stream_tag_removals', ['stream_id' => $stream->id, 'tag_id' => $tag->id]);
        $tagger->retagAll();
        $this->assertSame(['Minecraft'], $stream->fresh()->tags->pluck('name')->all());
    }
}
