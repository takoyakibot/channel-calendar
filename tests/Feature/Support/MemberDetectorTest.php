<?php

namespace Tests\Feature\Support;

use App\Models\Channel;
use App\Support\MemberDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberDetectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_detects_members_by_abbreviated_name_short_name_and_handles(): void
    {
        $hayamaru = Channel::factory()->create(['name' => 'Hayamaru ch. 隼丸ちゅん', 'handle' => '@hayamaru_chun', 'x_handle' => 'hayamaru_x']);
        $nakira = Channel::factory()->create(['name' => 'Nakira Ch. 奈煌🐼', 'short_name' => '奈煌ちゃん', 'handle' => '@nakirach']);
        $noluna = Channel::factory()->create(['name' => 'Noluna Ch. 閃光ノルナ', 'handle' => '@nolunach', 'x_handle' => 'noluna_x']);
        $other = Channel::factory()->create(['name' => 'こてんぱう', 'handle' => '@cotenpau']);
        $channels = Channel::all();

        // Abbreviated name in the title.
        $this->assertSame([$hayamaru->id], MemberDetector::detect('【切り抜き】隼丸ちゅんの神回まとめ', $channels));
        // Explicit short name.
        $this->assertSame([$nakira->id], MemberDetector::detect('奈煌ちゃんが歌ってみた', $channels));
        // YouTube handle and X handle in a description, case-insensitively.
        $this->assertSame([$noluna->id], MemberDetector::detect("出演: @NoLunaCh\nありがとう!", $channels));
        $this->assertSame([$hayamaru->id], MemberDetector::detect('本人: https://x.com/Hayamaru_X', $channels));
        // Several members, in channel order, each once.
        $this->assertSame([$hayamaru->id, $noluna->id], MemberDetector::detect('隼丸ちゅん × 閃光ノルナ コラボ！ノルナ登場', $channels));
        $this->assertSame([], MemberDetector::detect('関係ないゲーム実況', $channels));
        $this->assertSame([$other->id], MemberDetector::detect('こてんぱう', $channels));
    }

    public function test_ignores_tokens_too_short_to_be_meaningful(): void
    {
        $channel = Channel::factory()->create(['name' => 'ゆ', 'short_name' => 'ゆ', 'handle' => '@yu_channel']);

        $this->assertSame([], MemberDetector::detect('ゆっくり実況', Channel::all()));
        $this->assertSame([$channel->id], MemberDetector::detect('出演: ' . $channel->handle, Channel::all()));
    }
}
