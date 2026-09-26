<?php

namespace Tests\Feature\Support;

use App\Support\VideoUrl;
use PHPUnit\Framework\TestCase;

class VideoUrlTest extends TestCase
{
    /** @dataProvider urls */
    public function test_extracts_the_video_id_from_every_youtube_url_shape(string $input, ?string $expected): void
    {
        $this->assertSame($expected, VideoUrl::videoId($input));
    }

    public static function urls(): array
    {
        return [
            'watch' => ['https://www.youtube.com/watch?v=abc123DEF45', 'abc123DEF45'],
            'watch with extra params' => ['https://www.youtube.com/watch?t=120&v=abc123DEF45&list=PL1', 'abc123DEF45'],
            'mobile' => ['https://m.youtube.com/watch?v=abc123DEF45', 'abc123DEF45'],
            'youtu.be' => ['https://youtu.be/abc123DEF45?t=30', 'abc123DEF45'],
            'shorts' => ['https://www.youtube.com/shorts/abc123DEF45', 'abc123DEF45'],
            'live' => ['https://youtube.com/live/abc123DEF45?feature=share', 'abc123DEF45'],
            'embed' => ['https://www.youtube.com/embed/abc123DEF45', 'abc123DEF45'],
            'no scheme' => ['youtube.com/watch?v=abc123DEF45', 'abc123DEF45'],
            'surrounding whitespace' => ["  https://youtu.be/abc123DEF45 \n", 'abc123DEF45'],
            'bare id' => ['abc123DEF45', 'abc123DEF45'],
            'not youtube' => ['https://x.com/someone/status/123', null],
            'channel url' => ['https://www.youtube.com/@hayamaru_chun', null],
            'too short id' => ['https://youtu.be/abc', null],
            'empty' => ['', null],
        ];
    }
}
