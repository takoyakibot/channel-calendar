<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TweetPreviewTest extends TestCase
{
    use RefreshDatabase;

    private const TWEET = 'https://x.com/some_user/status/1234567890123456789';

    public function test_guest_cannot_use_the_preview(): void
    {
        $this->getJson('/api/tweet-preview?url=' . urlencode(self::TWEET))->assertUnauthorized();
    }

    public function test_returns_tweet_text_and_author_from_oembed(): void
    {
        Http::fake([
            'publish.twitter.com/oembed*' => Http::response([
                'author_name' => 'Some User',
                'author_url' => 'https://twitter.com/some_user',
                'html' => '<blockquote class="twitter-tweet"><p lang="ja" dir="ltr">今夜21時から配信します！<br>詳しくは <a href="https://t.co/xyz">https://t.co/xyz</a></p>&mdash; Some User (@some_user) <a href="https://twitter.com/some_user/status/1234567890123456789">September 16, 2026</a></blockquote>',
            ]),
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->getJson('/api/tweet-preview?url=' . urlencode(self::TWEET));

        $response->assertOk()->assertJson([
            'author_name' => 'Some User',
            'author_url' => 'https://twitter.com/some_user',
            'author_handle' => 'some_user',
        ]);
        $this->assertSame("今夜21時から配信します！\n詳しくは https://t.co/xyz", $response->json('text'));

        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://publish.twitter.com/oembed')
            && $request['url'] === 'https://twitter.com/some_user/status/1234567890123456789');
    }

    public function test_rejects_urls_that_are_not_tweets(): void
    {
        Http::fake();

        $this->actingAs(User::factory()->create())
            ->getJson('/api/tweet-preview?url=' . urlencode('https://www.youtube.com/watch?v=abc'))
            ->assertUnprocessable();

        Http::assertNothingSent();
    }

    public function test_reports_upstream_failure_without_crashing(): void
    {
        Http::fake(['publish.twitter.com/oembed*' => Http::response('', 404)]);

        $this->actingAs(User::factory()->create())
            ->getJson('/api/tweet-preview?url=' . urlencode(self::TWEET))
            ->assertStatus(404)
            ->assertJsonStructure(['message']);
    }
}
