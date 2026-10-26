import { test } from 'node:test';
import assert from 'node:assert/strict';
import { toShareItems, mosaicItems } from '../../resources/js/share-image/items.js';

const ev = (id, start, props) => ({
    id, title: id, start: new Date(start), backgroundColor: '#feb690',
    extendedProps: { channel_name: 'うる', thumbnail_url: 'https://i.ytimg.com/vi/x/default.jpg', tags: [], ...props },
});
const OCT = [new Date('2026-10-01T00:00:00+09:00'), new Date('2026-11-01T00:00:00+09:00')];

test('streams, videos, shorts and guest appearances go in; clips and manual entries do not', () => {
    const items = toShareItems([
        ev('s', '2026-10-05T20:00:00+09:00', { type: 'stream', status: 'completed' }),
        ev('u', '2026-10-06T20:00:00+09:00', { type: 'upload', status: 'completed' }),
        ev('sh', '2026-10-07T20:00:00+09:00', { type: 'short', status: 'completed' }),
        ev('g', '2026-10-08T20:00:00+09:00', { type: 'guest', status: 'posted' }),
        ev('c', '2026-10-09T20:00:00+09:00', { type: 'clip', status: 'posted' }),
        ev('m', '2026-10-10T20:00:00+09:00', { status: 'manual' }),
    ], ...OCT);
    assert.deepEqual(items.map(i => [i.id, i.kind]), [['s', 'stream'], ['u', 'upload'], ['sh', 'short'], ['g', 'guest']]);
});

test('days of the previous/next month shown at the grid edges are left out', () => {
    const items = toShareItems([
        ev('sep', '2026-09-30T23:30:00+09:00', { type: 'stream', status: 'completed' }),
        ev('nov', '2026-11-01T00:10:00+09:00', { type: 'stream', status: 'upcoming' }),
        ev('oct', '2026-10-31T23:59:00+09:00', { type: 'stream', status: 'upcoming' }),
    ], ...OCT);
    assert.deepEqual(items.map(i => i.id), ['oct']);
});

test('items come out in start order', () => {
    const items = toShareItems([
        ev('b', '2026-10-09T20:00:00+09:00', { type: 'stream', status: 'completed' }),
        ev('a', '2026-10-02T20:00:00+09:00', { type: 'stream', status: 'completed' }),
    ], ...OCT);
    assert.deepEqual(items.map(i => i.id), ['a', 'b']);
});

test('upcoming and live streams are kept; thumbnails are upgraded to mqdefault; actual times are Dates', () => {
    const [i] = toShareItems([ev('s', '2026-10-05T20:00:00+09:00', {
        type: 'stream', status: 'live', actual_start_at: '2026-10-05T11:01:00+00:00' })], ...OCT);
    assert.equal(i.status, 'live');
    assert.equal(i.thumb, 'https://i.ytimg.com/vi/x/mqdefault.jpg');
    assert.equal(i.actualStart.toISOString(), '2026-10-05T11:01:00.000Z');
    assert.equal(i.actualEnd, null);
});

test('a finished stream carries its real end; tags, colour and channel name come through', () => {
    const e = ev('s', '2026-10-17T21:00:00+09:00', {
        type: 'stream', status: 'completed', actual_start_at: '2026-10-17T12:01:34+00:00',
        tags: [{ id: 1, name: '原神', kind: 'game' }],
    });
    e.end = new Date('2026-10-17T13:47:17Z');
    const [i] = toShareItems([e], ...OCT);
    assert.equal(i.actualEnd.toISOString(), '2026-10-17T13:47:17.000Z');
    assert.deepEqual(i.tags, [{ name: '原神', kind: 'game' }]);
    assert.equal(i.color, '#feb690');
    assert.equal(i.channelName, 'うる');
});

test('the genre mosaic leaves Twitch out (its pictures are stream screenshots); the month keeps it', () => {
    const items = toShareItems([
        ev('yt', '2026-10-05T20:00:00+09:00', { type: 'stream', status: 'completed', platform: 'youtube' }),
        ev('tw', '2026-10-06T20:00:00+09:00', { type: 'stream', status: 'completed', platform: 'twitch' }),
    ], ...OCT);
    assert.deepEqual(items.map(i => i.id), ['yt', 'tw']);
    assert.deepEqual(mosaicItems(items).map(i => i.id), ['yt']);
});
