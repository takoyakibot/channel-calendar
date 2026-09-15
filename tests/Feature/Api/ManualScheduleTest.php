<?php

namespace Tests\Feature\Api;

use App\Models\Channel;
use App\Models\ManualSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualScheduleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Channel $channel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->channel = Channel::factory()->create();
    }

    public function test_guest_cannot_create_manual_schedule(): void
    {
        $this->postJson('/api/manual-schedules', [
            'channel_id' => $this->channel->id,
            'title' => 'x',
            'scheduled_at' => '2030-01-01T20:00:00+09:00',
        ])->assertUnauthorized();
    }

    public function test_offset_bearing_datetime_is_stored_as_the_same_instant(): void
    {
        // 20:00 JST on Jan 1st is 11:00 UTC on Jan 1st — it must not drift to the next day.
        $response = $this->actingAs($this->user)->postJson('/api/manual-schedules', [
            'channel_id' => $this->channel->id,
            'title' => '告知どおりの配信',
            'scheduled_at' => '2030-01-01T20:00:00+09:00',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('manual_schedules', [
            'title' => '告知どおりの配信',
            'scheduled_at' => '2030-01-01 11:00:00',
            'source_url' => null,
        ]);
    }

    public function test_source_url_is_saved_and_returned_with_the_schedule(): void
    {
        $this->actingAs($this->user)->postJson('/api/manual-schedules', [
            'channel_id' => $this->channel->id,
            'title' => 'コラボ配信',
            'source_url' => 'https://x.com/example/status/123',
            'scheduled_at' => '2030-01-01T20:00:00+09:00',
        ])->assertCreated();

        $this->assertDatabaseHas('manual_schedules', ['source_url' => 'https://x.com/example/status/123']);

        // "+" must be percent-encoded in a query string or it is read as a space.
        $this->getJson('/api/manual-schedules?' . http_build_query([
            'start' => '2030-01-01T00:00:00+09:00',
            'end' => '2030-01-08T00:00:00+09:00',
        ]))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['source_url' => 'https://x.com/example/status/123', 'status' => 'manual']);
    }

    public function test_source_url_must_be_a_web_url(): void
    {
        $this->actingAs($this->user)->postJson('/api/manual-schedules', [
            'channel_id' => $this->channel->id,
            'title' => 'x',
            'source_url' => 'javascript:alert(1)',
            'scheduled_at' => '2030-01-01T20:00:00+09:00',
        ])->assertUnprocessable()->assertJsonValidationErrors('source_url');

        $this->assertDatabaseCount('manual_schedules', 0);
    }

    public function test_owner_can_delete_and_others_cannot(): void
    {
        $schedule = ManualSchedule::create([
            'user_id' => $this->user->id,
            'channel_id' => $this->channel->id,
            'title' => 'mine',
            'scheduled_at' => now()->addDay(),
        ]);
        $other = User::factory()->create();

        $this->actingAs($other)->deleteJson("/api/manual-schedules/{$schedule->id}")->assertForbidden();
        $this->actingAs($this->user)->deleteJson("/api/manual-schedules/{$schedule->id}")->assertOk();
        $this->assertDatabaseMissing('manual_schedules', ['id' => $schedule->id]);
    }
}
