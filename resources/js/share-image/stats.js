/**
 * The numbers on pattern B. Only live streams count; lengths come from the real start/end,
 * so upcoming and live ones add to the count but not to the time.
 */

/** @param {import('./items.js').ShareItem[]} items */
export function monthStats(items) {
    let streams = 0;
    let totalSeconds = 0;
    let longestSeconds = 0;
    for (const item of items) {
        if (item.kind !== 'stream') continue;
        streams++;
        if (item.status !== 'completed' || !item.actualStart || !item.actualEnd) continue;
        const seconds = Math.max(0, Math.round((item.actualEnd - item.actualStart) / 1000));
        totalSeconds += seconds;
        longestSeconds = Math.max(longestSeconds, seconds);
    }
    return { streams, totalSeconds, longestSeconds };
}

export function formatDuration(seconds) {
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    return h ? `${h}時間${m}分` : `${m}分`;
}

/** What B shows, in order. Swap the third (e.g. for an average) here and nowhere else. */
export const STAT_TILES = [
    { key: 'streams', label: '配信数', format: (v) => String(v) },
    { key: 'totalSeconds', label: '配信時間', format: formatDuration },
    { key: 'longestSeconds', label: '最長配信', format: formatDuration },
];
