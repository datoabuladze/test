/**
 * Universal game player. Loaded only on game pages.
 *
 * The game is never loaded before the visitor presses Play. Every engine runs
 * inside a sandboxed iframe WITHOUT allow-same-origin, so game code gets an
 * opaque origin and cannot touch this page, its cookies or storage.
 *
 * Frame <-> page protocol (window.postMessage, validated by event.source):
 *   frame -> page: {type: 'nebulo:ready'} | {type: 'nebulo:score', score} |
 *                  {type: 'nebulo:gameover', score, durationMs, evidence} | {type: 'nebulo:error', message}
 *   page -> frame: {type: 'nebulo:mute', muted} | {type: 'nebulo:pause'} | {type: 'nebulo:resume'} |
 *                  {type: 'nebulo:session', ...} (score session for original games)
 */
import { postJson } from './lib/http';
import { recent } from './lib/recent';

const LOAD_TIMEOUT_MS = 30000;
// Engines whose frame documents we control and that announce readiness. Uploaded
// HTML5/Phaser packages are third-party code that does not speak the protocol, so
// they count as ready on the frame's load event, like embeds.
const MANAGED = ['original', 'unity', 'ruffle'];

function isTouchOnly() {
    return window.matchMedia('(hover: none) and (pointer: coarse)').matches;
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('gamePlayer', () => ({
        state: 'idle', // idle | loading | playing | error
        muted: false,
        isFullscreen: false,
        pseudoFullscreen: false,
        rotateHint: false,
        rotateDismissed: false,
        keyboardWarning: false,
        errorMessage: '',
        lastScore: null,
        iframe: null,
        playId: null,
        timer: null,
        scoreToken: null,
        scoreSeed: null,
        startedAt: 0,

        init() {
            const d = this.$root.dataset;
            this.keyboardWarning = d.keyboardOnly === '1' && isTouchOnly();
            try { this.muted = localStorage.getItem('player_muted') === '1'; } catch (e) { /* ignore */ }
            if (d.playUrl) recent.add(Number(d.gameId)); // admin previews have no play URL

            window.addEventListener('message', (e) => this.onMessage(e));
            document.addEventListener('fullscreenchange', () => {
                this.isFullscreen = document.fullscreenElement === this.$root;
            });
            document.addEventListener('visibilitychange', () => {
                this.send({ type: document.hidden ? 'nebulo:pause' : 'nebulo:resume' });
            });
            window.addEventListener('resize', () => this.checkOrientation(), { passive: true });
            window.addEventListener('beforeunload', () => this.reportStatus('ended'));
        },

        get scoreLabel() { return this.lastScore === null ? '' : `★ ${this.lastScore}`; },

        async play() {
            this.state = 'loading';
            this.mountFrame();
            this.checkOrientation();
            if (!this.$root.dataset.playUrl) return;
            try {
                const res = await postJson(this.$root.dataset.playUrl, {});
                this.playId = res.play_id;
                this.statusUrl = res.status_url;
            } catch (e) { /* analytics failure must never block play */ }
            this.requestScoreSession();
        },

        mountFrame() {
            const d = this.$root.dataset;
            const host = this.$refs.frameHost;
            host.innerHTML = '';
            const iframe = document.createElement('iframe');
            iframe.title = d.title;
            iframe.className = 'absolute inset-0 h-full w-full border-0 bg-black';
            iframe.setAttribute('sandbox', d.sandbox);
            iframe.setAttribute('allow', d.allow);
            iframe.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
            iframe.setAttribute('loading', 'eager');
            iframe.setAttribute('data-testid', 'game-frame');
            iframe.addEventListener('load', () => this.onFrameLoad());
            iframe.src = d.frameUrl;
            host.appendChild(iframe);
            this.iframe = iframe;
            this.startedAt = Date.now();

            clearTimeout(this.timer);
            this.timer = setTimeout(() => {
                if (this.state === 'loading') this.fail(this.$root.dataset.engine === 'ruffle'
                    ? 'The Flash emulator could not start this game.'
                    : 'The game took too long to start. Check your connection and try again.');
            }, LOAD_TIMEOUT_MS);
        },

        onFrameLoad() {
            // Third-party embeds don't speak our protocol: treat the load event as ready.
            if (!MANAGED.includes(this.$root.dataset.engine)) this.ready();
        },

        ready() {
            if (this.state !== 'loading') return;
            clearTimeout(this.timer);
            this.state = 'playing';
            this.send({ type: 'nebulo:mute', muted: this.muted });
            this.sendSeed();
            this.reportStatus('loaded');
            try { this.iframe.focus(); } catch (e) { /* ignore */ }
        },

        fail(message) {
            clearTimeout(this.timer);
            this.state = 'error';
            this.errorMessage = message || '';
            this.reportStatus('failed');
        },

        onMessage(event) {
            if (!this.iframe || event.source !== this.iframe.contentWindow) return;
            const msg = event.data;
            if (!msg || typeof msg !== 'object' || typeof msg.type !== 'string') return;
            switch (msg.type) {
                case 'nebulo:ready': this.ready(); break;
                case 'nebulo:error': this.fail(String(msg.message || '').slice(0, 200)); break;
                case 'nebulo:score':
                    if (Number.isFinite(msg.score)) this.lastScore = Math.floor(msg.score);
                    break;
                case 'nebulo:gameover':
                    if (Number.isFinite(msg.score)) {
                        this.lastScore = Math.floor(msg.score);
                        this.submitScore(msg);
                    }
                    break;
                default: break;
            }
        },

        send(message) {
            if (this.iframe && this.iframe.contentWindow) {
                // Target '*' is required for opaque-origin sandboxed frames; we only send UI commands.
                this.iframe.contentWindow.postMessage(message, '*');
            }
        },

        restart() {
            this.reportStatus('ended');
            this.state = 'loading';
            this.lastScore = null;
            this.mountFrame();
            this.requestScoreSession();
        },

        toggleMute() {
            this.muted = !this.muted;
            try { localStorage.setItem('player_muted', this.muted ? '1' : '0'); } catch (e) { /* ignore */ }
            this.send({ type: 'nebulo:mute', muted: this.muted });
        },

        async toggleFullscreen() {
            if (this.pseudoFullscreen) { this.pseudoFullscreen = false; document.body.style.overflow = ''; return; }
            if (document.fullscreenElement) { await document.exitFullscreen(); return; }
            if (this.$root.requestFullscreen) {
                try {
                    await this.$root.requestFullscreen({ navigationUI: 'hide' });
                    if (this.$root.dataset.orientation === 'landscape' && screen.orientation && screen.orientation.lock) {
                        screen.orientation.lock('landscape').catch(() => {});
                    }
                    return;
                } catch (e) { /* fall through to CSS fullscreen (iPhone Safari) */ }
            }
            this.pseudoFullscreen = true;
            document.body.style.overflow = 'hidden';
        },

        checkOrientation() {
            const wantsLandscape = this.$root.dataset.orientation === 'landscape';
            this.rotateHint = wantsLandscape && !this.rotateDismissed && this.state !== 'idle'
                && isTouchOnly() && window.innerHeight > window.innerWidth;
        },

        dismissRotate() { this.rotateDismissed = true; this.rotateHint = false; },

        reportStatus(status) {
            if (!this.statusUrl) return;
            const payload = { status, seconds: Math.round((Date.now() - this.startedAt) / 1000) };
            if (status === 'ended' && navigator.sendBeacon) {
                // Beacons can't set headers, so the CSRF token travels in the JSON body.
                payload._token = document.querySelector('meta[name="csrf-token"]')?.content || '';
                navigator.sendBeacon(this.statusUrl, new Blob([JSON.stringify(payload)], { type: 'application/json' }));
                return;
            }
            postJson(this.statusUrl, payload).catch(() => {});
        },

        async requestScoreSession() {
            this.scoreToken = null;
            const url = this.$root.dataset.scoreSessionUrl;
            if (!url) return;
            try {
                const res = await postJson(url, {});
                this.scoreToken = res.token;
                this.scoreSeed = res.seed;
                this.sendSeed();
            } catch (e) { /* scores are optional */ }
        },

        // The server-issued seed lets verified games be replayed deterministically on the server.
        sendSeed() {
            if (this.state === 'playing' && Number.isInteger(this.scoreSeed)) {
                this.send({ type: 'nebulo:session', seed: this.scoreSeed });
            }
        },

        async submitScore(msg) {
            const url = this.$root.dataset.scoreUrl;
            if (!url || !this.scoreToken) return;
            const token = this.scoreToken;
            this.scoreToken = null; // single use
            try {
                const res = await postJson(url, {
                    token,
                    score: Math.floor(msg.score),
                    duration_ms: Math.floor(Number(msg.durationMs) || (Date.now() - this.startedAt)),
                    evidence: msg.evidence && typeof msg.evidence === 'object' ? msg.evidence : null,
                });
                if (res.message) window.dispatchEvent(new CustomEvent('toast', { detail: res.message }));
            } catch (e) { /* ignore */ }
            this.requestScoreSession();
        },
    }));
});
