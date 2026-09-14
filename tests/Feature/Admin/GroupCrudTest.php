<?php

namespace Tests\Feature\Admin;

use App\Models\Channel;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
    }

    public function test_guest_cannot_access_admin_groups(): void
    {
        $this->get('/admin/groups')->assertRedirect('/login');
    }

    public function test_admin_can_view_group_list(): void
    {
        Group::factory()->create(['name' => 'My Group', 'slug' => 'mygroup']);

        $response = $this->actingAs($this->admin)->get('/admin/groups');

        $response->assertOk();
        $response->assertSee('My Group');
        $response->assertSee('/mygroup');
    }

    public function test_admin_can_create_group_with_channels(): void
    {
        $channels = Channel::factory()->count(2)->create();

        $response = $this->actingAs($this->admin)->post('/admin/groups', [
            'name' => 'New Group',
            'slug' => 'new-group',
            'channels' => $channels->pluck('id')->all(),
        ]);

        $response->assertRedirect('/admin/groups');
        $this->assertDatabaseHas('groups', ['slug' => 'new-group', 'name' => 'New Group']);
        $group = Group::where('slug', 'new-group')->first();
        $this->assertEqualsCanonicalizing($channels->pluck('id')->all(), $group->channels()->pluck('channels.id')->all());
    }

    public function test_admin_can_update_group_and_sync_channels(): void
    {
        $group = Group::factory()->create(['slug' => 'g1']);
        [$a, $b] = Channel::factory()->count(2)->create();
        $group->channels()->attach($a);

        $response = $this->actingAs($this->admin)->put("/admin/groups/{$group->slug}", [
            'name' => 'Renamed',
            'slug' => 'g1',
            'channels' => [$b->id],
        ]);

        $response->assertRedirect('/admin/groups');
        $this->assertDatabaseHas('groups', ['id' => $group->id, 'name' => 'Renamed']);
        $this->assertEquals([$b->id], $group->channels()->pluck('channels.id')->all());
    }

    public function test_admin_can_delete_group(): void
    {
        $group = Group::factory()->create();

        $response = $this->actingAs($this->admin)->delete("/admin/groups/{$group->slug}");

        $response->assertRedirect('/admin/groups');
        $this->assertDatabaseMissing('groups', ['id' => $group->id]);
    }

    public function test_reserved_slug_is_rejected(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/groups', [
            'name' => 'Bad',
            'slug' => 'admin',
        ]);

        $response->assertSessionHasErrors('slug');
        $this->assertDatabaseMissing('groups', ['slug' => 'admin']);
    }

    public function test_slug_must_be_url_safe(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/groups', [
            'name' => 'Bad',
            'slug' => 'Has Space',
        ]);

        $response->assertSessionHasErrors('slug');
    }
}
