/**
 * Treemap-style packing for the mosaic: each genre is one rectangle of whole 16:9 tiles,
 * biggest first, so a genre never wraps across rows and no thumbnail gets cropped.
 */

/** Shapes (cols x rows in tiles) a block of n may take on a board W wide: no fully empty row or column. */
function shapes(n, W) {
    const out = [];
    for (let c = 1; c <= Math.min(n, W); c++) {
        const r = Math.ceil(n / c);
        if (c * r - n < Math.min(c, r)) out.push([c, r]);
    }
    return out;
}

/** Greedy pass on a board W tiles wide: top-most/left-most spot, shape that keeps the board lowest. */
function packWidth(blocks, W) {
    const occ = [];
    const free = (x, y, c, r) => {
        for (let j = y; j < y + r; j++) {
            for (let i = x; i < x + c; i++) {
                if (occ[j] && occ[j][i]) return false;
            }
        }
        return true;
    };
    const spot = (c, r) => {
        for (let y = 0; ; y++) {
            for (let x = 0; x + c <= W; x++) {
                if (free(x, y, c, r)) return [x, y];
            }
        }
    };

    let H = 0;
    const placed = blocks.map((b, index) => {
        let best = null;
        for (const [c, r] of shapes(b.cells, W)) {
            const [x, y] = spot(c, r);
            const score = Math.max(H, y + r) * 100 + Math.abs(c - r) + y;
            if (!best || score < best.score) best = { x, y, c, r, score };
        }
        for (let j = best.y; j < best.y + best.r; j++) {
            occ[j] = occ[j] || [];
            for (let i = best.x; i < best.x + best.c; i++) occ[j][i] = true;
        }
        H = Math.max(H, best.y + best.r);
        return { index, x: best.x, y: best.y, c: best.c, r: best.r };
    });
    return { H, placed };
}

/**
 * @param {{cells: number}[]} blocks in drawing order (biggest first)
 * @param {{w: number, h: number}} area pixels available for the board
 * @returns {{cols: number, rows: number, tileW: number, tileH: number, placed: {index: number, x: number, y: number, c: number, r: number}[]}}
 */
export function packBlocks(blocks, area) {
    const total = blocks.reduce((s, b) => s + b.cells, 0);
    if (!total) return { cols: 0, rows: 0, tileW: 0, tileH: 0, placed: [] };

    // Try every board width; keep the one whose tiles come out biggest (ties: the narrower board).
    let best = null;
    for (let W = 1; W <= total; W++) {
        const { H, placed } = packWidth(blocks, W);
        const tileW = Math.min(area.w / W, (area.h / H) * 16 / 9);
        if (!best || tileW > best.tileW) best = { cols: W, rows: H, tileW, tileH: tileW * 9 / 16, placed };
    }
    return best;
}
