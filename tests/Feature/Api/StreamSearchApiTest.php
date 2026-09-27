<?php

namespace Tests\Feature\Api;

use App\Models\Channel;
use App\Models\Group;
use App\Models\Stream;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StreamSearchApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_finds_streams_by_title_substring_newest_first_with_channel_and_tags(): void
    {
        $channel = Channel::factory()->create(['name' => 'A1', 'color' => '#123456']);
        $tag = Tag::createWithAlias('Woodo', 'game');
        $old = Stream::factory()->create(['channel_id' => $channel->id, 'title' => '【Woodo】はじめて', 'scheduled_at' => '2026-09-10 11:00:00']);
        $new = Stream::factory()->create(['channel_id' => $channel->id, 'title' => '【 Woodo / 初見歓迎 】2回目', 'scheduled_at' => '2026-09-20 11:00:00']);
        Stream::factory()->create(['channel_id' => $channel->id, 'title' => '【雑談】関係ない', 'scheduled_at' => '2026-09-21 11:00:00']);
        $new->tags()->attach($tag->id, ['source' => 'auto']);

        $response = $this->getJson('/api/streams/search?q=Woodo')->assertOk();

        $this->assertSame([$new->id, $old->id], $response->json('*.id'));
        $this->assertSame('A1', $response->json('0.channel.name'));
        $this->assertSame('#123456', $response->json('0.channel.color'));
        $this->assertSame([['id' => $tag->id, 'name' => 'Woodo', 'kind' => 'game']], $response->json('0.tags'));
        $this->assertSame([], $response->json('1.tags'));
        $this->assertSame('https://www.youtube.com/watch?v=' . $new->video_id, $response->json('0.url'));
    }

    public function test_scoped_to_the_group_and_validated(): void
    {
        $groupA = Group::factory()->create(['slug' => 'aaaa']);
        $a = Channel::factory()->create();
        $b = Channel::factory()->create();
        $a->groups()->attach($groupA);
        Stream::factory()->create(['channel_id' => $a->id, 'title' => 'Woodo in A']);
        Stream::factory()->create(['channel_id' => $b->id, 'title' => 'Woodo in B']);

        $this->assertSame(['Woodo in A'], $this->getJson('/api/streams/search?q=Woodo&group=aaaa')->assertOk()->json('*.title'));
        $this->assertCount(2, $this->getJson('/api/streams/search?q=woodo')->json());   // case-insensitive like
        $this->getJson('/api/streams/search')->assertStatus(422);
        $this->getJson('/api/streams/search?q=' . urlencode('100%'))->assertOk()->assertJsonCount(0);   // wildcards are literal
    }
}
