import { test } from 'node:test';
import assert from 'node:assert/strict';
import { monthWeeks, eventLabel } from '../../resources/js/share-image/month.js';

const item = (id, start, extra = {}) => ({ id, start: new Date(start), kind: 'stream', status: 'completed', ...extra });

test('a month is laid out Sunday-first, only the weeks it spans, other days blank', () => {
    const weeks = monthWeeks([], new Date(2026, 9, 1));
    assert.equal(weeks.length, 5); // 1 Oct 2026 is a Thursday; the 31st a Saturday
    assert.deepEqual(weeks[0].map((c) => c.day), [null, null, null, null, 1, 2, 3]);
    assert.deepEqual(weeks[4].map((c) => c.day), [25, 26, 27, 28, 29, 30, 31]);
    assert.equal(monthWeeks([], new Date(2026, 1, 1)).length, 4); // Feb 2026: Sunday 1st to Saturday 28th
});

test('each item sits on the day it starts, in time order', () => {
    const weeks = monthWeeks([
        item('late', new Date(2026, 9, 2, 22, 0)),
        item('early', new Date(2026, 9, 2, 8, 0)),
        item('sat', new Date(2026, 9, 31, 23, 30)),
    ], new Date(2026, 9, 1));
    assert.deepEqual(weeks[0][5].items.map((i) => i.id), ['early', 'late']);
    assert.deepEqual(weeks[4][6].items.map((i) => i.id), ['sat']);
});

test('the label in front of the title follows the page', () => {
    assert.equal(eventLabel(item('a', new Date(2026, 9, 2, 9, 5))), '09:05');
    assert.equal(eventLabel(item('g', new Date(2026, 9, 2), { kind: 'guest', status: 'posted' })), '出演');
    assert.equal(eventLabel(item('s', new Date(2026, 9, 2), { kind: 'short' })), 'Short');
    assert.equal(eventLabel(item('u', new Date(2026, 9, 2), { kind: 'upload' })), '動画');
    assert.equal(eventLabel(item('l', new Date(2026, 9, 2), { status: 'live' })), 'LIVE');
});
