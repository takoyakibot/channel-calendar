/**
 * Canvas drawing for the share image. Pattern D (the genre mosaic) is drawn here from scratch;
 * A and B are a snapshot of the month view with a band of numbers on top (drawStatsBand).
 */
import { groupByGenre } from './genres.js';
import { packBlocks } from './layout.js';

export const GENRE_PALETTE = [
    '#f4a261', '#7fb7e6', '#8fd19e', '#e98bb5', '#c9a8f0', '#f2d06b', '#7fd6cf', '#f08a80',
    '#a3b8f5', '#b8d98a', '#d9a066', '#5fa8a0', '#e0c3fc', '#9bb0c1', '#f7b2bd', '#c2d36b',
];
export const OTHER_COLOR = '#c8c2b8';
export const BG_COLOR = '#f4f1ec';
export const SITE_LABEL = 'calendar.alpacasandbag.jp';

const FONT = '"Hiragino Sans", "Hiragino Kaku Gothic ProN", "Noto Sans JP", "Yu Gothic", sans-serif';
const W = 1600;
const H = 900;
const LEGEND_H = 80;
const PAD = 24;

/** A deleted YouTube video still answers with a 120x90 grey placeholder (status 404, but the image loads). */
export function isMissingThumbnail(url, width, height) {
    return /i\.ytimg\.com\//.test(url) && width === 120 && height === 90;
}

/**
 * Loads every picture with CORS so the canvas stays exportable. A picture that fails
 * (deleted video, expired Twitch VOD, no CORS) maps to null and is drawn as a plain panel.
 * @param {string[]} urls
 * @returns {Promise<Map<string, HTMLImageElement|null>>}
 */
export function loadImages(urls) {
    const unique = [...new Set(urls.filter(Boolean))];
    return Promise.all(unique.map((url) => new Promise((resolve) => {
        const img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = () => resolve([url, isMissingThumbnail(url, img.naturalWidth, img.naturalHeight) ? null : img]);
        img.onerror = () => resolve([url, null]);
        img.src = url;
    }))).then((pairs) => new Map(pairs));
}

function roundRect(ctx, x, y, w, h, r) {
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + w, y, x + w, y + h, r);
    ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r);
    ctx.arcTo(x, y, x + w, y, r);
    ctx.closePath();
}

/** A thumbnail that could not be loaded: the channel colour, faded onto the background. */
function drawFallback(ctx, item, x, y, w, h) {
    ctx.save();
    roundRect(ctx, x, y, w, h, 2);
    ctx.fillStyle = BG_COLOR;
    ctx.fill();
    ctx.globalAlpha = 0.3;
    ctx.fillStyle = item.color || '#9ca3af';
    ctx.fill();
    ctx.restore();
}

function drawTile(ctx, item, img, x, y, w, h) {
    if (!img) { drawFallback(ctx, item, x, y, w, h); return; }
    ctx.save();
    roundRect(ctx, x, y, w, h, 2);
    ctx.clip();
    ctx.drawImage(img, x, y, w, h);
    ctx.restore();
}

/** Shorts: the 16:9 thumbnail is the vertical video with blurred sides — keep the 9:16 middle. */
function drawShortTile(ctx, item, img, x, y, w, h) {
    if (!img) { drawFallback(ctx, item, x, y, w, h); return; }
    const sh = img.naturalHeight;
    const sw = Math.min(img.naturalWidth, sh * 9 / 16);
    const sx = (img.naturalWidth - sw) / 2;
    ctx.save();
    roundRect(ctx, x, y, w, h, 2);
    ctx.clip();
    ctx.drawImage(img, sx, 0, sw, sh, x, y, w, h);
    ctx.restore();
}

/**
 * The strip along the bottom of D: title, then colour · name · count for each genre, the site on the right.
 * Two lines at most; whatever does not fit becomes 「ほか n ジャンル」.
 * @param {CanvasRenderingContext2D} ctx
 * @param {{name: string, count: number, color: string}[]} entries
 * @param {{x: number, y: number, w: number, h: number}} box
 * @param {string} title
 */
export function drawLegend(ctx, entries, box, title) {
    ctx.save();
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(box.x, box.y, box.w, box.h);
    ctx.fillStyle = '#e5e0d8';
    ctx.fillRect(box.x, box.y, box.w, 1);

    const lineH = 30;
    const top = box.y + (box.h - lineH * 2) / 2 + lineH / 2;
    const left = box.x + PAD;
    const right = box.x + box.w - PAD;
    ctx.textBaseline = 'middle';

    ctx.font = `14px ${FONT}`;
    ctx.fillStyle = '#9ca3af';
    ctx.textAlign = 'right';
    ctx.fillText(SITE_LABEL, right, top);
    const lineEnds = [right - ctx.measureText(SITE_LABEL).width - 24, right];

    ctx.textAlign = 'left';
    ctx.font = `bold 20px ${FONT}`;
    ctx.fillStyle = '#1f2937';
    ctx.fillText(title, left, top);

    ctx.font = `17px ${FONT}`;
    const chip = 16;
    const gap = 22;
    const widthOf = (e) => chip + 6 + ctx.measureText(`${e.name} ${e.count}`).width;
    let line = 0;
    let x = title ? left + measureBold(ctx, title) + 28 : left;
    for (let i = 0; i < entries.length; i++) {
        const e = entries[i];
        const rest = entries.length - i - 1;
        const moreLabel = `ほか ${rest + 1} ジャンル`;
        const w = widthOf(e);
        if (x + w > lineEnds[line] && line === 0) { line = 1; x = left; }
        // On the last line, keep room for 「ほか n ジャンル」 unless this is the final entry.
        const reserve = rest ? ctx.measureText(`ほか ${rest} ジャンル`).width + gap : 0;
        if (line === 1 && x + w + reserve > lineEnds[1]) {
            ctx.fillStyle = '#6b7280';
            ctx.fillText(moreLabel, x, top + lineH);
            break;
        }
        const y = top + line * lineH;
        roundRect(ctx, x, y - chip / 2, chip, chip, 3);
        ctx.fillStyle = e.color;
        ctx.fill();
        ctx.fillStyle = '#374151';
        ctx.fillText(`${e.name} ${e.count}`, x + chip + 6, y);
        x += w + gap;
    }
    ctx.restore();
}

