<?php

namespace Tests\Feature\Api;

use App\Models\Channel;
use App\Models\Stream;
use App\Models\Tag;
use App\Models\User;
use App\Support\StreamTagger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TermClassifyTest extends TestCase
{
    use RefreshDatabase;

    private function seedStreams(): void
    {
        Channel::factory()->create();
        Stream::factory()->create(['title' => '【Woodo】はじめて']);
        Stream::factory()->create(['title' => '【Woodo/初見歓迎】2回目']);
        Stream::factory()->create(['title' => '【マイクラ】建築']);
        (new StreamTagger())->retagAll();
    }

    public function test_guest_can_classify_a_term_as_a_new_game_and_every_stream_gets_the_tag(): void
    {
        $this->seedStreams();
        $this->assertDatabaseHas('unmatched_terms', ['term' => 'woodo']);

        $response = $this->postJson('/api/terms/classify', ['term' => 'Woodo', 'kind' => 'game']);

        $response->assertCreated()->assertJsonPath('tag.name', 'Woodo')->assertJsonPath('tag.kind', 'game');
        $this->assertDatabaseHas('tag_aliases', ['alias' => 'woodo']);
        $this->assertSame(2, Tag::where('name', 'Woodo')->first()->streams()->count());
        $this->assertDatabaseMissing('unmatched_terms', ['term' => 'woodo']);
        $this->assertDatabaseHas('unmatched_terms', ['term' => '初見歓迎']);
        $this->assertDatabaseHas('activity_logs', ['user_id' => null, 'action' => 'classify_term']);
    }

    public function test_a_term_can_be_attached_to_an_existing_tag_as_an_alias(): void
    {
        $this->seedStreams();
        $mc = Tag::createWithAlias('Minecraft', 'game');

        $this->postJson('/api/terms/classify', ['term' => 'マイクラ', 'kind' => 'game', 'tag_id' => $mc->id])
            ->assertCreated()->assertJsonPath('tag.id', $mc->id);

        $this->assertDatabaseHas('tag_aliases', ['tag_id' => $mc->id, 'alias' => 'マイクラ']);
        $this->assertSame(['Minecraft'], Stream::where('title', '【マイクラ】建築')->first()->tags->pluck('name')->all());
        $this->assertSame(1, Tag::count());
    }

    public function test_a_term_can_be_ignored_and_a_classified_term_cannot_be_reclassified(): void
    {
        $this->seedStreams();

        $this->postJson('/api/terms/classify', ['term' => '初見歓迎', 'kind' => 'ignore'])->assertCreated()->assertJsonPath('tag', null);
        $this->assertDatabaseHas('ignored_terms', ['term' => '初見歓迎']);
        $this->assertDatabaseMissing('unmatched_terms', ['term' => '初見歓迎']);

        $this->postJson('/api/terms/classify', ['term' => '初見歓迎', 'kind' => 'category'])->assertStatus(409);
        $this->postJson('/api/terms/classify', ['term' => 'Woodo', 'kind' => 'game'])->assertCreated();
        $this->postJson('/api/terms/classify', ['term' => 'WOODO', 'kind' => 'category'])->assertStatus(409);
    }

    public function test_validation_and_bans(): void
    {
        $this->seedStreams();

        $this->postJson('/api/terms/classify', ['term' => 'Woodo', 'kind' => 'other'])->assertStatus(422)->assertJsonValidationErrors('kind');
        $this->postJson('/api/terms/classify', ['term' => 'x', 'kind' => 'game'])->assertStatus(422)->assertJsonValidationErrors('term');
        $this->actingAs(User::factory()->create(['is_banned' => true]))
            ->postJson('/api/terms/classify', ['term' => 'Woodo', 'kind' => 'game'])->assertForbidden();
    }
}
