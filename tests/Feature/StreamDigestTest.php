<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Stream;
use App\Support\StreamAnnouncement;
use App\Support\StreamDigest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StreamDigestTest extends TestCase
{
    use RefreshDatabase;

    /** @dataProvider channelNames */
    public function test_channels_fall_back_to_an_automatically_shortened_name(string $name, string $expected): void
    {
        $channel = Channel::factory()->make(['name' => $name, 'short_name' => null]);

        $this->assertSame($expected, $channel->shortName());
    }

    public static function channelNames(): array
    {
        return [
            'suffix Ch. then name' => ['Nakira Ch. 奈煌🐼🫟', '奈煌'],
            'lowercase ch.' => ['Hayamaru ch. 隼丸ちゅん', '隼丸ちゅん'],
            'name then Channel' => ['ゆゆ Channel', 'ゆゆ'],
            'slash separated' => ['ノル / Nolu', 'ノル'],
            'plain long name capped at 8' => ['とてもながいちゃんねるのなまえ', 'とてもながいちゃ'],
            'emoji only decorations' => ['🌸桜乃🌸', '桜乃'],
        ];
    }

    public function test_an_explicit_short_name_wins(): void
    {
        $channel = Channel::factory()->make(['name' => 'Nakira Ch. 奈煌🐼🫟', 'short_name' => '奈煌ちゃん']);

        $this->assertSame('奈煌ちゃん', $channel->shortName());
    }

    public function test_digest_lists_live_then_upcoming_in_order_with_a_header_and_site_link(): void
    {
        $now = Carbon::parse('2026-09-25 03:00:00', 'UTC'); // 12:00 JST, Friday
        $a = Channel::factory()->create(['short_name' => '奈煌']);
        $b = Channel::factory()->create(['short_name' => '隼丸']);
        $live = Stream::factory()->create(['channel_id' => $a->id, 'status' => 'live', 'title' => '【短め】まったり配信', 'scheduled_at' => '2026-09-25 02:30:00']);
        $tonight = Stream::factory()->create(['channel_id' => $b->id, 'status' => 'upcoming', 'title' => '夜の雑談', 'scheduled_at' => '2026-09-25 11:00:00']);
        $tomorrow = Stream::factory()->create(['channel_id' => $a->id, 'status' => 'upcoming', 'title' => 'マイクラ建築', 'scheduled_at' => '2026-09-26 10:00:00']);

        $text = StreamDigest::text(collect([$live]), collect([$tonight, $tomorrow]), $now);

        $this->assertSame(implode("\n", [
            '📅 9/25(金) の配信予定',
            '🔴 配信中 奈煌 / 【短め】まったり配信',
            '20:00 隼丸 / 夜の雑談',
            '9/26 19:00 奈煌 / マイクラ建築',
            url('/'),
        ]), $text);
    }

    public function test_digest_fits_280_weighted_characters_and_counts_the_rest(): void
    {
        $now = Carbon::parse('2026-09-25 03:00:00', 'UTC');
        $channel = Channel::factory()->create(['short_name' => 'ながいなまえ']);
        $upcoming = collect();
        for ($i = 0; $i < 12; $i++) {
            $upcoming->push(Stream::factory()->create([
                'channel_id' => $channel->id, 'status' => 'upcoming',
                'title' => str_repeat('あ', 40) . $i,
                'scheduled_at' => Carbon::parse('2026-09-25 04:00:00', 'UTC')->addHours($i),
            ]));
        }

        $text = StreamDigest::text(collect(), $upcoming, $now);

        $this->assertLessThanOrEqual(280, StreamAnnouncement::weightedLength($text));
        $this->assertMatchesRegularExpression('/他 \d+ 件 → ' . preg_quote(url('/'), '/') . '$/u', $text);
        $this->assertStringContainsString('…', $text);
        // At least a few entries made it in.
        $this->assertGreaterThanOrEqual(3, preg_match_all('/^\d{2}:\d{2} /mu', $text));
    }

    public function test_digest_is_null_when_there_is_nothing_to_say(): void
    {
        $this->assertNull(StreamDigest::text(collect(), collect(), Carbon::parse('2026-09-25 03:00:00', 'UTC')));
    }
}
