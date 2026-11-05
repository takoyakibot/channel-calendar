<?php

namespace Tests\Feature\Admin;

use App\Models\Channel;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GroupCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_guest_cannot_access_admin_groups(): void
    {
        $this->get('/admin/groups')->assertRedirect(route('auth.google'));
    }

    public function test_admin_can_view_group_list_with_full_paths(): void
    {
        $parent = Group::factory()->create(['name' => 'My Group', 'slug' => 'mygroup']);
        Group::factory()->create(['name' => 'Sub Group', 'slug' => 'sub', 'parent_id' => $parent->id]);

        $response = $this->actingAs($this->admin)->get('/admin/groups');

        $response->assertOk();
        $response->assertSee('My Group');
        $response->assertSee('/mygroup/sub');
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
        $this->assertDatabaseHas('groups', ['slug' => 'new-group', 'name' => 'New Group', 'parent_id' => null]);
        $group = Group::where('slug', 'new-group')->first();
        $this->assertEqualsCanonicalizing($channels->pluck('id')->all(), $group->channels()->pluck('channels.id')->all());
    }

    public function test_admin_can_create_nested_group(): void
    {
        $parent = Group::factory()->create(['slug' => 'aaaa']);

        $response = $this->actingAs($this->admin)->post('/admin/groups', [
            'name' => 'Child',
            'slug' => 'bbbb',
            'parent_id' => $parent->id,
        ]);

        $response->assertRedirect('/admin/groups');
        $this->assertDatabaseHas('groups', ['slug' => 'bbbb', 'parent_id' => $parent->id]);
        $this->assertEquals('aaaa/bbbb', Group::where('slug', 'bbbb')->first()->path);
    }

    public function test_same_slug_is_allowed_under_different_parents(): void
    {
        $p1 = Group::factory()->create(['slug' => 'p1']);
        $p2 = Group::factory()->create(['slug' => 'p2']);
        Group::factory()->create(['slug' => 'shared', 'parent_id' => $p1->id]);

        $response = $this->actingAs($this->admin)->post('/admin/groups', [
            'name' => 'Shared 2',
            'slug' => 'shared',
            'parent_id' => $p2->id,
        ]);

        $response->assertRedirect('/admin/groups');
        $this->assertDatabaseCount('groups', 4);
    }

    public function test_duplicate_slug_under_same_parent_is_rejected(): void
    {
        $parent = Group::factory()->create(['slug' => 'p1']);
        Group::factory()->create(['slug' => 'dup', 'parent_id' => $parent->id]);

        $response = $this->actingAs($this->admin)->post('/admin/groups', [
            'name' => 'Dup',
            'slug' => 'dup',
            'parent_id' => $parent->id,
        ]);

        $response->assertSessionHasErrors('slug');
    }

    public function test_admin_can_update_group_and_sync_channels(): void
    {
        $group = Group::factory()->create(['slug' => 'g1']);
        [$a, $b] = Channel::factory()->count(2)->create();
        $group->channels()->attach($a);

        $response = $this->actingAs($this->admin)->put("/admin/groups/{$group->id}", [
            'name' => 'Renamed',
            'slug' => 'g1',
            'channels' => [$b->id],
        ]);

        $response->assertRedirect('/admin/groups');
        $this->assertDatabaseHas('groups', ['id' => $group->id, 'name' => 'Renamed']);
        $this->assertEquals([$b->id], $group->channels()->pluck('channels.id')->all());
    }

    public function test_group_cannot_be_its_own_ancestor(): void
    {
        $parent = Group::factory()->create(['slug' => 'aaaa']);
        $child = Group::factory()->create(['slug' => 'bbbb', 'parent_id' => $parent->id]);

        $self = $this->actingAs($this->admin)->put("/admin/groups/{$parent->id}", [
            'name' => 'P', 'slug' => 'aaaa', 'parent_id' => $parent->id,
        ]);
        $self->assertSessionHasErrors('parent_id');

        $cycle = $this->actingAs($this->admin)->put("/admin/groups/{$parent->id}", [
            'name' => 'P', 'slug' => 'aaaa', 'parent_id' => $child->id,
        ]);
        $cycle->assertSessionHasErrors('parent_id');
    }

    public function test_admin_can_delete_group_and_children_cascade(): void
    {
        $group = Group::factory()->create();
        $child = Group::factory()->create(['parent_id' => $group->id]);

        $response = $this->actingAs($this->admin)->delete("/admin/groups/{$group->id}");

        $response->assertRedirect('/admin/groups');
        $this->assertDatabaseMissing('groups', ['id' => $group->id]);
        $this->assertDatabaseMissing('groups', ['id' => $child->id]);
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

    public function test_uploaded_thumbnail_is_stored_and_used(): void
    {
        Storage::fake('uploads');

        $this->actingAs($this->admin)->post('/admin/groups', [
            'name' => 'Pic',
            'slug' => 'pic',
            'thumbnail_url' => 'https://example.com/ignored.png',
            'thumbnail_file' => UploadedFile::fake()->image('thumb.png', 1280, 720),
        ])->assertRedirect('/admin/groups');

        $group = Group::where('slug', 'pic')->first();
        $this->assertStringStartsWith(Storage::disk('uploads')->url('groups/'), $group->thumbnail_url);
        Storage::disk('uploads')->assertExists('groups/' . basename($group->thumbnail_url));
    }

    public function test_thumbnail_upload_rejects_bad_files(): void
    {
        Storage::fake('uploads');

        foreach ([
            UploadedFile::fake()->create('x.svg', 10, 'image/svg+xml'),
            UploadedFile::fake()->image('big.png', 1280, 720)->size(3000),
            UploadedFile::fake()->image('tiny.png', 50, 50),
            UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'),
        ] as $file) {
            $this->actingAs($this->admin)->post('/admin/groups', [
                'name' => 'Bad', 'slug' => 'bad', 'thumbnail_file' => $file,
            ])->assertSessionHasErrors('thumbnail_file');
        }
        $this->assertDatabaseMissing('groups', ['slug' => 'bad']);
    }

    public function test_replacing_or_deleting_removes_old_upload(): void
    {
        Storage::fake('uploads');
        $parent = Group::factory()->create(['slug' => 'p']);
        $child = Group::factory()->create(['slug' => 'c', 'parent_id' => $parent->id]);

        $upload = fn (Group $g) => $this->actingAs($this->admin)->put("/admin/groups/{$g->id}", [
            'name' => $g->name, 'slug' => $g->slug, 'parent_id' => $g->parent_id,
            'thumbnail_file' => UploadedFile::fake()->image('t.jpg', 640, 360),
        ]);
        $upload($parent);
        $first = basename($parent->fresh()->thumbnail_url);
        $upload($parent);
        Storage::disk('uploads')->assertMissing('groups/' . $first);
        $upload($child);
        $files = [basename($parent->fresh()->thumbnail_url), basename($child->fresh()->thumbnail_url)];

        $this->actingAs($this->admin)->delete("/admin/groups/{$parent->id}");

        foreach ($files as $f) {
            Storage::disk('uploads')->assertMissing('groups/' . $f);
        }
    }

    public function test_external_thumbnail_url_still_works(): void
    {
        $group = Group::factory()->create(['slug' => 'ext']);

        $this->actingAs($this->admin)->put("/admin/groups/{$group->id}", [
            'name' => $group->name, 'slug' => 'ext', 'thumbnail_url' => 'https://example.com/a.png',
        ])->assertRedirect('/admin/groups');

        $this->assertSame('https://example.com/a.png', $group->fresh()->thumbnail_url);
    }
}
