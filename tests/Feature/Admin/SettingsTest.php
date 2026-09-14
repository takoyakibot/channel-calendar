<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use App\Services\YouTubeService;
use App\Support\YouTubeApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
    }

    public function test_guest_cannot_access_settings(): void
    {
        $this->get('/admin/settings')->assertRedirect('/login');
    }

    public function test_admin_can_view_settings_page(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/settings');

        $response->assertOk();
        $response->assertSee('YouTube API');
        $response->assertSee('name="youtube_api_key"', false);
    }

    public function test_admin_can_save_api_key_and_it_is_stored_encrypted(): void
    {
        $response = $this->actingAs($this->admin)->put('/admin/settings', [
            'youtube_api_key' => 'AIzaTESTKEY1234567890',
        ]);

        $response->assertRedirect('/admin/settings');
        $this->assertEquals('AIzaTESTKEY1234567890', Setting::get('youtube_api_key'));

        $raw = DB::table('settings')->where('key', 'youtube_api_key')->value('value');
        $this->assertNotEquals('AIzaTESTKEY1234567890', $raw);
        $this->assertStringNotContainsString('AIzaTESTKEY', $raw);
    }

    public function test_saving_empty_key_clears_the_setting(): void
    {
        Setting::set('youtube_api_key', 'old');

        $this->actingAs($this->admin)->put('/admin/settings', ['youtube_api_key' => ''])
            ->assertRedirect('/admin/settings');

        $this->assertNull(Setting::get('youtube_api_key'));
    }

    public function test_api_key_resolver_prefers_database_over_env(): void
    {
        config(['services.youtube.api_key' => 'ENVKEY']);

        $this->assertEquals('ENVKEY', app(YouTubeApiKey::class)->resolve());
        $this->assertEquals('env', app(YouTubeApiKey::class)->source());

        Setting::set('youtube_api_key', 'DBKEY');

        $this->assertEquals('DBKEY', app(YouTubeApiKey::class)->resolve());
        $this->assertEquals('database', app(YouTubeApiKey::class)->source());
    }

    public function test_settings_page_masks_the_stored_key(): void
    {
        Setting::set('youtube_api_key', 'AIzaSECRET_KEY_9876');

        $response = $this->actingAs($this->admin)->get('/admin/settings');

        $response->assertOk();
        $response->assertSee('9876');
        $response->assertDontSee('AIzaSECRET_KEY_9876');
    }

    public function test_connection_test_reports_success(): void
    {
        $mock = Mockery::mock(YouTubeService::class);
        $mock->shouldReceive('getChannelInfo')->once()->andReturn(['name' => 'YouTube', 'thumbnail_url' => null]);
        $this->app->instance(YouTubeService::class, $mock);

        $response = $this->actingAs($this->admin)->from('/admin/settings')->post('/admin/settings/test');

        $response->assertRedirect('/admin/settings');
        $response->assertSessionHas('success');
        $response->assertSessionMissing('error');
    }

    public function test_connection_test_reports_failure(): void
    {
        $mock = Mockery::mock(YouTubeService::class);
        $mock->shouldReceive('getChannelInfo')->once()->andThrow(new \RuntimeException('API key not valid'));
        $this->app->instance(YouTubeService::class, $mock);

        $response = $this->actingAs($this->admin)->from('/admin/settings')->post('/admin/settings/test');

        $response->assertRedirect('/admin/settings');
        $response->assertSessionHas('error');
    }
}
