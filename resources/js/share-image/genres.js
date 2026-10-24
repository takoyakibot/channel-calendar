/**
 * Genres for the mosaic (pattern D): every item lands in exactly one block, and small
 * genres fold together so a busy month does not turn into a wall of one-tile blocks.
 */

/** Tags about how a stream was run rather than what it was: used only when nothing else fits. */
export const LOW_PRIORITY_CATEGORIES = ['メン限', 'コラボ', '参加型', '同時視聴'];

const OTHER = 'その他';
const SHORTS = 'ショート';
const FOLDED_GAMES = 'その他ゲーム';
const ALL_GAMES = 'ゲーム';

/** Shorts are cropped to 9:16 and three share one 16:9 cell. */
function cellsFor(name, count) {
    return name === SHORTS ? Math.ceil(count / 3) : count;
}

function genreOf(item) {
    if (item.kind === 'short') return { name: SHORTS, isGame: false };
    const tags = item.tags || [];
    const g = tags.find((t) => t.kind === 'game');
    if (g) return { name: g.name, isGame: true };
    const c = tags.find((t) => t.kind === 'category' && !LOW_PRIORITY_CATEGORIES.includes(t.name))
        || tags.find((t) => t.kind === 'category');
    return c ? { name: c.name, isGame: false } : { name: OTHER, isGame: false };
}

/**
 * @param {import('./items.js').ShareItem[]} items
 * @param {{gameMax: number, categoryMax: number}} opts fold games / categories with this many items or fewer
 * @returns {{blocks: {name: string, items: object[], cells: number, isOther: boolean}[], foldedGames: string[], foldedCategories: string[]}}
 */
export function groupByGenre(items, { gameMax, categoryMax }) {
    const groups = new Map();
    const games = new Set();
    for (const item of items) {
        const { name, isGame } = genreOf(item);
        if (!groups.has(name)) groups.set(name, []);
        groups.get(name).push(item);
        if (isGame) games.add(name);
    }

    // Games first: several small titles become one block; a lone small title keeps its name.
    let foldedGames = [...games].filter((name) => groups.get(name).length <= gameMax);
    if (foldedGames.length < 2) foldedGames = [];
    if (foldedGames.length) {
        const kept = games.size - foldedGames.length;
        const merged = foldedGames.flatMap((name) => groups.get(name));
        foldedGames.forEach((name) => groups.delete(name));
        groups.set(kept ? FOLDED_GAMES : ALL_GAMES, merged);
    }

    // Then categories, into その他 — worth it for one category only when その他 already exists.
    const fixed = new Set([OTHER, SHORTS, FOLDED_GAMES, ALL_GAMES]);
    let foldedCategories = [...groups.keys()]
        .filter((name) => !games.has(name) && !fixed.has(name) && groups.get(name).length <= categoryMax);
    if (foldedCategories.length + (groups.has(OTHER) ? 1 : 0) < 2) foldedCategories = [];
    if (foldedCategories.length) {
        const merged = (groups.get(OTHER) || []).concat(foldedCategories.flatMap((name) => groups.get(name)));
        foldedCategories.forEach((name) => groups.delete(name));
        groups.set(OTHER, merged);
    }

    const blocks = [...groups].map(([name, list]) => ({
        name, items: list, cells: cellsFor(name, list.length), isOther: name === OTHER,
    }));
    blocks.sort((a, b) => (a.isOther - b.isOther) || (b.cells - a.cells) || a.name.localeCompare(b.name, 'ja'));

    return { blocks, foldedGames, foldedCategories };
}
