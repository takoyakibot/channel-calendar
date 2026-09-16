<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Setting;
use App\Support\XSearchKeywords;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class XSearchKeywordsTest extends TestCase
{
    use RefreshDatabase;

    public function test_parse_splits_on_commas_and_whitespace_and_dedupes(): void
    {
        $this->assertSame(['予定', '配信', '朝活'], XSearchKeywords::parse(" 予定, 配信　配信\n朝活 ,, "));
        $this->assertSame([], XSearchKeywords::parse(null));
        $this->assertSame([], XSearchKeywords::parse('  ,  '));
    }

    public function test_global_falls_back_to_config_when_no_setting_is_stored(): void
    {
        config(['services.x.search_keywords' => ['予定', '配信']]);

        $this->assertSame(['予定', '配信'], XSearchKeywords::global());
    }

    public function test_global_prefers_the_stored_setting(): void
    {
        config(['services.x.search_keywords' => ['予定', '配信']]);
        Setting::set(XSearchKeywords::SETTING_KEY, '告知, スケジュール');

        $this->assertSame(['告知', 'スケジュール'], XSearchKeywords::global());
    }

    public function test_for_channel_merges_global_and_channel_specific_terms(): void
    {
        config(['services.x.search_keywords' => ['予定', '配信']]);
        $channel = Channel::factory()->create(['x_handle' => 'some_user', 'x_search_keywords' => '配信, 歌枠']);

        $this->assertSame(['予定', '配信', '歌枠'], XSearchKeywords::forChannel($channel));
    }
}
