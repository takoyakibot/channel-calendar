<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        putenv('ADMIN_EMAIL');
        putenv('ADMIN_PASSWORD');
        $this->app['env'] = 'testing';

        parent::tearDown();
    }

    public function test_seeder_creates_admin_from_env(): void
    {
        $this->app['env'] = 'production';
        putenv('ADMIN_EMAIL=ops@example.com');
        putenv('ADMIN_PASSWORD=s3cret!');

        $this->artisan('db:seed', ['--class' => AdminUserSeeder::class, '--force' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('users', ['email' => 'ops@example.com']);
        $this->assertTrue(Hash::check('s3cret!', User::where('email', 'ops@example.com')->first()->password));
    }

    public function test_seeder_fails_without_password_outside_local(): void
    {
        $this->app['env'] = 'production';
        putenv('ADMIN_PASSWORD');

        $this->expectException(\RuntimeException::class);

        $this->artisan('db:seed', ['--class' => AdminUserSeeder::class, '--force' => true]);
    }
}
