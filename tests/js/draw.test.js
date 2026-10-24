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
