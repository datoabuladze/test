// Recently played games, kept in the visitor's own browser (no server tracking for guests).
const KEY = 'recently_played';
const MAX = 24;

function read() {
    try {
        const v = JSON.parse(localStorage.getItem(KEY) || '[]');
        return Array.isArray(v) ? v.filter((n) => Number.isInteger(n)) : [];
    } catch (e) {
        return [];
    }
}

export const recent = {
    ids: read,
    add(id) {
        id = Number(id);
        if (!Number.isInteger(id)) return;
        const list = [id, ...read().filter((x) => x !== id)].slice(0, MAX);
        try { localStorage.setItem(KEY, JSON.stringify(list)); } catch (e) { /* ignore */ }
    },
    clear() {
        try { localStorage.removeItem(KEY); } catch (e) { /* ignore */ }
    },
};
