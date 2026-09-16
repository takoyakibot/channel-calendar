<?php

namespace Tests\Unit;

use App\Support\XHandle;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class XHandleTest extends TestCase
{
    /** @dataProvider validInputs */
    public function test_normalizes_handles_and_profile_urls(string $raw, string $expected): void
    {
        $this->assertSame($expected, XHandle::normalize($raw));
    }

    public static function validInputs(): array
    {
        return [
            'bare' => ['some_user', 'some_user'],
            'with at' => ['@some_user', 'some_user'],
            'x.com url' => ['https://x.com/some_user', 'some_user'],
            'twitter.com url with query' => ['https://twitter.com/some_user?s=21', 'some_user'],
            'url with trailing path' => ['https://x.com/some_user/with_replies', 'some_user'],
            'whitespace' => ['  @Some_User  ', 'Some_User'],
        ];
    }

    /** @dataProvider invalidInputs */
    public function test_rejects_invalid_handles(string $raw): void
    {
        $this->expectException(InvalidArgumentException::class);

        XHandle::normalize($raw);
    }

    public static function invalidInputs(): array
    {
        return [
            'empty' => [''],
            'too long' => ['abcdefghijklmnop'],
            'spaces' => ['some user'],
            'hyphen' => ['some-user'],
            'status url' => ['https://x.com/some_user/status/123'],
            'other site' => ['https://example.com/some_user'],
        ];
    }

    public function test_builds_a_search_url_for_announcement_tweets(): void
    {
        $url = XHandle::searchUrl('some_user', ['予定', '配信']);

        $this->assertStringStartsWith('https://x.com/search?q=', $url);
        $this->assertStringContainsString(rawurlencode('from:some_user (予定 OR 配信)'), $url);
        $this->assertStringEndsWith('&f=live', $url);
    }
}
