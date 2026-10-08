/**
 * Admin panel components. Registered on alpine:init so they exist before
 * app.js calls Alpine.start() (admin.js is included first in the admin layout).
 */
import { postJson } from './lib/http';

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;

    /*
     * Drag-and-drop ordering for any list. Markup contract:
     *   <ul x-data="sortableList" data-url="POST endpoint"> <li data-id="1" draggable="true"> ... </li> </ul>
     * Items may also contain buttons calling moveUp / moveDown for keyboard and touch users.
     * The new order is POSTed as {ids: [...]}.
     */
    Alpine.data('sortableList', () => ({
        dragging: null,
        saving: false,
        saved: false,
        init() {
            const root = this.$root;
            root.addEventListener('dragstart', (e) => {
                const item = e.target.closest('[data-id]');
                if (!item || item.parentElement !== root) return;
                this.dragging = item;
                item.classList.add('opacity-40');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', item.dataset.id);
            });
            root.addEventListener('dragover', (e) => {
                if (!this.dragging) return;
                e.preventDefault();
                const over = e.target.closest('[data-id]');
                if (!over || over === this.dragging || over.parentElement !== root) return;
                const rect = over.getBoundingClientRect();
                const after = e.clientY > rect.top + rect.height / 2;
                root.insertBefore(this.dragging, after ? over.nextSibling : over);
            });
            root.addEventListener('dragend', () => {
                if (!this.dragging) return;
                this.dragging.classList.remove('opacity-40');
                this.dragging = null;
                this.persist();
            });
            root.addEventListener('click', (e) => {
                const btn = e.target.closest('[data-move]');
                if (!btn) return;
                const item = btn.closest('[data-id]');
                if (!item || item.parentElement !== root) return; // belongs to a nested list
                if (btn.dataset.move === 'up' && item.previousElementSibling) {
                    root.insertBefore(item, item.previousElementSibling);
                } else if (btn.dataset.move === 'down' && item.nextElementSibling) {
                    root.insertBefore(item.nextElementSibling, item);
                } else {
                    return;
                }
                btn.focus();
                this.persist();
            });
        },
        ids() {
            return [...this.$root.children].filter((el) => el.dataset.id).map((el) => Number(el.dataset.id));
        },
        async persist() {
            this.saving = true;
            try {
                await postJson(this.$root.dataset.url, { ids: this.ids() });
                window.dispatchEvent(new CustomEvent('toast', { detail: 'Order saved' }));
            } catch (e) {
                window.dispatchEvent(new CustomEvent('toast', { detail: e.message }));
            } finally {
                this.saving = false;
            }
        },
    }));

    /* Bulk selection for tables: header checkbox toggles every row checkbox named ids[]. */
    Alpine.data('bulkTable', () => ({
        count: 0,
        init() { this.recount(); this.$root.addEventListener('change', () => this.recount()); },
        boxes() { return [...this.$root.querySelectorAll('input[name="ids[]"]')]; },
        recount() { this.count = this.boxes().filter((b) => b.checked).length; },
        toggleAll(event) { this.boxes().forEach((b) => { b.checked = event.target.checked; }); this.recount(); },
        get hasSelection() { return this.count > 0; },
        get selectionLabel() { return this.count + ' selected'; },
    }));

    /* Fills a slug input from a title input until the slug is edited by hand. */
    Alpine.data('slugger', () => ({
        touched: false,
        init() {
            const slug = this.$root.querySelector('[data-slug]');
            const source = this.$root.querySelector('[data-slug-source]') || this.$root.querySelector('[name="title[en]"], [name="name[en]"]');
            if (!slug || !source) return;
            this.touched = slug.value !== '';
            slug.addEventListener('input', () => { this.touched = true; });
            source.addEventListener('input', () => {
                if (this.touched) return;
                slug.value = source.value.toLowerCase().normalize('NFKD').replace(/[̀-ͯ]/g, '')
                    .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 100);
            });
        },
    }));

    /* Live preview of theme tokens while editing the design form. */
    Alpine.data('themePreview', () => ({
        init() {
            this.$root.addEventListener('input', (e) => {
                const v = e.target.dataset.cssVar;
                if (v) document.documentElement.style.setProperty(v, e.target.value);
            });
        },
    }));

    /* Shows fields relevant to the selected engine on the game form. */
    Alpine.data('engineFields', () => ({
        engine: '',
        init() {
            const select = this.$root.querySelector('[name="engine"]');
            this.engine = select ? select.value : '';
            if (select) select.addEventListener('change', () => { this.engine = select.value; });
        },
        get isEmbed() { return this.engine === 'iframe'; },
        get isRuffle() { return this.engine === 'ruffle'; },
        get isUnity() { return this.engine === 'unity'; },
        get isLocal() { return this.engine !== 'iframe'; },
    }));
});
