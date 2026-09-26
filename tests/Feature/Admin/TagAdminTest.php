<?php

namespace Tests\Feature\Admin;

use App\Models\Channel;
use App\Models\IgnoredTerm;
use App\Models\Stream;
use App\Models\Tag;
use App\Models\TagAlias;
use App\Models\User;
use App\Support\StreamTagger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_only_admins_see_the_page_and_it_lists_tags_aliases_ignored_and_unmatched_terms(): void
    {
        Channel::factory()->create();
        $mc = Tag::createWithAlias('Minecraft', 'game');
        TagAlias::create(['tag_id' => $mc->id, 'alias' => 'マイクラ']);
        IgnoredTerm::create(['term' => '新人vtuber', 'display' => '新人Vtuber']);
        Stream::factory()->create(['title' => '【Woodo】']);
        (new StreamTagger())->retagAll();

        $this->get('/admin/tags')->assertRedirect(route('auth.google'));
        $this->actingAs(User::factory()->create())->get('/admin/tags')->assertForbidden();
        $this->actingAs($this->admin)->get('/admin/tags')->assertOk()
            ->assertSee('Minecraft')->assertSee('マイクラ')->assertSee('新人Vtuber')->assertSee('Woodo');
    }

    public function test_rename_and_alias_changes_retag_streams(): void
    {
        $mc = Tag::createWithAlias('Minecraft', 'game');
        $stream = Stream::factory()->create(['title' => '【マイクラ】建築']);

        $this->actingAs($this->admin)->post("/admin/tags/{$mc->id}/aliases", ['alias' => ' マイクラ '])->assertRedirect('/admin/tags');
        $this->assertSame(['Minecraft'], $stream->fresh()->tags->pluck('name')->all());

        $this->actingAs($this->admin)->put("/admin/tags/{$mc->id}", ['name' => 'マインクラフト', 'kind' => 'game'])->assertRedirect('/admin/tags');
        $this->assertDatabaseHas('tags', ['id' => $mc->id, 'name' => 'マインクラフト']);
        $this->assertDatabaseHas('tag_aliases', ['tag_id' => $mc->id, 'alias' => 'マインクラフト']);

        $alias = TagAlias::where('alias', 'マイクラ')->first();
        $this->actingAs($this->admin)->delete("/admin/tag-aliases/{$alias->id}")->assertRedirect('/admin/tags');
        $this->assertSame([], $stream->fresh()->tags->pluck('name')->all());
    }

    public function test_merge_moves_aliases_and_streams_then_deletes_the_source_tag(): void
    {
        $mc = Tag::createWithAlias('Minecraft', 'game');
        $dup = Tag::createWithAlias('マイクラ', 'game');
        $both = Stream::factory()->create(['title' => '【Minecraft】【マイクラ】']);
        $only = Stream::factory()->create(['title' => '【マイクラ】']);
        (new StreamTagger())->retagAll();

        $this->actingAs($this->admin)->post("/admin/tags/{$dup->id}/merge", ['into_tag_id' => $mc->id])->assertRedirect('/admin/tags');

        $this->assertDatabaseMissing('tags', ['id' => $dup->id]);
        $this->assertDatabaseHas('tag_aliases', ['tag_id' => $mc->id, 'alias' => 'マイクラ']);
        $this->assertSame(['Minecraft'], $both->fresh()->tags->pluck('name')->all());
        $this->assertSame(['Minecraft'], $only->fresh()->tags->pluck('name')->all());
        $this->assertDatabaseHas('activity_logs', ['user_id' => $this->admin->id, 'action' => 'merge_tag']);
    }

    public function test_delete_tag_unignore_and_classify_from_the_admin_page(): void
    {
        Channel::factory()->create();
        $mc = Tag::createWithAlias('Minecraft', 'game');
        $stream = Stream::factory()->create(['title' => '【Minecraft】【Woodo】']);
        $ignored = IgnoredTerm::create(['term' => '初見歓迎', 'display' => '初見歓迎']);
        (new StreamTagger())->retagAll();

        $this->actingAs($this->admin)->delete("/admin/tags/{$mc->id}")->assertRedirect('/admin/tags');
        $this->assertDatabaseMissing('tags', ['id' => $mc->id]);
        $this->assertDatabaseMissing('stream_tag', ['stream_id' => $stream->id]);
        $this->assertDatabaseHas('unmatched_terms', ['term' => 'minecraft']);

        $this->actingAs($this->admin)->delete("/admin/ignored-terms/{$ignored->id}")->assertRedirect('/admin/tags');
        $this->assertDatabaseMissing('ignored_terms', ['term' => '初見歓迎']);

        $this->actingAs($this->admin)->post('/admin/terms/classify', ['term' => 'Woodo', 'kind' => 'game', 'name' => 'WOODO!'])->assertRedirect('/admin/tags');
        $this->assertDatabaseHas('tags', ['name' => 'WOODO!', 'kind' => 'game']);
        $this->assertSame(['WOODO!'], $stream->fresh()->tags->pluck('name')->all());
        $this->actingAs($this->admin)->post('/admin/terms/classify', ['term' => 'woodo', 'kind' => 'ignore'])->assertRedirect('/admin/tags')->assertSessionHas('error');
    }
}
