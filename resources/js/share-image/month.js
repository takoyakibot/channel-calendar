/**
 * The month calendar of the share image (the カレンダー and まとめ＋カレンダー tabs), drawn
 * straight onto a canvas like the genre mosaic. Copying the page with html2canvas took over
 * 30s on an iPhone for a busy month; drawing it ourselves takes a moment.
 */
import { drawStatsBand, drawTile, roundRect, FONT } from './draw.js';

const W = 2400;
const MARGIN = 48;
const HEAD_H = 56;
const DAY_H = 48;
const MIN_ROW_H = 180;
const EV_GAP = 8;
const EV_HEAD_H = 44;
const WEEKDAYS = ['日', '月', '火', '水', '木', '金', '土'];

/**
 * The month as Sunday-first weeks, only as many as it spans. Days of the neighbouring months
 * are blank cells (day null); every item sits on the day it starts, in time order.
 * @param {import('./items.js').ShareItem[]} items
 * @param {Date} monthStart local midnight of the 1st
 * @returns {{day: number|null, items: object[]}[][]}
 */
export function monthWeeks(items, monthStart) {
    const y = monthStart.getFullYear();
    const m = monthStart.getMonth();
    const days = new Date(y, m + 1, 0).getDate();
    const lead = new Date(y, m, 1).getDay();
    const cells = [];
    for (let i = 0; i < lead; i++) cells.push({ day: null, items: [] });
    for (let d = 1; d <= days; d++) cells.push({ day: d, items: [] });
    while (cells.length % 7) cells.push({ day: null, items: [] });
    for (const it of [...items].sort((a, b) => a.start - b.start)) {
        if (it.start.getFullYear() !== y || it.start.getMonth() !== m) continue;
        cells[lead + it.start.getDate() - 1].items.push(it);
    }
    const weeks = [];
    for (let i = 0; i < cells.length; i += 7) weeks.push(cells.slice(i, i + 7));
    return weeks;
}

/** What goes before the title, as on the page: the start time, or what kind of entry it is. */
export function eventLabel(item) {
    if (item.kind === 'guest') return '出演';
    if (item.kind === 'short') return 'Short';
    if (item.kind === 'upload') return '動画';
    if (item.status === 'live') return 'LIVE';
    const p = (n) => String(n).padStart(2, '0');
    return `${p(item.start.getHours())}:${p(item.start.getMinutes())}`;
}

/** text cut with an ellipsis to fit maxW. */
function fit(ctx, text, maxW) {
    if (ctx.measureText(text).width <= maxW) return text;
    let s = text;
    while (s && ctx.measureText(`${s}…`).width > maxW) s = s.slice(0, -1);
    return `${s}…`;
}

function eventHeight(item, colW) {
    const thumbH = item.thumb ? (colW - 32) * 9 / 16 + 8 : 0;
    return EV_HEAD_H + thumbH;
}

function drawEvent(ctx, item, images, x, y, w) {
    const h = eventHeight(item, w + 16);
    const color = item.color || '#9ca3af';
    ctx.save();
    roundRect(ctx, x, y, w, h, 8);
    ctx.fillStyle = '#ffffff';
    ctx.fill();
    ctx.globalAlpha = 0.18;
    ctx.fillStyle = color;
    ctx.fill();
    ctx.globalAlpha = 1;
    ctx.fillRect(x, y, 6, h);

    // avatar · label · title
    const cy = y + EV_HEAD_H / 2;
    const avatar = item.avatar ? images.get(item.avatar) : null;
    ctx.save();
    ctx.beginPath();
    ctx.arc(x + 30, cy, 15, 0, Math.PI * 2);
    ctx.clip();
    if (avatar) ctx.drawImage(avatar, x + 15, cy - 15, 30, 30);
    else { ctx.fillStyle = color; ctx.fillRect(x + 15, cy - 15, 30, 30); }
    ctx.restore();

    ctx.textBaseline = 'middle';
    ctx.textAlign = 'left';
    const label = eventLabel(item);
    ctx.font = `bold 23px ${FONT}`;
    ctx.fillStyle = item.status === 'live' ? '#dc2626' : '#111827';
    ctx.fillText(label, x + 52, cy);
    const tx = x + 52 + ctx.measureText(label).width + 10;
    ctx.font = `22px ${FONT}`;
    ctx.fillStyle = '#374151';
    ctx.fillText(fit(ctx, (item.membersOnly ? '🔒 ' : '') + item.title, x + w - 8 - tx), tx, cy);

    if (item.thumb) {
        const tw = w - 16;
        drawTile(ctx, item, images.get(item.thumb) || null, x + 12, y + EV_HEAD_H, tw, tw * 9 / 16);
    }
    ctx.restore();
}

