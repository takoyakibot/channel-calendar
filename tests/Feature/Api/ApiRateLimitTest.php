<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiRateLimitTest extends TestCase
{
    use RefreshDatabase;

    /** Switching months calls these several times each; Laravel's default 60/min blanked the calendar. */
    public function test_the_calendars_read_apis_are_not_rate_limited(): void
    {
        foreach (range(1, 80) as $i) {
            $this->getJson('/api/streams?start=2026-11-01&end=2026-12-01')->assertOk();
            $this->getJson('/api/manual-schedules?start=2026-11-01&end=2026-12-01')->assertOk();
        }
    }

    public function test_signed_in_writes_keep_the_per_minute_limit(): void
    {
        $user = User::factory()->create();
        foreach (range(1, 60) as $i) {
            $this->actingAs($user)->getJson('/api/preferences')->assertOk();
        }
        $this->actingAs($user)->getJson('/api/preferences')->assertStatus(429);
    }
}
