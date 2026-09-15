<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPagesSmokeTest extends TestCase
{
    use RefreshDatabase;

    /** @dataProvider adminPages */
    public function test_admin_pages_render_for_admins(string $path): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get($path)->assertOk();
    }

    /** @dataProvider adminPages */
    public function test_admin_pages_are_forbidden_for_regular_users(string $path): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get($path)->assertForbidden();
    }

    /** @dataProvider adminPages */
    public function test_admin_pages_redirect_guests_to_google_login(string $path): void
    {
        $this->get($path)->assertRedirect(route('auth.google'));
    }

    public static function adminPages(): array
    {
        return [
            'channels' => ['/admin/channels'],
            'channel create' => ['/admin/channels/create'],
            'groups' => ['/admin/groups'],
            'group create' => ['/admin/groups/create'],
            'users' => ['/admin/users'],
            'activity logs' => ['/admin/activity-logs'],
            'settings' => ['/admin/settings'],
        ];
    }
}
