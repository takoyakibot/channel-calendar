/**
 * What goes into the month share image: the month's own streams, videos, shorts and guest
 * appearances. Clips are someone else's video and manual entries have no picture, so they stay out.
 *
 * @typedef {{
 *   id: string, title: string, start: Date,
 *   kind: 'stream'|'upload'|'short'|'guest',
 *   status: 'upcoming'|'live'|'completed'|'posted',
 *   thumb: string|null, color: string, channelName: string,
 *   tags: {name: string, kind: 'game'|'category'}[],
 *   actualStart: Date|null, actualEnd: Date|null,
 * }} ShareItem
 */

const KINDS = ['stream', 'upload', 'short', 'guest'];

/** Same rule as thumbUrl() on the calendar page: YouTube's 120px default → the 16:9 mqdefault. */
function thumbUrl(url) {
    if (!url) return null;
    return /i\.ytimg\.com\//.test(url) ? url.replace(/\/default(_live)?\.jpg/, '/mqdefault$1.jpg') : url;
}

/**
 * @param {object[]} events FullCalendar EventApi objects (or plain objects of the same shape)
 * @param {Date} monthStart first instant of the month
 * @param {Date} monthEnd first instant of the next month
 * @returns {ShareItem[]}
 */
export function toShareItems(events, monthStart, monthEnd) {
    return events
        .filter((e) => {
            const p = e.extendedProps || {};
            const start = new Date(e.start);
            return p.status !== 'manual' && KINDS.includes(p.type) && start >= monthStart && start < monthEnd;
        })
        .map((e) => {
            const p = e.extendedProps;
            const finished = p.type === 'stream' && p.status === 'completed';
            return {
                id: String(e.id),
                title: e.title,
                start: new Date(e.start),
                kind: p.type,
                status: p.status,
                thumb: thumbUrl(p.thumbnail_url),
                color: e.backgroundColor || e.borderColor || p.color || '#9ca3af',
                channelName: p.channel_name || '',
                tags: (p.tags || []).map((t) => ({ name: t.name, kind: t.kind })),
                actualStart: p.actual_start_at ? new Date(p.actual_start_at) : null,
                actualEnd: finished && e.end ? new Date(e.end) : null,
            };
        })
        .sort((a, b) => a.start - b.start);
}
