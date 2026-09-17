<?php

namespace Tests\Feature;

use App\Models\Group;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Ga4TagTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_tag_is_rendered_without_a_measurement_id(): void
    {
        config(['services.ga4.measurement_id' => null]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('googletagmanager.com', false);
    }

    /** @dataProvider pages */
    public function test_tag_is_consent_gated_and_never_loaded_statically(string $path): void
    {
        config(['services.ga4.measurement_id' => 'G-TESTID123']);
        Group::factory()->create(['name' => 'G', 'slug' => 'aaaa']);

        $response = $this->get($path);

        $response->assertOk();
        $response->assertSee('G-TESTID123');
        // The library is injected by script only after consent; no static <script src=…gtag/js>.
        $response->assertDontSee('<script async src="https://www.googletagmanager.com/gtag/js', false);
        $response->assertSee("localStorage.getItem(CONSENT_KEY) === 'all'", false);
        $response->assertSee("addEventListener('cookieConsent'", false);
    }

    public static function pages(): array
    {
        return [
            'landing' => ['/'],
            'group calendar' => ['/aaaa'],
            'privacy' => ['/privacy'],
        ];
    }

    public function test_privacy_policy_and_banner_disclose_analytics(): void
    {
        $this->get('/privacy')->assertOk()
            ->assertSee('Google アナリティクス')
            ->assertSee('https://policies.google.com/privacy', false)
            ->assertSee('アクセス解析（Google アナリティクス）');
    }
}
