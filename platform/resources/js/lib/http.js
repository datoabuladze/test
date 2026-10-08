export function csrf() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

async function handle(res) {
    let data = {};
    try { data = await res.json(); } catch (e) { /* non-JSON */ }
    if (!res.ok) {
        const msg = data.message || (data.errors && Object.values(data.errors)[0]?.[0]) || `Request failed (${res.status})`;
        throw new Error(msg);
    }
    return data;
}

export async function postJson(url, body) {
    const res = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
        body: JSON.stringify(body || {}),
    });
    return handle(res);
}

export async function getJson(url) {
    const res = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    return handle(res);
}
