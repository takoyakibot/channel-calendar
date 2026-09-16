<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use App\Services\YouTubeService;
use App\Support\XSearchKeywords;
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
        $this->admin = User::factory()->admin()->create();
    }

    public function test_guest_cannot_access_settings(): void
    {
        $this->get('/admin/settings')->assertRedirect(route('auth.google'));
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

    public function test_admin_can_save_global_x_search_keywords(): void
    {
        $response = $this->actingAs($this->admin)->put('/admin/settings', [
            'youtube_api_key' => '',
            'x_search_keywords' => ' 告知, スケジュール　配信 ',
        ]);

        $response->assertRedirect('/admin/settings');
        $this->assertSame('告知,スケジュール,配信', Setting::get(XSearchKeywords::SETTING_KEY));
        $this->assertSame(['告知', 'スケジュール', '配信'], XSearchKeywords::global());
    }

    public function test_clearing_global_x_search_keywords_falls_back_to_the_default(): void
    {
        config(['services.x.search_keywords' => ['予定', '配信']]);
        Setting::set(XSearchKeywords::SETTING_KEY, '告知');

        $this->actingAs($this->admin)->put('/admin/settings', ['youtube_api_key' => '', 'x_search_keywords' => ''])
            ->assertRedirect('/admin/settings');

        $this->assertNull(Setting::get(XSearchKeywords::SETTING_KEY));
        $this->assertSame(['予定', '配信'], XSearchKeywords::global());
    }

    public function test_settings_page_shows_current_x_search_keywords(): void
    {
        Setting::set(XSearchKeywords::SETTING_KEY, '告知,スケジュール');

        $response = $this->actingAs($this->admin)->get('/admin/settings');

        $response->assertOk();
        $response->assertSee('name="x_search_keywords"', false);
        $response->assertSee('告知, スケジュール');
    }

    public function test_saving_api_key_alone_keeps_x_search_keywords(): void
    {
        Setting::set(XSearchKeywords::SETTING_KEY, '告知');

        $this->actingAs($this->admin)->put('/admin/settings', ['youtube_api_key' => 'AIzaNEWKEY'])
            ->assertRedirect('/admin/settings');

        $this->assertSame('告知', Setting::get(XSearchKeywords::SETTING_KEY));
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
