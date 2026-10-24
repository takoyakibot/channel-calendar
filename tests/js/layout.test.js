import { test } from 'node:test';
import assert from 'node:assert/strict';
import { packBlocks } from '../../resources/js/share-image/layout.js';

function assertValid(blocks, res, area) {
    assert.equal(res.placed.length, blocks.length);
    assert.ok(Math.abs(res.tileW / res.tileH - 16 / 9) < 1e-9);
    assert.ok(res.cols * res.tileW <= area.w + 1e-6, 'board fits the width');
    assert.ok(res.rows * res.tileH <= area.h + 1e-6, 'board fits the height');
    const taken = new Set();
    for (const p of res.placed) {
        const cells = blocks[p.index].cells;
        assert.ok(p.x + p.c <= res.cols && p.y + p.r <= res.rows, 'inside the board');
        assert.ok(p.c * p.r >= cells, 'room for every tile');
        assert.ok(p.c * p.r - cells < Math.min(p.c, p.r), 'no fully empty row or column');
        for (let y = p.y; y < p.y + p.r; y++) {
            for (let x = p.x; x < p.x + p.c; x++) {
                assert.ok(!taken.has(`${x},${y}`), 'blocks do not overlap');
                taken.add(`${x},${y}`);
            }
        }
    }
}

test('the October example (4,3,3,2,1,1 cells) packs validly into 1600x820', () => {
    const area = { w: 1600, h: 820 };
    const blocks = [4, 3, 3, 2, 1, 1].map((cells) => ({ cells }));
    assertValid(blocks, packBlocks(blocks, area), area);
});

test('150 items over 20 genres still fit', () => {
    const area = { w: 1600, h: 820 };
    const blocks = Array.from({ length: 20 }, (_, i) => ({ cells: i < 5 ? 24 : 2 }));
    const res = packBlocks(blocks, area);
    assertValid(blocks, res, area);
    assert.ok(res.tileW > 40);
});

test('a single tile uses as much of the area as it can', () => {
    const res = packBlocks([{ cells: 1 }], { w: 1600, h: 820 });
    assert.equal(res.cols, 1);
    assert.ok(Math.abs(res.tileH - 820) < 1e-6);
});

test('no blocks gives an empty board', () => {
    assert.deepEqual(packBlocks([], { w: 1600, h: 820 }).placed, []);
});
