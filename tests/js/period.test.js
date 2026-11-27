import { test } from 'node:test';
import assert from 'node:assert/strict';
import { presetRange, presetFor, rangeFromInputs, periodLabel, periodFilename, toInputValue } from '../../resources/js/share-image/period.js';

const today = new Date(2026, 10, 27); // 2026-11-27
const d = (y, m, dd) => new Date(y, m - 1, dd);

test('presets are whole months / the year, relative to today', () => {
    assert.deepEqual(presetRange('this-month', today), { start: d(2026, 11, 1), end: d(2026, 12, 1) });
    assert.deepEqual(presetRange('last-month', today), { start: d(2026, 10, 1), end: d(2026, 11, 1) });
    assert.deepEqual(presetRange('last-3-months', today), { start: d(2026, 9, 1), end: d(2026, 12, 1) });
    assert.deepEqual(presetRange('this-year', today), { start: d(2026, 1, 1), end: d(2027, 1, 1) });
    assert.deepEqual(presetRange('last-month', new Date(2026, 0, 10)), { start: d(2025, 12, 1), end: d(2026, 1, 1) });
    assert.equal(presetRange('custom', today), null);
});

test('a range is recognised as its preset, anything else is custom', () => {
    assert.equal(presetFor({ start: d(2026, 10, 1), end: d(2026, 11, 1) }, today), 'last-month');
    assert.equal(presetFor({ start: d(2026, 8, 1), end: d(2026, 9, 1) }, today), 'custom');
});

test('date inputs include both days and are checked', () => {
    assert.deepEqual(rangeFromInputs('2026-09-15', '2026-10-03'), { start: d(2026, 9, 15), end: d(2026, 10, 4) });
    assert.ok(rangeFromInputs('', '2026-10-03').error);
    assert.ok(rangeFromInputs('2026-10-03', '2026-09-15').error);
    assert.ok(rangeFromInputs('2025-01-01', '2026-01-02').error);   // over a year
    assert.ok(!rangeFromInputs('2025-11-28', '2026-11-27').error);
    assert.equal(toInputValue(d(2026, 3, 5)), '2026-03-05');
});

test('labels and file names', () => {
    assert.equal(periodLabel({ start: d(2026, 11, 1), end: d(2026, 12, 1) }), '2026年11月');
    assert.equal(periodLabel({ start: d(2026, 9, 1), end: d(2026, 12, 1) }), '2026年9月〜11月');
    assert.equal(periodLabel({ start: d(2025, 12, 1), end: d(2026, 3, 1) }), '2025年12月〜2026年2月');
    assert.equal(periodLabel({ start: d(2026, 1, 1), end: d(2027, 1, 1) }), '2026年');
    assert.equal(periodLabel({ start: d(2026, 9, 15), end: d(2026, 10, 4) }), '2026年9月15日〜10月3日');
    assert.equal(periodLabel({ start: d(2025, 12, 20), end: d(2026, 1, 11) }), '2025年12月20日〜2026年1月10日');
    assert.equal(periodLabel({ start: d(2026, 9, 15), end: d(2026, 9, 16) }), '2026年9月15日');
    assert.equal(periodFilename({ start: d(2026, 11, 1), end: d(2026, 12, 1) }), 'channel-calendar-2026-11.png');
    assert.equal(periodFilename({ start: d(2026, 9, 1), end: d(2026, 12, 1) }), 'channel-calendar-2026-09-01_2026-11-30.png');
});
