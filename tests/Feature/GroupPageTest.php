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

    public function test_nested_group_page_resolves_by_path(): void
    {
        $parent = Group::factory()->create(['name' => '親', 'slug' => 'aaaa']);
        Group::factory()->create(['name' => '子グループ', 'slug' => 'bbbb', 'parent_id' => $parent->id]);

        $response = $this->get('/aaaa/bbbb');

        $response->assertOk();
        $response->assertSee('子グループ');
        $response->assertSee('"aaaa/bbbb"', false);
    }

    public function test_child_slug_is_not_reachable_at_root(): void
    {
        $parent = Group::factory()->create(['slug' => 'aaaa']);
        Group::factory()->create(['slug' => 'bbbb', 'parent_id' => $parent->id]);

        $this->get('/bbbb')->assertNotFound();
        $this->get('/aaaa/nope')->assertNotFound();
    }

    public function test_parent_page_offers_child_groups_in_a_selector(): void
    {
        $parent = Group::factory()->create(['name' => '親', 'slug' => 'aaaa']);
        Group::factory()->create(['name' => '子B', 'slug' => 'bbbb', 'parent_id' => $parent->id]);
        Group::factory()->create(['name' => '子C', 'slug' => 'cccc', 'parent_id' => $parent->id]);
        Group::factory()->create(['name' => '無関係', 'slug' => 'zzzz']);

        $response = $this->get('/aaaa');

        $response->assertOk();
        $response->assertSee('id="group-select"', false);
        $response->assertSee('value="' . url('/aaaa/bbbb') . '"', false);
        $response->assertSee('value="' . url('/aaaa/cccc') . '"', false);
        $response->assertSee('子B');
        $response->assertSee('子C');
        $response->assertDontSee('無関係');
    }

    public function test_leaf_group_page_has_no_selector(): void
    {
        $parent = Group::factory()->create(['slug' => 'aaaa']);
        Group::factory()->create(['name' => '子B', 'slug' => 'bbbb', 'parent_id' => $parent->id]);

        $response = $this->get('/aaaa/bbbb');

        $response->assertOk();
        $response->assertDontSee('id="group-select"', false);
    }

    public function test_root_page_offers_top_level_groups_in_a_selector(): void
    {
        $parent = Group::factory()->create(['name' => 'トップA', 'slug' => 'aaaa']);
        Group::factory()->create(['name' => '子B', 'slug' => 'bbbb', 'parent_id' => $parent->id]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('id="group-select"', false);
        $response->assertSee('value="' . url('/aaaa') . '"', false);
        $response->assertSee('トップA');
        $response->assertDontSee('子B');
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
