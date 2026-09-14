<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_privacy_policy_page_discloses_youtube_api_usage(): void
    {
        $response = $this->get('/privacy');

        $response->assertOk();
        $response->assertSee('プライバシーポリシー');
        $response->assertSee('YouTube API Services');
        $response->assertSee('https://www.google.com/policies/privacy', false);
        $response->assertSee('https://www.youtube.com/t/terms', false);
    }

    public function test_terms_page_links_to_youtube_terms(): void
    {
        $response = $this->get('/terms');

        $response->assertOk();
        $response->assertSee('利用規約');
        $response->assertSee('https://www.youtube.com/t/terms', false);
    }

    public function test_calendar_pages_link_to_legal_pages_in_footer(): void
    {
        Group::factory()->create(['name' => 'G', 'slug' => 'aaaa']);

        foreach (['/', '/aaaa'] as $path) {
            $response = $this->get($path);
            $response->assertOk();
            $response->assertSee('href="' . url('/privacy') . '"', false);
            $response->assertSee('href="' . url('/terms') . '"', false);
            $response->assertSee('YouTube Data API');
        }
    }

    public function test_legal_slugs_are_reserved_for_groups(): void
    {
        $admin = User::factory()->create();

        foreach (['privacy', 'terms'] as $slug) {
            $response = $this->actingAs($admin)->post('/admin/groups', ['name' => 'X', 'slug' => $slug]);
            $response->assertSessionHasErrors('slug');
        }

        $this->assertDatabaseCount('groups', 0);
    }
}
