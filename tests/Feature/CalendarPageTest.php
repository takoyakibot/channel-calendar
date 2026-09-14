<?php

namespace Tests\Feature;

use Tests\TestCase;

class CalendarPageTest extends TestCase
{
    public function test_calendar_page_loads(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Channel Calendar');
        $response->assertSee('fullcalendar');
    }
}
