<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\User;
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
        // Week board navigation: week and single-day steps.
        foreach (['board-prev', 'board-prev-day', 'board-today', 'board-next-day', 'board-next'] as $id) {
            $response->assertSee('id="' . $id . '"', false);
        }
        // Channel filter starts collapsed.
        $response->assertSee('id="channel-filter" class="channel-filter" hidden', false);
        $response->assertSee('class="arrow collapsed"', false);
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

    public function test_parent_page_offers_child_groups_as_toggles(): void
    {
        $parent = Group::factory()->create(['name' => '親', 'slug' => 'aaaa']);
        Group::factory()->create(['name' => '子B', 'slug' => 'bbbb', 'parent_id' => $parent->id]);
        Group::factory()->create(['name' => '子C', 'slug' => 'cccc', 'parent_id' => $parent->id]);
        Group::factory()->create(['name' => '無関係', 'slug' => 'zzzz']);

        $response = $this->get('/aaaa');

        $response->assertOk();
        $response->assertSee('id="subgroup-toggles"', false);
        $response->assertSee('id="subgroup-hint"', false);
        $response->assertSee('そのグループだけに絞り込みます');
        $response->assertSee('子B');
        $response->assertSee('子C');
        $response->assertDontSee('無関係');
    }

    public function test_leaf_group_page_has_no_subgroup_toggles(): void
    {
        $parent = Group::factory()->create(['slug' => 'aaaa']);
        Group::factory()->create(['name' => '子B', 'slug' => 'bbbb', 'parent_id' => $parent->id]);

        $response = $this->get('/aaaa/bbbb');

        $response->assertOk();
        $response->assertDontSee('id="subgroup-toggles"', false);
    }

    public function test_root_page_shows_top_level_groups_as_cards(): void
    {
        $parent = Group::factory()->create(['name' => 'トップA', 'slug' => 'aaaa']);
        Group::factory()->create(['name' => '子B', 'slug' => 'bbbb', 'parent_id' => $parent->id]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('class="group-card"', false);
        $response->assertSee('トップA');
        $response->assertDontSee('子B');
    }

    public function test_logged_in_group_page_offers_x_search_link_in_the_schedule_modal(): void
    {
        config(['services.x.search_keywords' => ['予定', '配信']]);
        Group::factory()->create(['name' => 'G', 'slug' => 'aaaa']);

        $response = $this->actingAs(User::factory()->create())->get('/aaaa');

        $response->assertOk();
        $response->assertSee('id="modal-x-search"', false);
        $response->assertSee('X_SEARCH_KEYWORDS', false);
        // @json escapes non-ASCII, so compare against the encoded form.
        $response->assertSee(json_encode('予定'), false);
    }

    public function test_unknown_group_slug_returns_404(): void
    {
        $this->get('/nope')->assertNotFound();
    }

    public function test_group_route_does_not_shadow_auth_routes(): void
    {
        // /auth/google is a reserved first segment; it must reach Socialite (a redirect), not the group catch-all (404).
        $this->get('/auth/google')->assertStatus(302);
        $this->get('/admin/channels')->assertRedirect(route('auth.google'));
    }
}