function measureBold(ctx, text) {
    ctx.save();
    ctx.font = `bold 20px ${FONT}`;
    const w = ctx.measureText(text).width;
    ctx.restore();
    return w;
}

/**
 * Pattern D: genres as rectangles of whole tiles on a 1600x900 canvas, names only in the legend.
 * @param {HTMLCanvasElement} canvas
 * @param {import('./items.js').ShareItem[]} items
 * @param {{title: string, gameMax: number, categoryMax: number, images: Map<string, HTMLImageElement|null>}} opts
 * @returns {{foldedGames: string[], foldedCategories: string[]}}
 */
export function drawMosaic(canvas, items, { title, gameMax, categoryMax, images }) {
    canvas.width = W;
    canvas.height = H;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = BG_COLOR;
    ctx.fillRect(0, 0, W, H);

    const { blocks, foldedGames, foldedCategories } = groupByGenre(items, { gameMax, categoryMax });
    const colors = [];
    let next = 0;
    blocks.forEach((b) => { colors.push(b.isOther ? OTHER_COLOR : GENRE_PALETTE[next++ % GENRE_PALETTE.length]); });

    const area = { x: PAD, y: PAD, w: W - PAD * 2, h: H - LEGEND_H - PAD * 1.5 };
    if (!blocks.length) {
        ctx.fillStyle = '#6b7280';
        ctx.font = `24px ${FONT}`;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText('この月の配信はありません', W / 2, area.y + area.h / 2);
    } else {
        const board = packBlocks(blocks, area);
        const { tileW, tileH } = board;
        const ox = area.x + (area.w - board.cols * tileW) / 2;
        const oy = area.y + (area.h - board.rows * tileH) / 2;
        const inset = Math.max(2, tileW * 0.035);

        board.placed.forEach((p) => {
            const block = blocks[p.index];
            const bx = ox + p.x * tileW;
            const by = oy + p.y * tileH;
            roundRect(ctx, bx, by, p.c * tileW, p.r * tileH, 3);
            ctx.fillStyle = colors[p.index];
            ctx.fill();

            const isShorts = block.name === 'ショート';
            block.items.forEach((item, i) => {
                const cell = isShorts ? Math.floor(i / 3) : i;
                const cx = bx + (cell % p.c) * tileW + inset;
                const cy = by + Math.floor(cell / p.c) * tileH + inset;
                const iw = tileW - inset * 2;
                const ih = tileH - inset * 2;
                if (isShorts) {
                    // Three 9:16 crops share one 16:9 cell.
                    const sw = ih * 9 / 16;
                    const gap = (iw - sw * 3) / 2;
                    drawShortTile(ctx, item, images.get(item.thumb) || null, cx + (i % 3) * (sw + gap), cy, sw, ih);
                } else {
                    drawTile(ctx, item, images.get(item.thumb) || null, cx, cy, iw, ih);
                }
            });
        });
    }

    drawLegend(ctx, blocks.map((b, i) => ({ name: b.name, count: b.items.length, color: colors[i] })),
        { x: 0, y: H - LEGEND_H, w: W, h: LEGEND_H }, title);
    return { foldedGames, foldedCategories };
}

/**
 * The band above the month snapshot (patterns A and B): the heading, and for B the numbers
 * from STAT_TILES on the right. Drawn at the snapshot's 2x scale.
 * @param {CanvasRenderingContext2D} ctx
 * @param {{key: string, label: string, value: string}[]|null} tiles null for A (heading only)
 * @param {{x: number, y: number, w: number, h: number}} box
 * @param {string} title
 */
export function drawStatsBand(ctx, tiles, box, title) {
    ctx.save();
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(box.x, box.y, box.w, box.h);
    ctx.textBaseline = 'middle';
    ctx.textAlign = 'left';
    ctx.fillStyle = '#111827';
    ctx.font = `bold 44px ${FONT}`;
    ctx.fillText(title, box.x + 48, box.y + (tiles ? box.h / 2 - 20 : box.h / 2));
    ctx.font = `24px ${FONT}`;
    ctx.fillStyle = '#9ca3af';
    if (tiles) ctx.fillText(SITE_LABEL, box.x + 48, box.y + box.h / 2 + 40);

    if (tiles) {
        const tileW = 300;
        const tileH = box.h - 80;
        let x = box.x + box.w - 48 - tiles.length * tileW - (tiles.length - 1) * 24;
        for (const t of tiles) {
            roundRect(ctx, x, box.y + 40, tileW, tileH, 16);
            ctx.fillStyle = '#fff4ec';
            ctx.fill();
            ctx.textAlign = 'center';
            ctx.fillStyle = '#e07a3c';
            ctx.font = `bold 52px ${FONT}`;
            ctx.fillText(t.value, x + tileW / 2, box.y + 40 + tileH / 2 - 16);
            ctx.fillStyle = '#6b7280';
            ctx.font = `24px ${FONT}`;
            ctx.fillText(t.label, x + tileW / 2, box.y + 40 + tileH / 2 + 40);
            x += tileW + 24;
        }
    } else {
        ctx.textAlign = 'right';
        ctx.fillText(SITE_LABEL, box.x + box.w - 48, box.y + box.h / 2);
    }
    ctx.restore();
}
