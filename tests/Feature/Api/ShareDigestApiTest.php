<?php

namespace Tests\Feature\Api;

use App\Models\Channel;
use App\Models\Group;
use App\Models\Stream;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ShareDigestApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_the_groups_digest_as_of_the_request(): void
    {
        $this->travelTo(Carbon::parse('2026-09-25 03:00:00', 'UTC')); // 12:00 JST, Friday
        $group = Group::factory()->create(['name' => 'テストG', 'slug' => 'aaaa']);
        $member = Channel::factory()->create(['short_name' => '奈煌']);
        $outsider = Channel::factory()->create(['short_name' => '部外者']);
        $group->channels()->attach($member);
        Stream::factory()->create(['channel_id' => $member->id, 'status' => 'live', 'title' => '朝活', 'scheduled_at' => '2026-09-25 02:00:00']);
        Stream::factory()->create(['channel_id' => $member->id, 'status' => 'upcoming', 'title' => '夜の雑談', 'scheduled_at' => '2026-09-25 11:00:00']);
        Stream::factory()->create(['channel_id' => $member->id, 'status' => 'upcoming', 'title' => 'メン限', 'scheduled_at' => '2026-09-25 12:00:00', 'is_members_only' => true]);
        Stream::factory()->create(['channel_id' => $outsider->id, 'status' => 'upcoming', 'title' => '別の配信', 'scheduled_at' => '2026-09-25 12:00:00']);

        $this->getJson('/api/share-digest?group=aaaa')
            ->assertOk()
            ->assertExactJson(['text' => implode("\n", [
                '📅 テストG 9/25(金) の配信予定',
                '🔴 配信中 奈煌 / 朝活',
                '20:00 奈煌 / 夜の雑談',
                '21:00 奈煌 / 🔒 メン限',
                url('/aaaa'),
            ])]);
    }

    public function test_returns_null_text_when_nothing_is_live_or_reserved(): void
    {
        Group::factory()->create(['slug' => 'aaaa']);

        $this->getJson('/api/share-digest?group=aaaa')
            ->assertOk()
            ->assertExactJson(['text' => null]);
    }

    public function test_unknown_group_returns_404(): void
    {
        $this->getJson('/api/share-digest?group=nope')->assertNotFound();
    }
}
