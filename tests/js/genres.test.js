import { test } from 'node:test';
import assert from 'node:assert/strict';
import { groupByGenre, LOW_PRIORITY_CATEGORIES } from '../../resources/js/share-image/genres.js';

let seq = 0;
const item = (tags, kind = 'stream') => ({ id: String(++seq), kind, tags, thumb: null, color: '#000' });
const game = (name) => ({ name, kind: 'game' });
const cat = (name) => ({ name, kind: 'category' });
const n = (count, tags) => Array.from({ length: count }, () => item(tags));
const names = (blocks) => blocks.map((b) => b.name);
const NONE = { gameMax: 0, categoryMax: 0 };

test('the low-priority categories are the four format tags', () => {
    assert.deepEqual(LOW_PRIORITY_CATEGORIES, ['メン限', 'コラボ', '参加型', '同時視聴']);
});

test('a game tag wins over categories; low-priority categories lose to any other tag', () => {
    const { blocks } = groupByGenre([
        item([cat('雑談'), cat('メン限')]), item([cat('メン限'), game('原神')]), item([cat('コラボ'), cat('朝活')]),
    ], NONE);
    assert.deepEqual(new Set(names(blocks)), new Set(['原神', '朝活', '雑談']));
});

test('a stream tagged only with a low-priority category gets that genre, not その他', () => {
    assert.deepEqual(names(groupByGenre([item([cat('メン限')])], NONE).blocks), ['メン限']);
});

test('untagged items are その他, always last; shorts are ショート with 3 per cell', () => {
    const { blocks } = groupByGenre([item([]), item([], 'short'), item([], 'short'), item([], 'short'), item([], 'short'), item([cat('雑談')])], NONE);
    assert.deepEqual(blocks.map((b) => [b.name, b.cells]), [['ショート', 2], ['雑談', 1], ['その他', 1]]);
    assert.equal(blocks[2].isOther, true);
});

test('bigger blocks come first', () => {
    const { blocks } = groupByGenre([item([cat('歌枠')]), ...n(3, [cat('雑談')]), ...n(2, [game('原神')])], NONE);
    assert.deepEqual(names(blocks), ['雑談', '原神', '歌枠']);
});

test('two or more small games fold into その他ゲーム while a big game keeps its block', () => {
    const r = groupByGenre([...n(3, [game('原神')]), item([game('A')]), item([game('B')])], { gameMax: 2, categoryMax: 0 });
    assert.deepEqual(names(r.blocks), ['原神', 'その他ゲーム']);
    assert.deepEqual(r.foldedGames, ['A', 'B']);
});

test('when every game folds the block is just ゲーム', () => {
    assert.deepEqual(names(groupByGenre([item([game('A')]), item([game('B')])], { gameMax: 2, categoryMax: 0 }).blocks), ['ゲーム']);
});

test('a lone small game keeps its own name', () => {
    assert.deepEqual(names(groupByGenre([...n(3, [game('原神')]), item([game('A')])], { gameMax: 2, categoryMax: 0 }).blocks), ['原神', 'A']);
});

test('small categories join その他; a lone one joins only when untagged items exist', () => {
    const r = groupByGenre([item([cat('朝活')]), item([cat('作業')]), ...n(2, [cat('雑談')])], { gameMax: 0, categoryMax: 1 });
    assert.deepEqual(names(r.blocks), ['雑談', 'その他']);
    assert.deepEqual(new Set(r.foldedCategories), new Set(['朝活', '作業']));
    assert.deepEqual(names(groupByGenre([item([cat('朝活')]), ...n(2, [cat('雑談')])], { gameMax: 0, categoryMax: 1 }).blocks), ['雑談', '朝活']);
    assert.deepEqual(names(groupByGenre([item([cat('朝活')]), item([]), ...n(2, [cat('雑談')])], { gameMax: 0, categoryMax: 1 }).blocks), ['雑談', 'その他']);
});

test('ショート and その他ゲーム are never folded away', () => {
    const r = groupByGenre([item([], 'short'), item([game('A')]), item([game('B')]), ...n(4, [cat('雑談')])], { gameMax: 5, categoryMax: 5 });
    assert.deepEqual(new Set(names(r.blocks)), new Set(['雑談', 'ゲーム', 'ショート']));
});

test('no items gives no blocks', () => {
    assert.deepEqual(groupByGenre([], NONE).blocks, []);
});
