<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_page_loads(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Channel Calendar');
        $response->assertSee('fullcalendar');
        $response->assertSee('id="board"', false);
        $response->assertSee('週ボード');
        $response->assertSee('data-view="month"', false);
    }
}
