/**
 * The month share dialog (#83): pick a pattern, preview it, make a PNG and share it.
 * The calendar page provides window.ccShare (month events, title, state URL);
 * nothing here touches the DOM until open() is called.
 */
import { toShareItems, mosaicItems } from './items.js';
import { drawMosaic, loadImages } from './draw.js';
import { drawMonthImage } from './month.js';
import { monthStats, STAT_TILES } from './stats.js';
import { PRESETS, presetRange, presetFor, rangeFromInputs, periodLabel, periodFilename, toInputValue } from './period.js';

export function monthLabel(date) {
    return `${date.getFullYear()}年${date.getMonth() + 1}月`;
}

/** "天和うる 2026年10月の配信" — the image heading and the text of the post. */
export function shareText(title, label) {
    return `${title ? `${title} ` : ''}${label}の配信`;
}

/** Phones and tablets get the share sheet; a mouse-driven computer gets copy + X. */
export function isMobile() {
    return Boolean(globalThis.matchMedia && globalThis.matchMedia('(pointer: coarse)').matches);
}

function defaultDeps() {
    return {
        navigator: globalThis.navigator,
        mobile: isMobile(),
        ClipboardItem: globalThis.ClipboardItem,
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
 * Phones: the share sheet with the picture attached (the X app is in it).
 * Computers: the OS share sheet has no X and its "Copy" pastes the picture twice into x.com,
 * so the PNG goes to the clipboard once and X's compose page opens, ready for ⌘V.
 * If neither works: save the PNG and open X's compose page to attach it by hand.
 * @returns {Promise<'shared'|'cancelled'|'copied'|'downloaded'>}
 */
export async function shareOrDownload(blob, filename, text, url, deps = defaultDeps()) {
    const nav = deps.navigator || {};
    const intent = `https://x.com/intent/tweet?${new URLSearchParams({ text, url })}`;
    if (deps.mobile) {
        const file = new File([blob], filename, { type: 'image/png' });
        if (nav.canShare && nav.share && nav.canShare({ files: [file] })) {
            try {
                await nav.share({ files: [file], text: `${text} ${url}` });
                return 'shared';
            } catch (e) {
                if (e && e.name === 'AbortError') return 'cancelled';
            }
        }
    } else if (nav.clipboard && nav.clipboard.write && deps.ClipboardItem) {
        try {
            // Before opening X: Chrome only writes the clipboard while this tab has focus.
            await nav.clipboard.write([new deps.ClipboardItem({ 'image/png': blob })]);
            deps.open(intent);
            return 'copied';
        } catch (e) {
            // fall through to saving the file
        }
    }
    deps.download(blob, filename);
    deps.open(intent);
    return 'downloaded';
}

const state = {
    pattern: 'd',
    limits: { game: 2, cat: 1 },
    items: [],
    mosaic: [], // items for D: without Twitch, over state.period (null while loading)
    images: null,
    mosaicImages: null,
    monthStart: null,
    monthLabel: '',
    period: null, // {start, end} of D; starts as the month on screen
    periodLoad: 0, // bumps on every period change, so a slow answer for an old period is dropped
    heading: '',
    filename: '',
    canvas: null,
    blob: null, // the PNG of canvas, encoded as soon as it is shown
    busy: false,
};

function $(id) { return document.getElementById(id); }

function setNote(text) { $('si-note').textContent = text; }

/**
 * Show a finished picture and encode its PNG right away: Safari only lets share() run straight
 * from the tap, so the file must already exist when the button is pressed.
 */
function showCanvas(canvas) {
    const submit = $('si-submit');
    $('si-preview').replaceChildren(canvas);
    state.canvas = canvas;
    state.blob = null;
    submit.disabled = true;
    canvas.toBlob((blob) => {
        if (state.canvas !== canvas) return; // replaced meanwhile
        state.blob = blob;
        submit.disabled = !blob;
        if (!blob) setNote('画像を作れませんでした。時間をおいてもう一度お試しください。');
    }, 'image/png');
}

function renderPreview() {
    const preview = $('si-preview');
    const submit = $('si-submit');
    document.querySelectorAll('#si-tabs [data-pattern]').forEach((b) => {
        b.classList.toggle('is-active', b.dataset.pattern === state.pattern);
        b.setAttribute('aria-selected', String(b.dataset.pattern === state.pattern));
    });
    const isD = state.pattern === 'd';
    $('si-steps').hidden = !isD;
    $('si-period').hidden = !isD;
    const label = isD ? periodLabel(state.period) : state.monthLabel;
    state.heading = shareText(window.ccShare.title(), label);
    state.filename = isD ? periodFilename(state.period) : periodFilename({ start: state.monthStart, end: state.monthEnd });
    $('si-heading').textContent = `📷 ${label}の配信結果を共有`;
    state.canvas = null;
    state.blob = null;

    if (isD && !state.mosaic) {
        preview.classList.remove('is-empty');
        preview.textContent = '配信を読み込み中…';
        submit.disabled = true;
        setNote('');
        return;
    }
    if (!(isD ? state.mosaic : state.items).length) {
        preview.textContent = isD ? 'この期間の配信はありません' : 'この月の配信はありません';
        preview.classList.add('is-empty');
        submit.disabled = true;
        setNote('');
        return;
    }
    preview.classList.remove('is-empty');

    if (!(isD ? state.mosaicImages : state.images)) {
        preview.textContent = 'サムネイルを読み込み中…';
        submit.disabled = true;
        return;
    }

    const canvas = document.createElement('canvas');
    if (state.pattern !== 'd') {
        const stats = state.pattern === 'b' ? monthStats(state.items) : null;
        const tiles = stats && STAT_TILES.map((t) => ({ key: t.key, label: t.label, value: t.format(stats[t.key]) }));
        drawMonthImage(canvas, state.items, { monthStart: state.monthStart, title: state.heading, tiles, images: state.images });
        showCanvas(canvas);
        setNote('');
        return;
    }
    const res = drawMosaic(canvas, state.mosaic, {
        title: state.heading, gameMax: state.limits.game, categoryMax: state.limits.cat, images: state.mosaicImages,
    });
    showCanvas(canvas);
    const parts = [];
    if (res.foldedGames.length) parts.push(`ゲームにまとめた：${res.foldedGames.join('、')}`);
    if (res.foldedCategories.length) parts.push(`その他にまとめた：${res.foldedCategories.join('、')}`);
    setNote(parts.join(' ／ '));
}

/** Fill the preset menu and date inputs for the current period. */
function showPeriod() {
    $('si-preset').value = presetFor(state.period);
    $('si-from').value = toInputValue(state.period.start);
    $('si-to').value = toInputValue(new Date(state.period.end.getFullYear(), state.period.end.getMonth(), state.period.end.getDate() - 1));
    $('si-period-error').hidden = true;
}

/** Switch D to another period: fetch its events, then its pictures. */
function setPeriod(range) {
    state.period = range;
    showPeriod();
    const load = ++state.periodLoad;
    state.mosaic = null;
    state.mosaicImages = null;
    renderPreview();
    window.ccShare.fetchEvents(range.start, range.end).then((events) => {
        if (load !== state.periodLoad) return;
        state.mosaic = mosaicItems(toShareItems(events, range.start, range.end));
        renderPreview();
        return loadImages(state.mosaic.flatMap((i) => [i.thumb, i.avatar])).then((images) => {
            if (load !== state.periodLoad) return;
            state.mosaicImages = images;
            renderPreview();
        });
    }).catch(() => {
        if (load !== state.periodLoad) return;
        state.mosaic = [];
        state.mosaicImages = new Map();
        renderPreview();
        setNote('配信を読み込めませんでした。時間をおいてもう一度お試しください。');
    });
}

function onDatesChanged() {
    const range = rangeFromInputs($('si-from').value, $('si-to').value);
    if (range.error) {
        $('si-period-error').textContent = range.error;
        $('si-period-error').hidden = false;
        return;
    }
    setPeriod(range);
}

function close() { $('share-image-modal').hidden = true; }

async function submit() {
    if (!state.blob || state.busy) return;
    const btn = $('si-submit');
    state.busy = true;
    btn.disabled = true;
    try {
        // No await before this call: share() has to start inside the tap (see showCanvas).
        const result = await shareOrDownload(state.blob, state.filename, state.heading, window.ccShare.stateUrl());
        if (result !== 'cancelled') close();
    } catch (e) {
        setNote('画像を作れませんでした。時間をおいてもう一度お試しください。');
    } finally {
        state.busy = false;
        btn.disabled = !state.blob;
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
    const preset = $('si-preset');
    preset.replaceChildren(...PRESETS.map((p) => new Option(p.label, p.key)));
    preset.addEventListener('change', () => {
        const range = presetRange(preset.value);
        if (range) setPeriod(range);
        else $('si-from').focus();   // 期間を指定: keep the dates, let them be edited
    });
    $('si-from').addEventListener('change', onDatesChanged);
    $('si-to').addEventListener('change', onDatesChanged);
    $('si-cancel').addEventListener('click', close);
    $('si-submit').addEventListener('click', submit);
    $('share-image-modal').addEventListener('click', (e) => { if (e.target === e.currentTarget) close(); });
}

const sameRange = (a, b) => +a.start === +b.start && +a.end === +b.end;

export function open() {
    wire();
    const { events, start, end } = window.ccShare.monthEvents();
    state.items = toShareItems(events, start, end);
    // D starts on the month on screen, from the same events; another period is fetched on demand.
    state.periodLoad++;
    state.period = { start, end };
    state.mosaic = mosaicItems(state.items);
    state.images = null;
    state.mosaicImages = null;
    state.monthStart = start;
    state.monthEnd = end;
    state.monthLabel = monthLabel(start);
    showPeriod();
    $('si-submit').textContent = isMobile() ? '画像を作って共有' : '画像をコピーして X を開く';
    $('si-game-max').textContent = String(state.limits.game);
    $('si-cat-max').textContent = String(state.limits.cat);
    $('share-image-modal').hidden = false;
    renderPreview();

    const items = state.items;
    // Every picture any tab needs: the thumbnails (Twitch too, for the calendar) and the channel icons.
    loadImages(items.flatMap((i) => [i.thumb, i.avatar])).then((images) => {
        if (state.items !== items) return; // reopened for another month meanwhile
        state.images = images;
        if (state.mosaicImages === null && sameRange(state.period, { start: state.monthStart, end: state.monthEnd })) state.mosaicImages = images;
        renderPreview();
    });
}

if (typeof window !== 'undefined') {
    window.ccShareImage = { open };
}
