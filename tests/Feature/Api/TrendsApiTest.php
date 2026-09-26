<?php

namespace Tests\Feature\Api;

use App\Models\Channel;
use App\Models\Group;
use App\Models\Stream;
use App\Models\Tag;
use App\Support\StreamTagger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TrendsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-23 03:00:00', 'UTC')); // Wed 12:00 JST; week = Mon 9/21 … Sun 9/27
    }

    public function test_counts_streams_per_tag_for_the_jst_week_with_members_and_previous_week(): void
    {
        $groupA = Group::factory()->create(['slug' => 'aaaa']);
        $groupB = Group::factory()->create(['slug' => 'bbbb']);
        $a1 = Channel::factory()->create(['name' => 'A1']);
        $a2 = Channel::factory()->create(['name' => 'A2']);
        $b1 = Channel::factory()->create(['name' => 'B1']);
        $a1->groups()->attach($groupA);
        $a2->groups()->attach($groupA);
        $b1->groups()->attach($groupB);
        Tag::createWithAlias('原神', 'game');
        Tag::createWithAlias('雑談', 'category');

        Stream::factory()->create(['channel_id' => $a1->id, 'title' => '【原神】探索', 'scheduled_at' => '2026-09-22 11:00:00']);
        Stream::factory()->create(['channel_id' => $a2->id, 'title' => '【原神】ガチャ', 'scheduled_at' => '2026-09-23 11:00:00']);
        Stream::factory()->create(['channel_id' => $a1->id, 'title' => '【雑談】夜', 'scheduled_at' => '2026-09-24 11:00:00']);
        Stream::factory()->create(['channel_id' => $b1->id, 'title' => '【原神】別グループ', 'scheduled_at' => '2026-09-22 11:00:00']);
        Stream::factory()->create(['channel_id' => $a1->id, 'title' => '【原神】先週', 'scheduled_at' => '2026-09-15 11:00:00']);
        Stream::factory()->create(['channel_id' => $a1->id, 'title' => '【原神】ショート', 'type' => 'short', 'status' => 'completed', 'scheduled_at' => '2026-09-22 12:00:00']);
        // Sunday 23:30 JST is still this week; Monday 00:30 JST next week is not.
        Stream::factory()->create(['channel_id' => $a2->id, 'title' => '【雑談】日曜深夜', 'scheduled_at' => '2026-09-27 14:30:00']);
        Stream::factory()->create(['channel_id' => $a2->id, 'title' => '【雑談】翌週', 'scheduled_at' => '2026-09-27 15:30:00']);
        (new StreamTagger())->retagAll();

        $response = $this->getJson('/api/trends?group=aaaa')->assertOk();

        $this->assertSame('2026-09-21', $response->json('week_start'));
        $this->assertSame('2026-09-27', $response->json('week_end'));
        $tags = collect($response->json('tags'))->keyBy('name');
        $this->assertSame(2, $tags['原神']['count']);
        $this->assertSame(1, $tags['原神']['prev_count']);
        $this->assertSame('game', $tags['原神']['kind']);
        $this->assertEqualsCanonicalizing(['A1', 'A2'], array_column($tags['原神']['members'], 'name'));
        $this->assertSame(2, $tags['雑談']['count']);
        $this->assertSame(0, $tags['雑談']['prev_count']);
        $this->assertSame(['原神', '雑談'], $response->json('tags.*.name'));

        // Whole site: the other group's stream counts too.
        $this->assertSame(3, collect($this->getJson('/api/trends')->json('tags'))->firstWhere('name', '原神')['count']);
        // An explicit week.
        $prev = collect($this->getJson('/api/trends?group=aaaa&week=2026-09-16')->json('tags'))->keyBy('name');
        $this->assertSame(1, $prev['原神']['count']);
        $this->assertSame(0, $prev['原神']['prev_count']);
    }

    public function test_lists_unmatched_terms_by_frequency(): void
    {
        Channel::factory()->create();
        Stream::factory()->create(['title' => '【Woodo】1', 'scheduled_at' => '2026-09-22 11:00:00']);
        Stream::factory()->create(['title' => '【Woodo】2', 'scheduled_at' => '2026-09-22 12:00:00']);
        Stream::factory()->create(['title' => '【歌枠】', 'scheduled_at' => '2026-09-22 13:00:00']);
        (new StreamTagger())->retagAll();

        $response = $this->getJson('/api/trends')->assertOk();

        $this->assertSame([['term' => 'woodo', 'display' => 'Woodo', 'count' => 2], ['term' => '歌枠', 'display' => '歌枠', 'count' => 1]], $response->json('unmatched'));
        $this->assertSame([], $response->json('tags'));
    }
}
