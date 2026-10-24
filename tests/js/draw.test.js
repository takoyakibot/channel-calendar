import { test } from 'node:test';
import assert from 'node:assert/strict';
import { isMissingThumbnail, GENRE_PALETTE } from '../../resources/js/share-image/draw.js';

test("YouTube's 120x90 grey placeholder for a deleted video counts as missing", () => {
    assert.equal(isMissingThumbnail('https://i.ytimg.com/vi/gone/mqdefault.jpg', 120, 90), true);
    assert.equal(isMissingThumbnail('https://i.ytimg.com/vi/ok/mqdefault.jpg', 320, 180), false);
    assert.equal(isMissingThumbnail('https://static-cdn.jtvnw.net/x-120x90.jpg', 120, 90), false);
});

test('the palette has enough distinct colours for a busy month', () => {
    assert.ok(GENRE_PALETTE.length >= 16);
    assert.equal(new Set(GENRE_PALETTE).size, GENRE_PALETTE.length);
});

import { drawLegend } from '../../resources/js/share-image/draw.js';

function mockCtx() {
    const texts = [];
    return {
        texts,
        save() {}, restore() {}, fillRect() {}, beginPath() {}, moveTo() {}, arcTo() {}, closePath() {}, fill() {},
        measureText: (s) => ({ width: [...s].length * 18 }),
        fillText: (s) => texts.push(s),
        set font(v) {}, set fillStyle(v) {}, set textAlign(v) {}, set textBaseline(v) {},
    };
}

test('every legend genre is either drawn or counted in 「ほか n ジャンル」', () => {
    for (let count = 8; count <= 24; count++) {
        const entries = Array.from({ length: count }, (_, i) => ({
            name: i === count - 1 ? 'とても長いゲームタイトルの名前' : `ジャンル${i}`, count: 1, color: '#000',
        }));
        const ctx = mockCtx();
        drawLegend(ctx, entries, { x: 0, y: 820, w: 1600, h: 80 }, '2026年10月の配信');
        const drawn = ctx.texts.filter((t) => /^(ジャンル\d+|とても長い.*) 1$/.test(t)).length;
        const more = ctx.texts.map((t) => /^ほか (\d+) ジャンル$/.exec(t)).find(Boolean);
        assert.equal(drawn + (more ? Number(more[1]) : 0), count, `${count} entries`);
    }
});
