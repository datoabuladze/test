import Alpine from '@alpinejs/csp';
import collapse from '@alpinejs/collapse';
import { csrf, postJson, getJson } from './lib/http';
import { recent } from './lib/recent';

window.Alpine = Alpine;
Alpine.plugin(collapse);

/* ------------------------------------------------------------------ theme */
Alpine.data('themeToggle', () => ({
    mode: document.documentElement.dataset.theme || 'dark',
    toggle() {
        this.mode = this.mode === 'dark' ? 'light' : 'dark';
        document.documentElement.dataset.theme = this.mode;
        try { localStorage.setItem('theme', this.mode); } catch (e) { /* storage unavailable */ }
    },
    get isDark() { return this.mode === 'dark'; },
}));

/* ------------------------------------------------------------------ generic toggles */
Alpine.data('disclosure', (initial = false) => ({
    open: initial,
    toggle() { this.open = !this.open; },
    close() { this.open = false; },
    show() { this.open = true; },
}));

/* ------------------------------------------------------------------ search with live suggestions */
Alpine.data('searchBox', () => ({
    q: '',
    open: false,
    loading: false,
    games: [],
    categories: [],
    active: -1,
    timer: null,
    init() {
        const url = new URL(window.location.href);
        if (url.pathname.endsWith('/search')) this.q = url.searchParams.get('q') || '';
    },
    get hasResults() { return this.games.length > 0 || this.categories.length > 0; },
    get showEmpty() { return this.open && !this.loading && this.q.length >= 2 && !this.hasResults; },
    input() {
        clearTimeout(this.timer);
        if (this.q.trim().length < 2) { this.games = []; this.categories = []; this.open = false; return; }
        this.timer = setTimeout(() => this.fetch(), 160);
    },
    async fetch() {
        this.loading = true;
        this.open = true;
        try {
            const data = await getJson(this.$root.dataset.suggestUrl + '?q=' + encodeURIComponent(this.q.trim()));
            this.games = data.games || [];
            this.categories = data.categories || [];
            this.active = -1;
        } catch (e) {
            this.games = []; this.categories = [];
        } finally {
            this.loading = false;
        }
    },
    move(delta) {
        const total = this.games.length + this.categories.length;
        if (!total) return;
        this.open = true;
        this.active = (this.active + delta + total) % total;
    },
    down() { this.move(1); },
    up() { this.move(-1); },
    isActive(i) { return this.active === i; },
    isActiveCat(i) { return this.active === this.games.length + i; },
    submit(event) {
        const all = [...this.games, ...this.categories];
        if (this.active >= 0 && all[this.active]) {
            event.preventDefault();
            window.location.href = all[this.active].url;
        }
    },
    close() { this.open = false; },
    focusOpen() { if (this.hasResults) this.open = true; },
}));

/* ------------------------------------------------------------------ horizontal rails */
Alpine.data('rail', () => ({
    canLeft: false,
    canRight: true,
    init() {
        this.update();
        this.$refs.track.addEventListener('scroll', () => this.update(), { passive: true });
        window.addEventListener('resize', () => this.update(), { passive: true });
    },
    update() {
        const t = this.$refs.track;
        this.canLeft = t.scrollLeft > 4;
        this.canRight = t.scrollLeft + t.clientWidth < t.scrollWidth - 4;
    },
    left() { this.$refs.track.scrollBy({ left: -this.$refs.track.clientWidth * 0.85, behavior: 'smooth' }); },
    right() { this.$refs.track.scrollBy({ left: this.$refs.track.clientWidth * 0.85, behavior: 'smooth' }); },
}));