/**
 * The whole picture: the heading band (with the numbers when tiles is given) over the month grid.
 * @param {HTMLCanvasElement} canvas
 * @param {object[]} items ShareItems of the month
 * @param {{monthStart: Date, title: string, tiles: {label: string, value: string}[]|null, images: Map}} opts
 */
export function drawMonthImage(canvas, items, { monthStart, title, tiles, images }) {
    const weeks = monthWeeks(items, monthStart);
    const colW = (W - MARGIN * 2) / 7;
    const rowHs = weeks.map((week) => Math.max(MIN_ROW_H, DAY_H + 8 + Math.max(0, ...week.map((c) =>
        c.items.reduce((s, it) => s + eventHeight(it, colW) + EV_GAP, 0)))));
    const bandH = tiles ? 300 : 120;
    canvas.width = W;
    canvas.height = bandH + HEAD_H + rowHs.reduce((s, h) => s + h, 0) + MARGIN;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    drawStatsBand(ctx, tiles, { x: 0, y: 0, w: W, h: bandH }, title);

    // weekday header
    let y = bandH;
    ctx.textBaseline = 'middle';
    ctx.textAlign = 'center';
    ctx.font = `bold 24px ${FONT}`;
    ctx.fillStyle = '#111827';
    WEEKDAYS.forEach((d, i) => ctx.fillText(d, MARGIN + colW * (i + 0.5), y + HEAD_H / 2));
    y += HEAD_H;

    weeks.forEach((week, r) => {
        week.forEach((cell, i) => {
            const x = MARGIN + colW * i;
            if (cell.day === null) {
                ctx.fillStyle = '#f3f4f6';
                ctx.fillRect(x, y, colW, rowHs[r]);
                return;
            }
            ctx.textAlign = 'right';
            ctx.textBaseline = 'middle';
            ctx.font = `24px ${FONT}`;
            ctx.fillStyle = '#111827';
            ctx.fillText(`${cell.day}日`, x + colW - 12, y + DAY_H / 2);
            let ey = y + DAY_H;
            for (const it of cell.items) {
                drawEvent(ctx, it, images, x + 8, ey, colW - 16);
                ey += eventHeight(it, colW) + EV_GAP;
            }
        });
        y += rowHs[r];
    });

    // grid lines over everything
    ctx.strokeStyle = '#e5e7eb';
    ctx.lineWidth = 2;
    const gridTop = bandH;
    const gridBottom = y;
    for (let i = 0; i <= 7; i++) {
        ctx.beginPath();
        ctx.moveTo(MARGIN + colW * i, gridTop);
        ctx.lineTo(MARGIN + colW * i, gridBottom);
        ctx.stroke();
    }
    let ly = gridTop;
    for (const h of [HEAD_H, ...rowHs]) {
        ctx.beginPath();
        ctx.moveTo(MARGIN, ly);
        ctx.lineTo(W - MARGIN, ly);
        ctx.stroke();
        ly += h;
    }
    ctx.beginPath();
    ctx.moveTo(MARGIN, ly);
    ctx.lineTo(W - MARGIN, ly);
    ctx.stroke();
}
