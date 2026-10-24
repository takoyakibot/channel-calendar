import { test } from 'node:test';
import assert from 'node:assert/strict';
import { shareOrDownload, shareText, monthLabel } from '../../resources/js/share-image/index.js';

const blob = new Blob(['x'], { type: 'image/png' });
const fail = () => assert.fail('should not be called');

test('shares the file when the browser can', async () => {
    const calls = [];
    const nav = { canShare: () => true, share: async (d) => { calls.push(d); } };
    assert.equal(await shareOrDownload(blob, 'channel-calendar-2026-10.png', 'text', 'https://x', { navigator: nav, open: fail, download: fail }), 'shared');
    assert.equal(calls[0].files[0].name, 'channel-calendar-2026-10.png');
    assert.equal(calls[0].text, 'text https://x');
});

test('a cancelled share does nothing else', async () => {
    const nav = { canShare: () => true, share: async () => { throw Object.assign(new Error('x'), { name: 'AbortError' }); } };
    assert.equal(await shareOrDownload(blob, 'f.png', 't', 'u', { navigator: nav, open: fail, download: fail }), 'cancelled');
});

test('falls back to download + X intent when files cannot be shared', async () => {
    const opened = [];
    const saved = [];
    const res = await shareOrDownload(blob, 'channel-calendar-2026-10.png', '天和うる 2026年10月の配信', 'https://c/amawauru?view=month',
        { navigator: {}, open: (u) => opened.push(u), download: (b, name) => saved.push(name) });
    assert.equal(res, 'downloaded');
    assert.deepEqual(saved, ['channel-calendar-2026-10.png']);
    assert.match(opened[0], /^https:\/\/x\.com\/intent\/tweet\?/);
    const q = new URL(opened[0]).searchParams;
    assert.equal(q.get('text'), '天和うる 2026年10月の配信');
    assert.equal(q.get('url'), 'https://c/amawauru?view=month');
});

test('a share that fails for another reason falls back to download', async () => {
    const saved = [];
    const nav = { canShare: () => true, share: async () => { throw new Error('NotAllowedError'); } };
    assert.equal(await shareOrDownload(blob, 'f.png', 't', 'u', { navigator: nav, open: () => {}, download: (b, n) => saved.push(n) }), 'downloaded');
    assert.deepEqual(saved, ['f.png']);
});

test('shareText', () => {
    assert.equal(shareText('天和うる', '2026年10月'), '天和うる 2026年10月の配信');
    assert.equal(shareText('', '2026年10月'), '2026年10月の配信');
});

test('monthLabel', () => {
    assert.equal(monthLabel(new Date(2026, 9, 1)), '2026年10月');
});
