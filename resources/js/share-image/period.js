/**
 * The period of the genre mosaic (pattern D): a preset or two dates picked by hand.
 * Dates are local calendar days; `end` is exclusive (the first instant after the period).
 */

export const PRESETS = [
    { key: 'this-month', label: '今月' },
    { key: 'last-month', label: '先月' },
    { key: 'last-3-months', label: '過去3か月' },
    { key: 'this-year', label: '今年' },
    { key: 'custom', label: '期間を指定' },
];

/** Longest period the picture is made for: beyond this the thumbnails shrink to stamps. */
export const MAX_DAYS = 366;

const day = (y, m, d) => new Date(y, m, d);

/** @returns {{start: Date, end: Date}|null} null for 'custom' */
export function presetRange(key, today = new Date()) {
    const y = today.getFullYear();
    const m = today.getMonth();
    switch (key) {
        case 'this-month': return { start: day(y, m, 1), end: day(y, m + 1, 1) };
        case 'last-month': return { start: day(y, m - 1, 1), end: day(y, m, 1) };
        case 'last-3-months': return { start: day(y, m - 2, 1), end: day(y, m + 1, 1) };
        case 'this-year': return { start: day(y, 0, 1), end: day(y + 1, 0, 1) };
        default: return null;
    }
}

/** The preset a range is exactly, or 'custom'. */
export function presetFor(range, today = new Date()) {
    const hit = PRESETS.find((p) => {
        const r = presetRange(p.key, today);
        return r && +r.start === +range.start && +r.end === +range.end;
    });
    return hit ? hit.key : 'custom';
}

/** "2026-11-05" for <input type="date">. */
export function toInputValue(date) {
    const p = (n) => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${p(date.getMonth() + 1)}-${p(date.getDate())}`;
}

/**
 * From the two date inputs (both days included).
 * @returns {{start: Date, end: Date}|{error: string}}
 */
export function rangeFromInputs(from, to) {
    const parse = (s) => {
        const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s || '');
        return m ? day(Number(m[1]), Number(m[2]) - 1, Number(m[3])) : null;
    };
    const start = parse(from);
    const last = parse(to);
    if (!start || !last) return { error: '開始日と終了日を入れてください。' };
    if (last < start) return { error: '終了日は開始日より後にしてください。' };
    const end = day(last.getFullYear(), last.getMonth(), last.getDate() + 1);
    if (Math.round((end - start) / 86400000) > MAX_DAYS) return { error: '期間は1年以内にしてください。' };
    return { start, end };
}

const isMonthStart = (d) => d.getDate() === 1;

/** "2026年11月", "2026年9月〜11月", "2025年12月〜2026年2月", "2026年", "2026年9月15日〜10月3日". */
export function periodLabel({ start, end }) {
    const last = day(end.getFullYear(), end.getMonth(), end.getDate() - 1);
    const sy = start.getFullYear();
    const ly = last.getFullYear();
    if (isMonthStart(start) && isMonthStart(end)) {
        const sm = start.getMonth() + 1;
        const lm = last.getMonth() + 1;
        if (sy === ly && sm === 1 && lm === 12) return `${sy}年`;
        if (sy === ly && sm === lm) return `${sy}年${sm}月`;
        return sy === ly ? `${sy}年${sm}月〜${lm}月` : `${sy}年${sm}月〜${ly}年${lm}月`;
    }
    const s = `${sy}年${start.getMonth() + 1}月${start.getDate()}日`;
    if (+start === +last) return s;
    return `${s}〜${sy === ly ? '' : `${ly}年`}${last.getMonth() + 1}月${last.getDate()}日`;
}

/** channel-calendar-2026-11.png for one month, channel-calendar-2026-09-01_2026-11-30.png otherwise. */
export function periodFilename({ start, end }) {
    const last = day(end.getFullYear(), end.getMonth(), end.getDate() - 1);
    const oneMonth = isMonthStart(start) && isMonthStart(end)
        && start.getFullYear() === last.getFullYear() && start.getMonth() === last.getMonth();
    if (oneMonth) return `channel-calendar-${toInputValue(start).slice(0, 7)}.png`;
    return `channel-calendar-${toInputValue(start)}_${toInputValue(last)}.png`;
}
