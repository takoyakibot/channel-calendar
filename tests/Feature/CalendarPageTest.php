<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Group;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_loads(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('配信スケジュールをまとめてチェック');
    }

    public function test_landing_cards_include_channels_from_descendant_groups(): void
    {
        $top = Group::factory()->create(['name' => 'トップ', 'slug' => 'top']);
        $child = Group::factory()->create(['name' => '子', 'slug' => 'child', 'parent_id' => $top->id]);
        $grandchild = Group::factory()->create(['name' => '孫', 'slug' => 'grandchild', 'parent_id' => $child->id]);

        $direct = Channel::factory()->create(['name' => 'Direct Ch', 'thumbnail_url' => 'https://example.com/d.jpg']);
        $inChild = Channel::factory()->create(['name' => 'Child Ch', 'thumbnail_url' => 'https://example.com/c.jpg']);
        $inGrandchild = Channel::factory()->create(['name' => 'Grandchild Ch', 'thumbnail_url' => 'https://example.com/g.jpg']);
        $inactive = Channel::factory()->create(['name' => 'Inactive Ch', 'is_active' => false]);
        $top->channels()->attach($direct);
        $child->channels()->attach([$inChild->id, $inactive->id]);
        $grandchild->channels()->attach([$inGrandchild->id, $inChild->id]); // shared channel counts once

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('title="Direct Ch"', false);
        $response->assertSee('title="Child Ch"', false);
        $response->assertSee('title="Grandchild Ch"', false);
        $response->assertDontSee('Inactive Ch');
        $response->assertSee('3チャンネル');
    }

    public function test_landing_page_shows_groups_as_cards(): void
    {
        Group::factory()->create(['name' => 'テストグループ', 'slug' => 'test']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('テストグループ');
        $response->assertSee('class="group-card"', false);
    }

    public function test_group_calendar_page_loads(): void
    {
        Group::factory()->create(['name' => 'カレンダー確認', 'slug' => 'caltest']);

        $response = $this->get('/caltest');

        $response->assertOk();
        $response->assertSee('カレンダー確認');
        $response->assertSee('fullcalendar');
        $response->assertSee('id="board"', false);
        $response->assertSee('週ボード');
    }
}
