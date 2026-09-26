<?php

namespace Tests\Feature\Api;

use App\Models\ActivityLog;
use App\Models\Channel;
use App\Models\Group;
use App\Models\User;
use App\Models\VideoPost;
use App\Services\YouTubeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class VideoPostTest extends TestCase
{
    use RefreshDatabase;

    private const VIDEO = 'abc123DEF45';

    private function info(array $overrides = []): array
    {
        return array_merge([
            'video_id' => self::VIDEO,
            'title' => '【切り抜き】隼丸ちゅんの神回まとめ',
            'description' => '元配信: https://youtube.com/@hayamaru_chun',
            'channel_id' => 'UC_clipper',
            'channel_title' => '切り抜きch',
            'thumbnail_url' => 'https://i.ytimg.com/vi/abc123DEF45/default.jpg',
            'published_at' => '2026-09-24T12:34:56Z',
            'duration_seconds' => 95,
        ], $overrides);
    }

    private function mockYouTube(?array $info): void
    {
        $mock = Mockery::mock(YouTubeService::class);
        $mock->shouldReceive('getVideoInfo')->with(self::VIDEO)->andReturn($info);
        $this->app->instance(YouTubeService::class, $mock);
    }

    public function test_preview_resolves_the_video_and_suggests_members(): void
    {
        $member = Channel::factory()->create(['name' => 'Hayamaru ch. 隼丸ちゅん', 'handle' => '@hayamaru_chun']);
        Channel::factory()->create(['name' => 'こてんぱう']);
        $this->mockYouTube($this->info());

        $response = $this->getJson('/api/video-posts/preview?url=' . urlencode('https://youtu.be/' . self::VIDEO));

        $response->assertOk()
            ->assertJsonPath('video_id', self::VIDEO)
            ->assertJsonPath('title', '【切り抜き】隼丸ちゅんの神回まとめ')
            ->assertJsonPath('source_channel_name', '切り抜きch')
            ->assertJsonPath('published_at', '2026-09-24T12:34:56+00:00')
            ->assertJsonPath('detected_channel_ids', [$member->id])
            ->assertJsonPath('already_registered', false);
    }

    public function test_preview_rejects_non_youtube_urls_and_unknown_videos(): void
    {
        $this->getJson('/api/video-posts/preview?url=' . urlencode('https://x.com/a/status/1'))->assertStatus(422);

        $this->mockYouTube(null);
        $this->getJson('/api/video-posts/preview?url=' . urlencode('https://youtu.be/' . self::VIDEO))->assertStatus(404);
    }

    public function test_guest_can_register_a_clip_and_it_is_logged_anonymously(): void
    {
        $member = Channel::factory()->create(['name' => 'Hayamaru ch. 隼丸ちゅん']);
        $this->mockYouTube($this->info());

        $response = $this->postJson('/api/video-posts', [
            'url' => 'https://www.youtube.com/watch?v=' . self::VIDEO,
            'kind' => 'clip',
            'channel_ids' => [$member->id],
        ]);

        $response->assertCreated()
            ->assertJsonPath('id', 'vp_1')
            ->assertJsonPath('extendedProps.type', 'clip')
            ->assertJsonPath('extendedProps.source_channel_name', '切り抜きch')
            ->assertJsonPath('extendedProps.channel_id', $member->id);
        $this->assertDatabaseHas('video_posts', ['video_id' => self::VIDEO, 'kind' => 'clip', 'source_channel_id' => 'UC_clipper', 'submitted_by_user_id' => null]);
        $this->assertDatabaseHas('channel_video_post', ['video_post_id' => 1, 'channel_id' => $member->id]);
        $this->assertDatabaseHas('activity_logs', ['user_id' => null, 'action' => 'create_video_post', 'target_id' => 1]);
    }

    public function test_logged_in_submitter_is_recorded_and_banned_users_are_refused(): void
    {
        $member = Channel::factory()->create();
        $this->mockYouTube($this->info());
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/video-posts', ['url' => self::VIDEO, 'kind' => 'guest', 'channel_ids' => [$member->id]])
            ->assertCreated();
        $this->assertDatabaseHas('video_posts', ['video_id' => self::VIDEO, 'submitted_by_user_id' => $user->id]);

        $banned = User::factory()->create(['is_banned' => true]);
        $this->actingAs($banned)->postJson('/api/video-posts', ['url' => 'zzz123DEF45', 'kind' => 'guest', 'channel_ids' => [$member->id]])
            ->assertForbidden();
    }

    public function test_same_video_cannot_be_registered_twice(): void
    {
        $member = Channel::factory()->create();
        $this->mockYouTube($this->info());
        $payload = ['url' => self::VIDEO, 'kind' => 'clip', 'channel_ids' => [$member->id]];

        $this->postJson('/api/video-posts', $payload)->assertCreated();
        $this->postJson('/api/video-posts', $payload)->assertStatus(409);

        $this->assertSame(1, VideoPost::count());
        $this->getJson('/api/video-posts/preview?url=' . self::VIDEO)->assertOk()->assertJsonPath('already_registered', true);
    }

    public function test_videos_on_a_registered_member_channel_are_refused(): void
    {
        $member = Channel::factory()->create(['channel_id' => 'UC_member']);
        $this->mockYouTube($this->info(['channel_id' => 'UC_member']));

        $this->postJson('/api/video-posts', ['url' => self::VIDEO, 'kind' => 'guest', 'channel_ids' => [$member->id]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('url');
    }

    public function test_validation_requires_kind_members_and_a_resolvable_video(): void
    {
        $member = Channel::factory()->create();
        $inactive = Channel::factory()->create(['is_active' => false]);

        $this->postJson('/api/video-posts', ['url' => self::VIDEO, 'kind' => 'other', 'channel_ids' => [$member->id]])
            ->assertStatus(422)->assertJsonValidationErrors('kind');
        $this->postJson('/api/video-posts', ['url' => self::VIDEO, 'kind' => 'clip', 'channel_ids' => []])
            ->assertStatus(422)->assertJsonValidationErrors('channel_ids');
        $this->postJson('/api/video-posts', ['url' => self::VIDEO, 'kind' => 'clip', 'channel_ids' => [$inactive->id]])
            ->assertStatus(422)->assertJsonValidationErrors('channel_ids.0');
        $this->postJson('/api/video-posts', ['url' => 'https://x.com/a/status/1', 'kind' => 'clip', 'channel_ids' => [$member->id]])
            ->assertStatus(422)->assertJsonValidationErrors('url');

        $this->mockYouTube(null);
        $this->postJson('/api/video-posts', ['url' => self::VIDEO, 'kind' => 'clip', 'channel_ids' => [$member->id]])
            ->assertStatus(422)->assertJsonValidationErrors('url');
    }

    public function test_index_lists_posts_as_calendar_events_scoped_to_the_group(): void
    {
        $groupA = Group::factory()->create(['slug' => 'aaaa']);
        $groupB = Group::factory()->create(['slug' => 'bbbb']);
        $a = Channel::factory()->create(['name' => 'A', 'color' => '#111111']);
        $b = Channel::factory()->create(['name' => 'B']);
        $a->groups()->attach($groupA);
        $b->groups()->attach($groupB);

        $clip = VideoPost::create(['video_id' => 'clip0000001', 'kind' => 'clip', 'title' => 'A の切り抜き', 'source_channel_id' => 'UC_x', 'source_channel_name' => '切り抜きch', 'published_at' => '2026-09-24 10:00:00']);
        $clip->channels()->attach($a);
        $guest = VideoPost::create(['video_id' => 'guest000001', 'kind' => 'guest', 'title' => 'B がゲスト', 'source_channel_id' => 'UC_y', 'source_channel_name' => 'ホストch', 'published_at' => '2026-09-25 10:00:00']);
        $guest->channels()->attach($b);

        $all = $this->getJson('/api/video-posts?start=2026-09-01&end=2026-09-30');
        $all->assertOk()->assertJsonCount(2);
        $all->assertJsonFragment(['id' => 'vp_' . $clip->id, 'color' => '#111111']);
        $this->assertSame('clip', $all->json('0.extendedProps.type'));
        $this->assertSame('posted', $all->json('0.extendedProps.status'));
        $this->assertSame('https://www.youtube.com/watch?v=clip0000001', $all->json('0.url'));
        $this->assertSame([['id' => $a->id, 'name' => 'A']], $all->json('0.extendedProps.members'));

        $this->getJson('/api/video-posts?start=2026-09-01&end=2026-09-30&group=aaaa')
            ->assertOk()->assertJsonCount(1)->assertJsonFragment(['title' => 'A の切り抜き']);
        $this->getJson('/api/video-posts?start=2026-09-25&end=2026-09-25')
            ->assertOk()->assertJsonCount(1)->assertJsonFragment(['title' => 'B がゲスト']);
    }
}
