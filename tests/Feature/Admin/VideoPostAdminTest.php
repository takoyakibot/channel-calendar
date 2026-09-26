<?php

namespace Tests\Feature\Admin;

use App\Models\Channel;
use App\Models\User;
use App\Models\VideoPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VideoPostAdminTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(): VideoPost
    {
        $channel = Channel::factory()->create(['name' => 'Member A']);
        $post = VideoPost::create(['video_id' => 'clip0000001', 'kind' => 'clip', 'title' => '消される切り抜き', 'source_channel_id' => 'UC_x', 'source_channel_name' => '切り抜きch', 'published_at' => '2026-09-24 10:00:00']);
        $post->channels()->attach($channel);

        return $post;
    }

    public function test_only_admins_can_see_the_list(): void
    {
        $this->makePost();

        $this->get('/admin/video-posts')->assertRedirect(route('auth.google'));
        $this->actingAs(User::factory()->create())->get('/admin/video-posts')->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get('/admin/video-posts')
            ->assertOk()->assertSee('消される切り抜き')->assertSee('切り抜きch')->assertSee('Member A');
    }

    public function test_admin_can_delete_a_post_and_it_is_logged(): void
    {
        $post = $this->makePost();
        $admin = User::factory()->admin()->create();

        $this->actingAs(User::factory()->create())->delete("/admin/video-posts/{$post->id}")->assertForbidden();
        $this->assertDatabaseHas('video_posts', ['id' => $post->id]);

        $this->actingAs($admin)->delete("/admin/video-posts/{$post->id}")->assertRedirect('/admin/video-posts');
        $this->assertDatabaseMissing('video_posts', ['id' => $post->id]);
        $this->assertDatabaseMissing('channel_video_post', ['video_post_id' => $post->id]);
        $this->assertDatabaseHas('activity_logs', ['user_id' => $admin->id, 'action' => 'delete_video_post', 'target_id' => $post->id]);
    }
}