/* ------------------------------------------------------------------ hero carousel */
Alpine.data('carousel', (count = 1) => ({
    index: 0,
    count,
    timer: null,
    init() { this.start(); },
    start() {
        if (this.count < 2 || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        this.stop();
        this.timer = setInterval(() => this.next(), 6500);
    },
    stop() { clearInterval(this.timer); },
    next() { this.index = (this.index + 1) % this.count; },
    prev() { this.index = (this.index - 1 + this.count) % this.count; },
    go(i) { this.index = i; this.start(); },
    isCurrent(i) { return this.index === i; },
}));

/* ------------------------------------------------------------------ client-rendered rails (recently played) */
Alpine.data('recentRail', () => ({
    loaded: false,
    empty: false,
    async init() {
        const ids = recent.ids();
        if (!ids.length) { this.empty = true; this.loaded = true; return; }
        try {
            const res = await fetch(this.$root.dataset.url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'text/html' },
                body: JSON.stringify({ ids, variant: this.$root.dataset.variant || 'rail' }),
            });
            const html = await res.text();
            if (!html.trim()) { this.empty = true; } else { this.$refs.target.innerHTML = html; }
        } catch (e) {
            this.empty = true;
        }
        this.loaded = true;
    },
    clear() { recent.clear(); this.empty = true; },
}));

/* ------------------------------------------------------------------ favorite toggle */
Alpine.data('favoriteButton', () => ({
    on: false,
    busy: false,
    init() { this.on = this.$root.dataset.on === '1'; },
    async toggle() {
        if (this.$root.dataset.login) { window.location.href = this.$root.dataset.login; return; }
        this.busy = true;
        try {
            const data = await postJson(this.$root.dataset.url, {});
            this.on = data.favorited;
            window.dispatchEvent(new CustomEvent('toast', { detail: data.message }));
        } finally { this.busy = false; }
    },
}));

/* ------------------------------------------------------------------ star rating */
Alpine.data('ratingWidget', () => ({
    value: 0,
    hover: 0,
    avg: 0,
    count: 0,
    init() {
        this.value = Number(this.$root.dataset.value || 0);
        this.avg = Number(this.$root.dataset.avg || 0);
        this.count = Number(this.$root.dataset.count || 0);
    },
    shown(i) { return (this.hover || this.value) >= i; },
    enter(i) { this.hover = i; },
    leave() { this.hover = 0; },
    async rate(i) {
        if (this.$root.dataset.login) { window.location.href = this.$root.dataset.login; return; }
        const data = await postJson(this.$root.dataset.url, { stars: i });
        this.value = i; this.avg = data.rating_avg; this.count = data.rating_count;
        window.dispatchEvent(new CustomEvent('toast', { detail: data.message }));
    },
    get avgLabel() { return this.avg ? this.avg.toFixed(1) : '–'; },
}));

/* ------------------------------------------------------------------ toast */
Alpine.data('toaster', () => ({
    message: '',
    visible: false,
    timer: null,
    init() {
        window.addEventListener('toast', (e) => this.show(e.detail));
        const flash = this.$root.dataset.flash;
        if (flash) this.show(flash);
    },
    show(msg) {
        if (!msg) return;
        this.message = msg; this.visible = true;
        clearTimeout(this.timer);
        this.timer = setTimeout(() => { this.visible = false; }, 3200);
    },
    hide() { this.visible = false; },
}));

/* ------------------------------------------------------------------ share */
Alpine.data('shareButton', () => ({
    async share() {
        const data = { title: this.$root.dataset.title, url: this.$root.dataset.url };
        if (navigator.share) {
            try { await navigator.share(data); return; } catch (e) { /* cancelled */ }
        }
        try {
            await navigator.clipboard.writeText(data.url);
            window.dispatchEvent(new CustomEvent('toast', { detail: this.$root.dataset.copied }));
        } catch (e) {
            window.prompt('Copy link', data.url);
        }
    },
}));

/* ------------------------------------------------------------------ report dialog */
Alpine.data('reportDialog', () => ({
    open: false,
    sent: false,
    busy: false,
    reason: 'not_loading',
    message: '',
    show() { this.open = true; this.sent = false; },
    close() { this.open = false; },
    async submit() {
        this.busy = true;
        try {
            await postJson(this.$root.dataset.url, { reason: this.reason, message: this.message });
            this.sent = true;
        } catch (e) {
            window.dispatchEvent(new CustomEvent('toast', { detail: e.message }));
        } finally { this.busy = false; }
    },
}));

/* ------------------------------------------------------------------ confirm-before-submit forms */
Alpine.data('confirmForm', () => ({
    confirmSubmit(event) {
        if (!window.confirm(this.$root.dataset.confirm || 'Are you sure?')) event.preventDefault();
    },
}));

Alpine.start();
