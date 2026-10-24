/**
 * The month share dialog (#83): pick a pattern, preview it, make a PNG and share it.
 * The calendar page provides window.ccShare (month events, title, state URL, month renderer);
 * nothing here touches the DOM until open() is called.
 */
import { toShareItems } from './items.js';
import { drawMosaic, drawStatsBand, loadImages } from './draw.js';
import { monthStats, STAT_TILES } from './stats.js';

// html2canvas-pro: the maintained fork that understands color-mix(), which the month grid's tint uses.
const HTML2CANVAS_URL = 'https://cdn.jsdelivr.net/npm/html2canvas-pro@1.5.11/dist/html2canvas-pro.min.js';

export function monthLabel(date) {
    return `${date.getFullYear()}年${date.getMonth() + 1}月`;
}

/** "天和うる 2026年10月の配信" — the image heading and the text of the post. */
export function shareText(title, label) {
    return `${title ? `${title} ` : ''}${label}の配信`;
}

function defaultDeps() {
    return {
        navigator: globalThis.navigator,
        open: (url) => window.open(url, '_blank', 'noopener,noreferrer'),
        download: (blob, filename) => {
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            a.remove();
            setTimeout(() => URL.revokeObjectURL(a.href), 10000);
        },
    };
}

/**
 * Phones: the share sheet with the picture attached. Elsewhere (or if sharing fails): save the
 * PNG and open X's compose page — its intent URL cannot carry an image, so it is attached by hand.
 * @returns {Promise<'shared'|'cancelled'|'downloaded'>}
 */
export async function shareOrDownload(blob, filename, text, url, deps = defaultDeps()) {
    const file = new File([blob], filename, { type: 'image/png' });
    const nav = deps.navigator || {};
    if (nav.canShare && nav.share && nav.canShare({ files: [file] })) {
        try {
            await nav.share({ files: [file], text: `${text} ${url}` });
            return 'shared';
        } catch (e) {
            if (e && e.name === 'AbortError') return 'cancelled';
        }
    }
    deps.download(blob, filename);
    deps.open(`https://x.com/intent/tweet?${new URLSearchParams({ text, url })}`);
    return 'downloaded';
}

const state = {
    pattern: 'd',
    limits: { game: 2, cat: 1 },
    items: [],
    images: null,
    heading: '',
    filename: '',
    canvas: null,
    busy: false,
    snapshot: null, // the month grid as a canvas, made once per open for A and B
    snapshotJob: null,
};

let html2canvasJob = null;
/** Loaded only when someone first asks for A or B; D needs none of it. */
function loadHtml2canvas() {
    if (window.html2canvas) return Promise.resolve(window.html2canvas);
    html2canvasJob ||= new Promise((resolve, reject) => {
        const s = document.createElement('script');
        s.src = HTML2CANVAS_URL;
        s.onload = () => resolve(window.html2canvas);
        s.onerror = () => { html2canvasJob = null; reject(new Error('html2canvas failed to load')); };
        document.head.appendChild(s);
    });
    return html2canvasJob;
}

/** The month grid drawn off-screen at desktop width (readable even when shared from a phone). */
async function snapshotMonth() {
    const html2canvas = await loadHtml2canvas();
    const wrap = document.createElement('div');
    wrap.className = 'cc-light si-snapshot';
    document.body.appendChild(wrap);
    try {
        const copy = await window.ccShare.renderMonth(wrap);
        const canvas = await html2canvas(wrap, { useCORS: true, scale: 2, backgroundColor: '#ffffff', logging: false });
        copy.destroy();
        return canvas;
    } finally {
        wrap.remove();
    }
}

/** A: heading band + month grid. B: heading and the numbers + month grid. */
function composeMonth(snapshot, withStats) {
    const bandH = withStats ? 300 : 120;
    const canvas = document.createElement('canvas');
    canvas.width = snapshot.width;
    canvas.height = snapshot.height + bandH;
    const ctx = canvas.getContext('2d');
    const stats = withStats ? monthStats(state.items) : null;
    const tiles = stats && STAT_TILES.map((t) => ({ key: t.key, label: t.label, value: t.format(stats[t.key]) }));
    drawStatsBand(ctx, tiles, { x: 0, y: 0, w: canvas.width, h: bandH }, state.heading);
    ctx.drawImage(snapshot, 0, bandH);
    return canvas;
}

function $(id) { return document.getElementById(id); }

function setNote(text) { $('si-note').textContent = text; }

