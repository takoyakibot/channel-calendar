<?php

namespace Tests\Feature;

use App\Models\Group;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_page_shows_group_name_and_board(): void
    {
        Group::factory()->create(['name' => 'テストグループ', 'slug' => 'aaaa']);

        $response = $this->get('/aaaa');

        $response->assertOk();
        $response->assertSee('テストグループ');
        $response->assertSee('id="board"', false);
        $response->assertSee('"aaaa"', false);
    }

    public function test_unknown_group_slug_returns_404(): void
    {
        $this->get('/nope')->assertNotFound();
    }

    public function test_group_route_does_not_shadow_login(): void
    {
        $this->get('/login')->assertOk();
    }
}
