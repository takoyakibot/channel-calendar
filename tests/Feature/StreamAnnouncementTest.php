<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Group;
use App\Models\Stream;
use App\Support\StreamAnnouncement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StreamAnnouncementTest extends TestCase
{
    use RefreshDatabase;

    public function test_text_has_title_channel_jst_time_video_url_and_group_page(): void
    {
        $group = Group::factory()->create(['slug' => 'emove']);
        $channel = Channel::factory()->create(['name' => 'Hayamaru ch.']);
        $channel->groups()->attach($group);
        $stream = Stream::factory()->create([
            'channel_id' => $channel->id,
            'video_id' => 'abc123',
            'title' => '【雑談】夜のまったり枠',
            'scheduled_at' => '2026-09-24 11:00:00', // UTC → 20:00 JST, Thursday
        ]);

        $text = StreamAnnouncement::text($stream);

        $this->assertStringContainsString("🎬 【雑談】夜のまったり枠\n", $text);
        $this->assertStringContainsString("📺 Hayamaru ch.\n", $text);
        $this->assertStringContainsString("🕐 9/24(木) 20:00〜\n", $text);
        $this->assertStringContainsString("🔗 https://www.youtube.com/watch?v=abc123\n", $text);
        $this->assertStringEndsWith(url('/emove'), $text);
    }

    public function test_live_streams_say_they_are_on_air_with_the_actual_start_time(): void
    {
        $channel = Channel::factory()->create(['name' => 'Hayamaru ch.']);
        $stream = Stream::factory()->create([
            'channel_id' => $channel->id,
            'video_id' => 'live1',
            'title' => '【縦型雑談】ゲリラ',
            'status' => 'live',
            'scheduled_at' => '2026-09-25 11:05:16',
            'actual_start_at' => '2026-09-25 11:05:16', // 20:05 JST, Friday
        ]);

        $text = StreamAnnouncement::text($stream);

        $this->assertStringStartsWith("🔴 配信中\n", $text);
        $this->assertStringContainsString("🎬 【縦型雑談】ゲリラ\n", $text);
        $this->assertStringContainsString("🕐 9/25(金) 20:05 開始\n", $text);
        $this->assertStringContainsString("🔗 https://www.youtube.com/watch?v=live1\n", $text);
        $this->assertStringNotContainsString('新しい配信予定', $text);
    }

    public function test_channel_without_a_group_links_to_the_site_root(): void
    {
        $channel = Channel::factory()->create();
        $stream = Stream::factory()->create(['channel_id' => $channel->id, 'video_id' => 'nogrp']);

        $this->assertStringEndsWith(url('/'), StreamAnnouncement::text($stream));
    }

    public function test_long_titles_are_shortened_to_fit_x_weighted_length(): void
    {
        $channel = Channel::factory()->create(['name' => str_repeat('ち', 20)]);
        $stream = Stream::factory()->create([
            'channel_id' => $channel->id,
            'video_id' => 'longtitle',
            'title' => str_repeat('あ', 200),
        ]);

        $text = StreamAnnouncement::text($stream);

        $this->assertLessThanOrEqual(280, StreamAnnouncement::weightedLength($text));
        $this->assertStringContainsString('あ…', $text);
        $this->assertStringContainsString('watch?v=longtitle', $text);
    }

    public function test_weighted_length_counts_cjk_double_and_urls_as_23(): void
    {
        $this->assertSame(3, StreamAnnouncement::weightedLength('abc'));
        $this->assertSame(6, StreamAnnouncement::weightedLength('日本語'));
        $this->assertSame(23, StreamAnnouncement::weightedLength('https://www.youtube.com/watch?v=abc123'));
        $this->assertSame(23 + 1 + 2, StreamAnnouncement::weightedLength("https://example.com/a/very/long/path/here 日"));
    }
}
