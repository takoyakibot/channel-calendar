<?php

namespace Tests\Unit;

use App\Support\ChannelInput;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ChannelInputTest extends TestCase
{
    /** @dataProvider handleInputs */
    public function test_parses_handles(string $raw, string $expected): void
    {
        $parsed = ChannelInput::parse($raw);

        $this->assertSame('handle', $parsed['type']);
        $this->assertSame($expected, $parsed['value']);
    }

    public static function handleInputs(): array
    {
        return [
            'with at' => ['@some_channel', '@some_channel'],
            'without at' => ['some_channel', '@some_channel'],
            'full url' => ['https://www.youtube.com/@some_channel', '@some_channel'],
            'url without scheme' => ['youtube.com/@some_channel', '@some_channel'],
            'url with trailing path' => ['https://www.youtube.com/@some_channel/streams', '@some_channel'],
            'url with query' => ['https://youtube.com/@Some.Channel-1?si=abc', '@Some.Channel-1'],
            'surrounding whitespace' => ['  @some_channel  ', '@some_channel'],
        ];
    }

    /** @dataProvider idInputs */
    public function test_parses_channel_ids(string $raw): void
    {
        $parsed = ChannelInput::parse($raw);

        $this->assertSame('id', $parsed['type']);
        $this->assertSame('UCBR8-60-B28hp2BmDPdntcQ', $parsed['value']);
    }

    public static function idInputs(): array
    {
        return [
            'bare id' => ['UCBR8-60-B28hp2BmDPdntcQ'],
            'channel url' => ['https://www.youtube.com/channel/UCBR8-60-B28hp2BmDPdntcQ'],
            'channel url with path' => ['https://www.youtube.com/channel/UCBR8-60-B28hp2BmDPdntcQ/videos'],
        ];
    }

    /** @dataProvider invalidInputs */
    public function test_rejects_invalid_input(string $raw): void
    {
        $this->expectException(InvalidArgumentException::class);

        ChannelInput::parse($raw);
    }

    public static function invalidInputs(): array
    {
        return [
            'empty' => [''],
            'only at' => ['@'],
            'too short' => ['@ab'],
            'spaces inside' => ['@has space'],
            'watch url' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
            'other site' => ['https://example.com/@foo'],
        ];
    }
}
