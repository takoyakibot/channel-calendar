<?php

namespace Tests\Feature;

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
