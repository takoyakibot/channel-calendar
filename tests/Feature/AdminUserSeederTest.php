<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_grants_admin_to_every_configured_email(): void
    {
        config(['app.admin_emails' => 'ops@example.com, second@example.com']);

        $this->seed(AdminUserSeeder::class);

        $this->assertDatabaseHas('users', ['email' => 'ops@example.com', 'is_admin' => true]);
        $this->assertDatabaseHas('users', ['email' => 'second@example.com', 'is_admin' => true]);
        $this->assertDatabaseCount('users', 2);
    }

    public function test_seeder_promotes_an_existing_user_without_duplicating(): void
    {
        $existing = User::factory()->create(['email' => 'ops@example.com', 'is_admin' => false]);
        config(['app.admin_emails' => 'ops@example.com']);

        $this->seed(AdminUserSeeder::class);

        $this->assertDatabaseCount('users', 1);
        $this->assertTrue($existing->fresh()->is_admin);
    }

    public function test_seeder_does_nothing_when_no_admin_emails_configured(): void
    {
        config(['app.admin_emails' => '']);

        $this->seed(AdminUserSeeder::class);

        $this->assertDatabaseCount('users', 0);
    }
}
