<?php

namespace Tests\Feature\Api;

use App\Models\Channel;
use App\Models\Group;
use App\Models\Stream;
use App\Models\Tag;
use App\Models\VideoPost;
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

        $this->assertSame('week', $response->json('period'));
        $this->assertSame('2026-09-21', $response->json('start'));
        $this->assertSame('2026-09-27', $response->json('end'));
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
        // An explicit week (both spellings of the anchor date).
        $prev = collect($this->getJson('/api/trends?group=aaaa&week=2026-09-16')->json('tags'))->keyBy('name');
        $this->assertSame(1, $prev['原神']['count']);
        $this->assertSame(0, $prev['原神']['prev_count']);
        $this->assertSame(1, collect($this->getJson('/api/trends?group=aaaa&period=week&date=2026-09-16')->json('tags'))->firstWhere('name', '原神')['count']);
    }

    public function test_member_activity_counts_streams_shorts_uploads_clips_and_guest_appearances(): void
    {
        $group = Group::factory()->create(['slug' => 'aaaa']);
        $a1 = Channel::factory()->create(['name' => 'A1']);
        $a2 = Channel::factory()->create(['name' => 'A2']);
        $b1 = Channel::factory()->create(['name' => 'B1']);
        $a1->groups()->attach($group);
        $a2->groups()->attach($group);
        Stream::factory()->create(['channel_id' => $a1->id, 'title' => 's1', 'scheduled_at' => '2026-09-22 11:00:00']);
        Stream::factory()->create(['channel_id' => $a1->id, 'title' => 's2', 'scheduled_at' => '2026-09-23 11:00:00']);
        Stream::factory()->create(['channel_id' => $a1->id, 'title' => 'short', 'type' => 'short', 'status' => 'completed', 'scheduled_at' => '2026-09-23 12:00:00']);
        Stream::factory()->create(['channel_id' => $a1->id, 'title' => 'last week', 'scheduled_at' => '2026-09-15 11:00:00']);
        Stream::factory()->create(['channel_id' => $a2->id, 'title' => 'upload', 'type' => 'upload', 'status' => 'completed', 'scheduled_at' => '2026-09-24 11:00:00']);
        Stream::factory()->create(['channel_id' => $b1->id, 'title' => 'other group', 'scheduled_at' => '2026-09-24 11:00:00']);
        $clip = VideoPost::create(['video_id' => 'clip0000001', 'kind' => 'clip', 'title' => 'clip', 'source_channel_id' => 'UC_x', 'source_channel_name' => 'x', 'published_at' => '2026-09-25 10:00:00']);
        $clip->channels()->attach([$a1->id, $a2->id]);
        $guest = VideoPost::create(['video_id' => 'guest000001', 'kind' => 'guest', 'title' => 'guest', 'source_channel_id' => 'UC_y', 'source_channel_name' => 'y', 'published_at' => '2026-09-26 10:00:00']);
        $guest->channels()->attach($a2);
        $old = VideoPost::create(['video_id' => 'oldclip0001', 'kind' => 'clip', 'title' => 'old', 'source_channel_id' => 'UC_x', 'source_channel_name' => 'x', 'published_at' => '2026-09-10 10:00:00']);
        $old->channels()->attach($a1);

        $members = $this->getJson('/api/trends?group=aaaa')->assertOk()->json('members');

        $this->assertSame(['A1', 'A2'], array_column($members, 'name'));
        $this->assertSame(['streams' => 2, 'prev_streams' => 1, 'shorts' => 1, 'uploads' => 0, 'clips' => 1, 'guests' => 0],
            array_intersect_key($members[0], array_flip(['streams', 'prev_streams', 'shorts', 'uploads', 'clips', 'guests'])));
        $this->assertSame(['streams' => 0, 'prev_streams' => 0, 'shorts' => 0, 'uploads' => 1, 'clips' => 1, 'guests' => 1],
            array_intersect_key($members[1], array_flip(['streams', 'prev_streams', 'shorts', 'uploads', 'clips', 'guests'])));
        $this->assertSame([], $this->getJson("/api/trends?group=aaaa&channels=0")->json('members'));
    }

    public function test_month_period_aggregates_the_jst_month_and_compares_with_the_previous_month(): void
    {
        $channel = Channel::factory()->create(['name' => 'A1']);
        Tag::createWithAlias('原神', 'game');
        Stream::factory()->create(['channel_id' => $channel->id, 'title' => '【原神】8月末', 'scheduled_at' => '2026-08-31 14:59:00']); // Aug 31 23:59 JST
        Stream::factory()->create(['channel_id' => $channel->id, 'title' => '【原神】9月頭', 'scheduled_at' => '2026-08-31 15:00:00']); // Sep 1 00:00 JST
        Stream::factory()->create(['channel_id' => $channel->id, 'title' => '【原神】9月中', 'scheduled_at' => '2026-09-15 11:00:00']);
        Stream::factory()->create(['channel_id' => $channel->id, 'title' => '【原神】9月末', 'scheduled_at' => '2026-09-30 14:59:00']); // Sep 30 23:59 JST
        Stream::factory()->create(['channel_id' => $channel->id, 'title' => '【原神】10月', 'scheduled_at' => '2026-09-30 15:00:00']); // Oct 1 00:00 JST
        (new StreamTagger())->retagAll();

        $response = $this->getJson('/api/trends?period=month&date=2026-09-10')->assertOk();

        $this->assertSame('month', $response->json('period'));
        $this->assertSame('2026-09-01', $response->json('start'));
        $this->assertSame('2026-09-30', $response->json('end'));
        $genshin = collect($response->json('tags'))->firstWhere('name', '原神');
        $this->assertSame(3, $genshin['count']);
        $this->assertSame(1, $genshin['prev_count']);

        // Defaults to the current month when no date is given.
        $this->assertSame('2026-09-01', $this->getJson('/api/trends?period=month')->json('start'));
        $this->getJson('/api/trends?period=year')->assertStatus(422);
    }

    public function test_lists_unmatched_terms_by_frequency_scoped_to_the_group(): void
    {
        $groupA = Group::factory()->create(['slug' => 'aaaa']);
        $groupB = Group::factory()->create(['slug' => 'bbbb']);
        $a = Channel::factory()->create();
        $b = Channel::factory()->create();
        $a->groups()->attach($groupA);
        $b->groups()->attach($groupB);
        Stream::factory()->create(['channel_id' => $a->id, 'title' => '【Woodo】1', 'scheduled_at' => '2026-09-22 11:00:00']);
        Stream::factory()->create(['channel_id' => $a->id, 'title' => '【Woodo】2', 'scheduled_at' => '2026-09-22 12:00:00']);
        Stream::factory()->create(['channel_id' => $a->id, 'title' => '【歌枠】', 'scheduled_at' => '2026-09-22 13:00:00']);
        Stream::factory()->create(['channel_id' => $b->id, 'title' => '【#天和うる】別グループの語', 'scheduled_at' => '2026-09-22 13:00:00']);
        (new StreamTagger())->retagAll();

        $all = $this->getJson('/api/trends')->assertOk();
        // Most frequent first; ties in term (byte) order.
        $this->assertSame(['woodo', '天和うる', '歌枠'], array_column($all->json('unmatched'), 'term'));
        $this->assertSame([['term' => 'woodo', 'display' => 'Woodo', 'count' => 2], ['term' => '天和うる', 'display' => '#天和うる', 'count' => 1], ['term' => '歌枠', 'display' => '歌枠', 'count' => 1]], $all->json('unmatched'));
        $this->assertSame([], $all->json('tags'));

        $this->assertSame(['woodo', '歌枠'], array_column($this->getJson('/api/trends?group=aaaa')->json('unmatched'), 'term'));
        $this->assertSame(['天和うる'], array_column($this->getJson('/api/trends?group=bbbb')->json('unmatched'), 'term'));
    }

    public function test_channels_parameter_narrows_within_the_group_but_never_beyond_it(): void
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
        foreach ([$a1, $a2, $b1] as $ch) {
            Stream::factory()->create(['channel_id' => $ch->id, 'title' => '【原神】', 'scheduled_at' => '2026-09-22 11:00:00']);
            Stream::factory()->create(['channel_id' => $ch->id, 'title' => '【Woodo】', 'scheduled_at' => '2026-09-23 11:00:00']);
        }
        (new StreamTagger())->retagAll();

        // Sub-group toggle / hidden channels: only A1 is on show.
        $narrowed = $this->getJson("/api/trends?group=aaaa&channels={$a1->id},{$b1->id}")->assertOk();
        $genshin = collect($narrowed->json('tags'))->firstWhere('name', '原神');
        $this->assertSame(1, $genshin['count']);
        $this->assertSame(['A1'], array_column($genshin['members'], 'name'));
        $this->assertSame([['term' => 'woodo', 'display' => 'Woodo', 'count' => 1]], $narrowed->json('unmatched'));

        // Without the parameter the whole group counts.
        $this->assertSame(2, collect($this->getJson('/api/trends?group=aaaa')->json('tags'))->firstWhere('name', '原神')['count']);
    }
}
