import { test } from 'node:test';
import assert from 'node:assert/strict';
import { monthStats, formatDuration, STAT_TILES } from '../../resources/js/share-image/stats.js';

const stream = (status, start, end) => ({
    kind: 'stream', status, actualStart: start ? new Date(start) : null, actualEnd: end ? new Date(end) : null,
});

test('only live streams count; time uses actual start to end of finished ones', () => {
    const s = monthStats([
        stream('completed', '2026-10-17T12:01:34Z', '2026-10-17T13:47:17Z'),
        stream('completed', '2026-10-10T10:00:00Z', '2026-10-10T16:12:00Z'),
        stream('upcoming', null, null),
        stream('live', '2026-10-24T10:00:00Z', null),
        { ...stream('completed', '2026-10-01T10:00:00Z', '2026-10-01T11:00:00Z'), kind: 'upload' },
    ]);
    assert.deepEqual(s, { streams: 4, totalSeconds: 6343 + 22320, longestSeconds: 22320 });
});

test('a finished stream without a recorded start counts but adds no time', () => {
    assert.deepEqual(monthStats([stream('completed', null, '2026-10-17T13:47:17Z')]), { streams: 1, totalSeconds: 0, longestSeconds: 0 });
});

test('formatDuration', () => {
    assert.equal(formatDuration(22320), '6時間12分');
    assert.equal(formatDuration(3120), '52分');
    assert.equal(formatDuration(0), '0分');
});

test('STAT_TILES order is 配信数, 配信時間, 最長配信 and formats each value', () => {
    assert.deepEqual(STAT_TILES.map((t) => t.label), ['配信数', '配信時間', '最長配信']);
    const s = { streams: 21, totalSeconds: 137520, longestSeconds: 22320 };
    assert.deepEqual(STAT_TILES.map((t) => t.format(s[t.key])), ['21', '38時間12分', '6時間12分']);
});
