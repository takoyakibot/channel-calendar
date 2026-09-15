<?php

namespace Tests\Feature;

use App\Models\Group;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CookieConsentTest extends TestCase
{
    use RefreshDatabase;

    /** @dataProvider publicPages */
    public function test_pages_ship_the_consent_banner_with_a_settings_mode(string $path): void
    {
        Group::factory()->create(['name' => 'G', 'slug' => 'aaaa']);

        $response = $this->get($path);

        $response->assertOk();
        // Banner and both choices exist, hidden until the script decides.
        $response->assertSee('id="cookie-consent" hidden', false);
        $response->assertSee('id="cc-accept-all"', false);
        $response->assertSee('id="cc-accept-req"', false);
        // Settings mode: current-choice label and a close button that keeps the choice.
        $response->assertSee('id="cc-current"', false);
        $response->assertSee('id="cc-close"', false);
        $response->assertSee('変更せずに閉じる');
        // Footer entry point.
        $response->assertSee('data-cc-reset', false);
    }

    public static function publicPages(): array
    {
        return [
            'landing' => ['/'],
            'group calendar' => ['/aaaa'],
            'privacy' => ['/privacy'],
        ];
    }
}