function renderPreview() {
    const preview = $('si-preview');
    const submit = $('si-submit');
    document.querySelectorAll('#si-tabs [data-pattern]').forEach((b) => {
        b.classList.toggle('is-active', b.dataset.pattern === state.pattern);
        b.setAttribute('aria-selected', String(b.dataset.pattern === state.pattern));
    });
    $('si-steps').hidden = state.pattern !== 'd';
    state.canvas = null;

    if (!state.items.length) {
        preview.textContent = 'この月の配信はありません';
        preview.classList.add('is-empty');
        submit.disabled = true;
        setNote('');
        return;
    }
    preview.classList.remove('is-empty');

    if (state.pattern !== 'd') {
        setNote('');
        if (!state.snapshot) {
            preview.textContent = '作成中…';
            submit.disabled = true;
            if (!state.snapshotJob) {
                const items = state.items;
                state.snapshotJob = snapshotMonth().then((canvas) => {
                    if (state.items !== items) return; // reopened for another month meanwhile
                    state.snapshot = canvas;
                    renderPreview();
                }).catch(() => {
                    if (state.items !== items) return;
                    state.snapshotJob = null;
                    if (state.pattern !== 'd') preview.textContent = '画像を作れませんでした。時間をおいてもう一度お試しください。';
                });
            }
            return;
        }
        const canvas = composeMonth(state.snapshot, state.pattern === 'b');
        preview.replaceChildren(canvas);
        state.canvas = canvas;
        submit.disabled = false;
        return;
    }
    if (!state.images) {
        preview.textContent = 'サムネイルを読み込み中…';
        submit.disabled = true;
        return;
    }

    const canvas = document.createElement('canvas');
    const res = drawMosaic(canvas, state.items, {
        title: state.heading, gameMax: state.limits.game, categoryMax: state.limits.cat, images: state.images,
    });
    preview.replaceChildren(canvas);
    state.canvas = canvas;
    submit.disabled = false;
    const parts = [];
    if (res.foldedGames.length) parts.push(`ゲームにまとめた：${res.foldedGames.join('、')}`);
    if (res.foldedCategories.length) parts.push(`その他にまとめた：${res.foldedCategories.join('、')}`);
    setNote(parts.join(' ／ '));
}

function close() { $('share-image-modal').hidden = true; }

async function submit() {
    if (!state.canvas || state.busy) return;
    const btn = $('si-submit');
    state.busy = true;
    btn.disabled = true;
    btn.textContent = '作成中…';
    try {
        const blob = await new Promise((resolve) => state.canvas.toBlob(resolve, 'image/png'));
        if (!blob) throw new Error('toBlob returned null');
        const result = await shareOrDownload(blob, state.filename, state.heading, window.ccShare.stateUrl());
        if (result !== 'cancelled') close();
    } catch (e) {
        setNote('画像を作れませんでした。時間をおいてもう一度お試しください。');
    } finally {
        state.busy = false;
        btn.disabled = false;
        btn.textContent = '画像を作って共有';
    }
}

let wired = false;
function wire() {
    if (wired) return;
    wired = true;
    document.querySelectorAll('#si-tabs [data-pattern]').forEach((b) => b.addEventListener('click', () => {
        state.pattern = b.dataset.pattern;
        renderPreview();
    }));
    document.querySelectorAll('#si-steps [data-step]').forEach((b) => b.addEventListener('click', () => {
        const key = b.dataset.step;
        state.limits[key] = Math.max(0, state.limits[key] + Number(b.dataset.delta));
        $(`si-${key}-max`).textContent = String(state.limits[key]);
        renderPreview();
    }));
    $('si-cancel').addEventListener('click', close);
    $('si-submit').addEventListener('click', submit);
    $('share-image-modal').addEventListener('click', (e) => { if (e.target === e.currentTarget) close(); });
}

export function open() {
    wire();
    const { events, start, end } = window.ccShare.monthEvents();
    const label = monthLabel(start);
    state.items = toShareItems(events, start, end);
    state.heading = shareText(window.ccShare.title(), label);
    state.filename = `channel-calendar-${start.getFullYear()}-${String(start.getMonth() + 1).padStart(2, '0')}.png`;
    state.images = null;
    state.snapshot = null;
    state.snapshotJob = null;
    $('si-heading').textContent = `📷 ${label}の配信結果を共有`;
    $('si-game-max').textContent = String(state.limits.game);
    $('si-cat-max').textContent = String(state.limits.cat);
    $('share-image-modal').hidden = false;
    renderPreview();

    const items = state.items;
    loadImages(items.map((i) => i.thumb)).then((images) => {
        if (state.items !== items) return; // reopened for another month meanwhile
        state.images = images;
        renderPreview();
    });
}

if (typeof window !== 'undefined') {
    window.ccShareImage = { open };
}
