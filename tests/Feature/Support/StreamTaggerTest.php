<?php

namespace Tests\Feature\Support;

use App\Models\Channel;
use App\Models\IgnoredTerm;
use App\Models\Stream;
use App\Models\Tag;
use App\Models\TagAlias;
use App\Support\StreamTagger;
use App\Support\TermNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StreamTaggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_normalizer_folds_width_case_hashes_and_spaces(): void
    {
        $this->assertSame('minecraft', TermNormalizer::normalize(' ＭＩＮＥＣＲＡＦＴ '));
        $this->assertSame('はやまる中', TermNormalizer::normalize('#はやまる中'));
        $this->assertSame('はやまる中', TermNormalizer::normalize('＃はやまる中'));
        $this->assertSame('初心者 指示ok', TermNormalizer::normalize("初心者　　指示OK"));
        $this->assertTrue(TermNormalizer::isAscii('repo'));
        $this->assertFalse(TermNormalizer::isAscii('原神'));
    }

    public function test_matches_the_dictionary_anywhere_in_the_title_after_normalisation(): void
    {
        $mc = Tag::createWithAlias('Minecraft', 'game');
        TagAlias::create(['tag_id' => $mc->id, 'alias' => 'マイクラ']);
        $chat = Tag::createWithAlias('雑談', 'category');
        $tagger = new StreamTagger();

        $this->assertEqualsCanonicalizing([$mc->id], $tagger->matchTagIds('マイクラみたいなゲームやる'));
        $this->assertEqualsCanonicalizing([$mc->id], $tagger->matchTagIds('【 ＭＩＮＥＣＲＡＦＴ 】建築'));
        $this->assertEqualsCanonicalizing([$mc->id, $chat->id], $tagger->matchTagIds('【Minecraft】雑談しながら'));
        $this->assertSame([], $tagger->matchTagIds('歌枠'));
    }

    public function test_ascii_aliases_are_matched_on_word_boundaries(): void
    {
        $repo = Tag::createWithAlias('R.E.P.O.', 'game');
        TagAlias::create(['tag_id' => $repo->id, 'alias' => 'repo']);
        $tagger = new StreamTagger();

        $this->assertSame([$repo->id], $tagger->matchTagIds('【REPO】4人で'));
        $this->assertSame([$repo->id], $tagger->matchTagIds('R.E.P.O. やる'));
        $this->assertSame([], $tagger->matchTagIds('repository を掃除する'));
    }

    public function test_tag_adds_auto_tags_drops_stale_auto_tags_and_keeps_manual_ones(): void
    {
        $genshin = Tag::createWithAlias('原神', 'game');
        $woodo = Tag::createWithAlias('Woodo', 'game');
        $chat = Tag::createWithAlias('雑談', 'category');
        $stream = Stream::factory()->create(['title' => '【原神】探索する']);
        $stream->tags()->attach($woodo->id, ['source' => 'auto']);   // no longer matches
        $stream->tags()->attach($chat->id, ['source' => 'manual']);  // someone added it; keep

        (new StreamTagger())->tag($stream);

        $tags = $stream->fresh()->tags->keyBy('id');
        $this->assertEqualsCanonicalizing([$genshin->id, $chat->id], $tags->keys()->all());
        $this->assertSame('auto', $tags[$genshin->id]->pivot->source);
        $this->assertSame('manual', $tags[$chat->id]->pivot->source);
    }

    public function test_rebuild_unmatched_collects_bracket_pieces_the_dictionary_ignore_list_and_member_names_do_not_cover(): void
    {
        Channel::factory()->create(['name' => 'Hayamaru ch. 隼丸ちゅん', 'handle' => '@hayamaru_chun']);
        Tag::createWithAlias('Minecraft', 'game');
        IgnoredTerm::create(['term' => '新人vtuber', 'display' => '新人Vtuber']);
        Stream::factory()->create(['title' => '【Minecraft/初見さん大歓迎】建築']);
        Stream::factory()->create(['title' => '【新人Vtuber／隼丸ちゅん】雑談']);
        Stream::factory()->create(['title' => '【 Woodo 】はじめて']);
        Stream::factory()->create(['title' => '【Woodo】2回目 【#はやまる中】']);
        Stream::factory()->create(['title' => '括弧なしのタイトル Woodo']);

        (new StreamTagger())->rebuildUnmatched();

        $this->assertDatabaseHas('unmatched_terms', ['term' => 'woodo', 'display' => 'Woodo', 'count' => 2]);
        $this->assertDatabaseHas('unmatched_terms', ['term' => '初見さん大歓迎', 'count' => 1]);
        $this->assertDatabaseHas('unmatched_terms', ['term' => 'はやまる中', 'count' => 1]);
        $this->assertDatabaseMissing('unmatched_terms', ['term' => 'minecraft']);
        $this->assertDatabaseMissing('unmatched_terms', ['term' => '新人vtuber']);
        $this->assertDatabaseMissing('unmatched_terms', ['term' => '隼丸ちゅん']);
    }

    public function test_streams_that_name_another_member_get_the_collab_category(): void
    {
        $hayamaru = Channel::factory()->create(['name' => 'Hayamaru ch. 隼丸ちゅん', 'handle' => '@hayamaru_chun']);
        $noluna = Channel::factory()->create(['name' => 'Noluna Ch. 閃光ノルナ', 'handle' => '@nolunach']);
        $collab = Stream::factory()->create(['channel_id' => $hayamaru->id, 'title' => '【雑談】閃光ノルナと一緒に！']);
        $self = Stream::factory()->create(['channel_id' => $hayamaru->id, 'title' => '【縦型雑談】【#はやまる中】隼丸ちゅんの朝']);
        $solo = Stream::factory()->create(['channel_id' => $noluna->id, 'title' => '【歌枠】ひとりで歌う']);

        (new StreamTagger())->retagAll();

        $this->assertSame(['コラボ'], $collab->fresh()->tags->pluck('name')->all());
        $this->assertSame('category', Tag::where('name', 'コラボ')->first()->kind);
        $this->assertSame([], $self->fresh()->tags->pluck('name')->all());   // naming yourself is not a collab
        $this->assertSame([], $solo->fresh()->tags->pluck('name')->all());
        // The word itself in a title also counts, through the tag's own alias.
        $word = Stream::factory()->create(['channel_id' => $noluna->id, 'title' => '【初心者コラボ】']);
        (new StreamTagger())->tag($word);
        $this->assertSame(['コラボ'], $word->fresh()->tags->pluck('name')->all());
    }

    public function test_retag_all_applies_a_new_alias_to_past_streams(): void
    {
        $mc = Tag::createWithAlias('Minecraft', 'game');
        $old = Stream::factory()->create(['title' => '【マイクラ】昔の配信']);
        TagAlias::create(['tag_id' => $mc->id, 'alias' => 'マイクラ']);

        $n = (new StreamTagger())->retagAll();

        $this->assertSame(1, $n);
        $this->assertSame(['Minecraft'], $old->fresh()->tags->pluck('name')->all());
    }
}
