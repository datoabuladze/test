/** Private room client: polls server-authoritative state and sends move intents. */
import { postJson, getJson } from './lib/http';

const POLL_MS = 1200;

document.addEventListener('alpine:init', () => {
    window.Alpine.data('roomCreator', () => ({
        busy: false,
        error: '',
        async create(game) {
            this.busy = true; this.error = '';
            try {
                const res = await postJson(this.$root.dataset.url, { game });
                window.location.href = res.url;
            } catch (e) { this.error = e.message; this.busy = false; }
        },
    }));

    window.Alpine.data('room', () => ({
        data: null,
        error: '',
        timer: null,
        cells9: [0, 1, 2, 3, 4, 5, 6, 7, 8],
        cells42: Array.from({ length: 42 }, (_, i) => i),
        game: '',

        init() {
            this.game = this.$root.dataset.game;
            this.poll();
        },
        async poll() {
            try {
                this.data = await getJson(this.$root.dataset.stateUrl);
                this.error = '';
            } catch (e) {
                this.error = e.message;
            }
            clearTimeout(this.timer);
            if (!this.data || this.data.status !== 'expired') this.timer = setTimeout(() => this.poll(), POLL_MS);
        },
        async send(url, body) {
            try {
                this.data = await postJson(url, body || {});
                this.error = '';
            } catch (e) { this.error = e.message; }
        },
        join() { this.send(this.$root.dataset.joinUrl); },
        ready() { this.send(this.$root.dataset.readyUrl); },
        rematch() { this.send(this.$root.dataset.rematchUrl); },
        playCell(i) { if (this.myTurn) this.send(this.$root.dataset.moveUrl, { cell: i }); },
        playCol(i) { if (this.myTurn) this.send(this.$root.dataset.moveUrl, { col: this.colOf(i) }); },
        colOf(i) { return i % 7; },
        async copyLink() {
            try { await navigator.clipboard.writeText(this.$root.dataset.shareUrl); } catch (e) { window.prompt(this.$root.dataset.copyPrompt, this.$root.dataset.shareUrl); return; }
            window.dispatchEvent(new CustomEvent('toast', { detail: this.$root.dataset.copied }));
        },

        get g() { return this.data && this.data.state && this.data.state.game ? this.data.state.game : null; },
        get you() { return this.data ? this.data.you : null; },
        get hasBoard() { return !!this.g; },
        get isFinished() { return !!this.data && this.data.status === 'finished'; },
        get myTurn() { return !!this.g && this.data.status === 'playing' && this.g.turn === this.you; },
        get canJoin() { return !!this.data && this.you === null && !this.data.guest.joined; },
        get showReady() {
            if (!this.data || this.you === null || this.data.status !== 'waiting' || !this.data.guest.joined) return false;
            return this.you === 0 ? !this.data.host.ready : !this.data.guest.ready;
        },
        get waitingForGuest() { return !!this.data && this.you === 0 && !this.data.guest.joined; },
        get score0() { return this.data && this.data.state.score ? this.data.state.score[0] : 0; },
        get score1() { return this.data && this.data.state.score ? this.data.state.score[1] : 0; },
        get hostStatus() { return this.presence(this.data && this.data.host, true); },
        get guestStatus() { return this.data && this.data.guest.joined ? this.presence(this.data.guest, true) : '—'; },
        presence(p) {
            if (!p) return '';
            const d = this.$root.dataset;
            return (p.present ? d.tOnline : d.tAway) + (p.ready ? ' · ' + d.tReady : '');
        },
        cellLabel(i) { return this.$root.dataset.tCell.replace(':n', i + 1); },
        colLabel(i) { return this.$root.dataset.tColumn.replace(':n', this.colOf(i) + 1); },
        isTurn(seat) { return !!this.g && this.data.status === 'playing' && this.g.turn === seat; },
        get message() {
            const d = this.$root.dataset;
            if (!this.data) return '';
            if (this.data.status === 'expired') return d.tExpired;
            if (!this.g) return this.you === null && this.data.guest.joined ? d.tSpectating : '';
            if (this.g.draw) return d.tDraw;
            if (this.g.winner !== null) {
                if (this.you === null) return d.tSpectating;
                return this.g.winner === this.you ? d.tYouWon : d.tYouLost;
            }
            if (this.you === null) return d.tSpectating;
            return this.myTurn ? d.tYourTurn : d.tTheirTurn;
        },
        inLine(r, c) {
            const line = this.g && this.g.line;
            if (!line) return false;
            if (this.game === 'tictactoe') return line.includes(r);
            return line.some((p) => p[0] === r && p[1] === c);
        },
        cellMark(i) {
            const v = this.g ? this.g.board[i] : null;
            return v === 0 ? '×' : v === 1 ? '○' : '';
        },
        cellClass(i) {
            const v = this.g ? this.g.board[i] : null;
            return {
                'text-brand-2': v === 0,
                'text-brand-3': v === 1,
                'ring-2 ring-ok': this.inLine(i),
                'cursor-pointer': this.myTurn && v === null,
            };
        },
        discClass(i) {
            const r = Math.floor(i / 7); const c = i % 7;
            const v = this.g ? this.g.board[r][c] : null;
            return {
                'bg-bg': v === null,
                'bg-brand-2': v === 0,
                'bg-brand-3': v === 1,
                'ring-4 ring-white': this.inLine(r, c),
                'cursor-pointer hover:bg-card-2': this.myTurn && v === null,
            };
        },
    }));
});
