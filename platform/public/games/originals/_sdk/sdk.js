/*!
 * Nebulo Game SDK – shared runtime for Nebulo original games.
 * Classic script (no modules) so it runs in sandboxed, opaque-origin frames without CORS.
 *
 * Provides: HUD (title, stats, pause/restart/mute buttons), overlays (start / pause / game over),
 * fixed-timestep loop with auto-pause, crisp HiDPI canvas fitting, seeded RNG (mulberry32),
 * procedural WebAudio sound effects, safe storage, keyboard + touch helpers, and the
 * page <-> frame postMessage protocol (ready, score, gameover, mute, pause, resume, session seed).
 */
(function () {
  'use strict';

  var parentWin = window.parent !== window ? window.parent : null;
  function post(msg) { if (parentWin) { try { parentWin.postMessage(msg, '*'); } catch (e) { /* ignore */ } } }

  // ------------------------------------------------------------------ i18n
  var LANG = (function () {
    var l = (navigator.language || 'en').slice(0, 2).toLowerCase();
    try { var q = new URLSearchParams(location.search).get('lang'); if (q) l = q; } catch (e) { /* ignore */ }
    return ['en', 'ka', 'tr', 'ru'].indexOf(l) >= 0 ? l : 'en';
  })();
  var STR = {
    en: { play: 'Play', resume: 'Resume', restart: 'Restart', paused: 'Paused', gameOver: 'Game over', youWin: 'You win!', score: 'Score', best: 'Best', newBest: 'New best!', howTo: 'How to play', level: 'Level', time: 'Time', moves: 'Moves', lives: 'Lives', again: 'Play again', next: 'Next level' },
    ka: { play: 'თამაში', resume: 'გაგრძელება', restart: 'თავიდან', paused: 'პაუზა', gameOver: 'თამაში დასრულდა', youWin: 'მოიგე!', score: 'ქულა', best: 'რეკორდი', newBest: 'ახალი რეკორდი!', howTo: 'როგორ ვითამაშოთ', level: 'დონე', time: 'დრო', moves: 'სვლები', lives: 'სიცოცხლე', again: 'კიდევ ერთხელ', next: 'შემდეგი დონე' },
    tr: { play: 'Oyna', resume: 'Devam', restart: 'Yeniden başla', paused: 'Duraklatıldı', gameOver: 'Oyun bitti', youWin: 'Kazandın!', score: 'Puan', best: 'En iyi', newBest: 'Yeni rekor!', howTo: 'Nasıl oynanır', level: 'Seviye', time: 'Süre', moves: 'Hamle', lives: 'Can', again: 'Tekrar oyna', next: 'Sonraki seviye' },
    ru: { play: 'Играть', resume: 'Продолжить', restart: 'Заново', paused: 'Пауза', gameOver: 'Игра окончена', youWin: 'Победа!', score: 'Очки', best: 'Рекорд', newBest: 'Новый рекорд!', howTo: 'Как играть', level: 'Уровень', time: 'Время', moves: 'Ходы', lives: 'Жизни', again: 'Ещё раз', next: 'Следующий уровень' },
  };
  function t(key) { return (STR[LANG] && STR[LANG][key]) || STR.en[key] || key; }

  // ------------------------------------------------------------------ storage (opaque origins throw on access)
  var mem = {};
  var store = {
    get: function (k, d) {
      try { var v = window.localStorage.getItem('ng.' + k); return v === null ? d : JSON.parse(v); }
      catch (e) { return k in mem ? mem[k] : d; }
    },
    set: function (k, v) {
      mem[k] = v;
      try { window.localStorage.setItem('ng.' + k, JSON.stringify(v)); } catch (e) { /* memory only */ }
    },
  };

  // ------------------------------------------------------------------ RNG
  function mulberry32(seed) {
    var a = seed >>> 0;
    return function () {
      a |= 0; a = a + 0x6D2B79F5 | 0;
      var t2 = Math.imul(a ^ a >>> 15, 1 | a);
      t2 = t2 + Math.imul(t2 ^ t2 >>> 7, 61 | t2) ^ t2;
      return ((t2 ^ t2 >>> 14) >>> 0) / 4294967296;
    };
  }
  function randomSeed() {
    try { var b = new Uint32Array(1); crypto.getRandomValues(b); return (b[0] % 2147483646) + 1; }
    catch (e) { return Math.floor(Math.random() * 2147483646) + 1; }
  }

  // ------------------------------------------------------------------ audio (procedural, original)
  var audio = {
    ctx: null, muted: false, master: null,
    ensure: function () {
      if (this.ctx) { if (this.ctx.state === 'suspended') this.ctx.resume(); return this.ctx; }
      var AC = window.AudioContext || window.webkitAudioContext;
      if (!AC) return null;
      this.ctx = new AC();
      this.master = this.ctx.createGain();
      this.master.gain.value = this.muted ? 0 : 0.5;
      this.master.connect(this.ctx.destination);
      return this.ctx;
    },
    setMuted: function (m) {
      this.muted = !!m;
      if (this.master) this.master.gain.value = this.muted ? 0 : 0.5;
    },
    /** tone(freq, durationSec, type, volume, slideToFreq) */
    tone: function (freq, dur, type, vol, slideTo) {
      if (this.muted) return;
      var ctx = this.ensure(); if (!ctx) return;
      var now = ctx.currentTime;
      var o = ctx.createOscillator(); var g = ctx.createGain();
      o.type = type || 'square'; o.frequency.setValueAtTime(freq, now);
      if (slideTo) o.frequency.exponentialRampToValueAtTime(Math.max(20, slideTo), now + dur);
      g.gain.setValueAtTime(0.0001, now);
      g.gain.exponentialRampToValueAtTime(vol || 0.2, now + 0.01);
      g.gain.exponentialRampToValueAtTime(0.0001, now + dur);
      o.connect(g); g.connect(this.master); o.start(now); o.stop(now + dur + 0.02);
    },
    noise: function (dur, vol) {
      if (this.muted) return;
      var ctx = this.ensure(); if (!ctx) return;
      var len = Math.floor(ctx.sampleRate * dur); var buf = ctx.createBuffer(1, len, ctx.sampleRate);
      var data = buf.getChannelData(0); for (var i = 0; i < len; i++) data[i] = (Math.random() * 2 - 1) * (1 - i / len);
      var src = ctx.createBufferSource(); var g = ctx.createGain(); g.gain.value = vol || 0.2;
      src.buffer = buf; src.connect(g); g.connect(this.master); src.start();
    },
    // Named effects so games share a consistent, original sound palette.
    sfx: function (name) {
      switch (name) {
        case 'click': this.tone(660, 0.05, 'square', 0.08); break;
        case 'move': this.tone(330, 0.04, 'triangle', 0.08); break;
        case 'point': this.tone(880, 0.08, 'square', 0.12, 1320); break;
        case 'bonus': this.tone(523, 0.08, 'square', 0.12); var s = this; setTimeout(function () { s.tone(784, 0.08, 'square', 0.12); }, 70); setTimeout(function () { s.tone(1047, 0.12, 'square', 0.12); }, 140); break;
        case 'hit': this.noise(0.12, 0.25); this.tone(140, 0.12, 'sawtooth', 0.12, 70); break;
        case 'explode': this.noise(0.35, 0.35); this.tone(90, 0.35, 'sawtooth', 0.15, 40); break;
        case 'jump': this.tone(300, 0.15, 'square', 0.1, 700); break;
        case 'lose': this.tone(392, 0.18, 'triangle', 0.15, 196); var s2 = this; setTimeout(function () { s2.tone(262, 0.35, 'triangle', 0.15, 131); }, 180); break;
        case 'win': var s3 = this; [523, 659, 784, 1047].forEach(function (f, i) { setTimeout(function () { s3.tone(f, 0.14, 'square', 0.12); }, i * 110); }); break;
        case 'tick': this.tone(1200, 0.02, 'square', 0.05); break;
        default: this.tone(440, 0.06, 'square', 0.08);
      }
    },
  };

  // ------------------------------------------------------------------ DOM helpers
  function el(tag, attrs, children) {
    var n = document.createElement(tag);
    if (attrs) for (var k in attrs) {
      if (k === 'text') n.textContent = attrs[k];
      else if (k === 'html') n.innerHTML = attrs[k];
      else if (k.slice(0, 2) === 'on') n.addEventListener(k.slice(2), attrs[k]);
      else n.setAttribute(k, attrs[k]);
    }
    (children || []).forEach(function (c) { if (c) n.appendChild(typeof c === 'string' ? document.createTextNode(c) : c); });
    return n;
  }
  var ICONS = {
    pause: '<svg viewBox="0 0 24 24"><rect x="6" y="5" width="4" height="14" rx="1"/><rect x="14" y="5" width="4" height="14" rx="1"/></svg>',
    play: '<svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>',
    restart: '<svg viewBox="0 0 24 24"><path d="M4 12a8 8 0 1 0 2.4-5.7"/><path d="M4 4v4h4"/></svg>',
    sound: '<svg viewBox="0 0 24 24"><path d="M4 9h4l5-4v14l-5-4H4z"/><path d="M16.5 8.5a5 5 0 0 1 0 7"/></svg>',
    mute: '<svg viewBox="0 0 24 24"><path d="M4 9h4l5-4v14l-5-4H4z"/><path d="m17 9 5 6M22 9l-5 6"/></svg>',
  };

  // ------------------------------------------------------------------ the game object
  function createGame(opts) {
    opts = opts || {};
    var game = {
      id: opts.id || 'game',
      title: opts.title || document.title,
      t: t, lang: LANG, store: store, audio: audio, el: el,
      state: 'menu', // menu | playing | paused | over
      startedAt: 0,
      seed: randomSeed(),
      pendingSeed: null,
      stats: {},
      _handlers: {},
      _hud: null, _stage: null, _overlay: null, _statEls: {},
    };

    function on(evt, fn) { (game._handlers[evt] = game._handlers[evt] || []).push(fn); }
    function emit(evt, arg) { (game._handlers[evt] || []).forEach(function (fn) { fn(arg); }); }
    game.on = on;

    // Build layout
    var root = el('div', { id: 'ng-root' });
    var hud = el('div', { id: 'ng-hud' });
    var stage = el('div', { id: 'ng-stage' });
    hud.appendChild(el('span', { class: 'ng-title', text: game.title }));
    var statsBox = el('div', { class: 'ng-stats' });
    hud.appendChild(statsBox);
    var pauseBtn = el('button', { class: 'ng-btn', type: 'button', 'aria-label': t('paused'), html: ICONS.pause, onclick: function () { game.togglePause(); } });
    var restartBtn = el('button', { class: 'ng-btn', type: 'button', 'aria-label': t('restart'), html: ICONS.restart, onclick: function () { game.restart(); } });
    var muteBtn = el('button', { class: 'ng-btn', type: 'button', 'aria-label': 'Sound', html: ICONS.sound, onclick: function () { game.setMuted(!audio.muted, true); } });
    if (opts.pausable !== false) hud.appendChild(pauseBtn);
    hud.appendChild(restartBtn);
    hud.appendChild(muteBtn);
    root.appendChild(hud); root.appendChild(stage);
    document.body.appendChild(root);
    game._hud = hud; game._stage = stage; game.stage = stage;

    // Stats in HUD: game.setStat('score', 10)
    game.setStat = function (key, value, label) {
      if (!game._statEls[key]) {
        var b = el('b'); var s = el('span', { class: 'ng-stat' }, [label || t(key), b]);
        statsBox.appendChild(s); game._statEls[key] = b;
      }
      game.stats[key] = value;
      game._statEls[key].textContent = value;
      if (key === 'score') post({ type: 'nebulo:score', score: Number(value) || 0 });
    };

    // Overlays
    game.overlay = function (content) {
      game.closeOverlay();
      var card = el('div', { class: 'ng-card' });
      if (content.title) card.appendChild(el('h2', { text: content.title }));
      if (content.big !== undefined) card.appendChild(el('div', { class: 'ng-big', text: String(content.big) }));
      (content.lines || []).forEach(function (line) { card.appendChild(el('p', { text: line })); });
      if (content.list) card.appendChild(el('ul', null, content.list.map(function (li) { return el('li', { text: li }); })));
      var actions = el('div', { class: 'ng-actions' });
      (content.actions || []).forEach(function (a, i) {
        var b = el('button', { class: 'ng-action' + (i > 0 ? ' ng-secondary' : ''), type: 'button', text: a.label, onclick: function () { audio.ensure(); a.onClick(); } });
        actions.appendChild(b);
        if (i === 0) setTimeout(function () { try { b.focus(); } catch (e) { /* ignore */ } }, 30);
      });
      card.appendChild(actions);
      var ov = el('div', { class: 'ng-overlay', role: 'dialog' }, [card]);
      stage.appendChild(ov); game._overlay = ov;
      return ov;
    };
    game.closeOverlay = function () { if (game._overlay) { game._overlay.remove(); game._overlay = null; } };

    game.showMenu = function () {
      game.state = 'menu';
      game.overlay({
        title: game.title,
        lines: opts.tagline ? [opts.tagline] : [],
        list: opts.howTo || [],
        actions: [{ label: t('play'), onClick: function () { game.start(); } }],
      });
    };

    game.start = function () {
      game.closeOverlay();
      if (game.pendingSeed) { game.seed = game.pendingSeed; game.pendingSeed = null; } else { game.seed = randomSeed(); }
      game.rng = mulberry32(game.seed);
      game.state = 'playing';
      game.startedAt = performance.now();
      game._pausedTotal = 0;
      emit('start');
    };
    game.restart = function () { emit('stop'); game.start(); };

    game.pause = function () {
      if (game.state !== 'playing') return;
      game.state = 'paused'; game._pausedAt = performance.now();
      emit('pause');
      game.overlay({ title: t('paused'), actions: [
        { label: t('resume'), onClick: function () { game.resume(); } },
        { label: t('restart'), onClick: function () { game.restart(); } },
      ] });
    };
    game.resume = function () {
      if (game.state !== 'paused') return;
      game.closeOverlay(); game.state = 'playing';
      game._pausedTotal += performance.now() - game._pausedAt;
      emit('resume');
    };
    game.togglePause = function () { if (game.state === 'playing') game.pause(); else if (game.state === 'paused') game.resume(); };

    /** Active play time in ms (excludes pauses). */
    game.elapsed = function () {
      var end = game.state === 'paused' ? game._pausedAt : performance.now();
      return Math.max(0, end - game.startedAt - (game._pausedTotal || 0));
    };

    /** End a round. opts: {score, win, evidence, lines} */
    game.over = function (o) {
      o = o || {};
      if (game.state === 'over') return;
      game.state = 'over';
      emit('stop');
      var score = Math.floor(o.score || 0);
      var bestKey = game.id + '.best';
      var best = store.get(bestKey, 0);
      var lowerIsBetter = !!opts.lowerIsBetter;
      var isBest = score > 0 && (best === 0 || (lowerIsBetter ? score < best : score > best));
      if (isBest) { best = score; store.set(bestKey, best); }
      audio.sfx(o.win ? 'win' : 'lose');
      if (opts.reportScores !== false) {
        post({ type: 'nebulo:gameover', score: score, durationMs: Math.round(game.elapsed()), evidence: o.evidence || null, win: !!o.win });
      }
      var lines = (o.lines || []).slice();
      lines.push(t('best') + ': ' + (o.formatScore ? o.formatScore(best) : best) + (isBest ? '  ★ ' + t('newBest') : ''));
      game.overlay({
        title: o.title || (o.win ? t('youWin') : t('gameOver')),
        big: o.formatScore ? o.formatScore(score) : score,
        lines: lines,
        actions: (o.actions || []).concat([{ label: t('again'), onClick: function () { game.start(); } }]),
      });
    };

    game.best = function () { return store.get(game.id + '.best', 0); };

    game.setMuted = function (m, persist) {
      audio.setMuted(m);
      muteBtn.innerHTML = m ? ICONS.mute : ICONS.sound;
      if (persist) store.set('muted', !!m);
    };
    game.setMuted(store.get('muted', false));

    // Fixed-timestep loop. update(dt seconds) runs only while playing; render() every frame.
    game.loop = function (update, render, step) {
      step = step || 1 / 60;
      var acc = 0; var last = performance.now();
      function frame(now) {
        var dt = Math.min(0.25, (now - last) / 1000); last = now;
        if (game.state === 'playing') {
          acc += dt;
          while (acc >= step) { update(step); acc -= step; if (game.state !== 'playing') { acc = 0; break; } }
        } else { acc = 0; }
        if (render) render(dt);
        requestAnimationFrame(frame);
      }
      requestAnimationFrame(frame);
    };

    /**
     * Create a canvas that fills the stage while keeping a logical size (w x h).
     * Returns {canvas, ctx, scale, toLogical(clientX, clientY)}; drawing uses logical units.
     */
    game.canvas = function (w, h) {
      var canvas = el('canvas'); stage.insertBefore(canvas, stage.firstChild);
      var ctx = canvas.getContext('2d');
      var view = { canvas: canvas, ctx: ctx, w: w, h: h, scale: 1 };
      function fit() {
        var rect = stage.getBoundingClientRect();
        var s = Math.min(rect.width / w, rect.height / h);
        var dpr = Math.min(window.devicePixelRatio || 1, 2.5);
        canvas.style.width = Math.floor(w * s) + 'px';
        canvas.style.height = Math.floor(h * s) + 'px';
        canvas.width = Math.floor(w * s * dpr);
        canvas.height = Math.floor(h * s * dpr);
        ctx.setTransform(s * dpr, 0, 0, s * dpr, 0, 0);
        view.scale = s;
        emit('resize', view);
      }
      view.fit = fit;
      view.toLogical = function (clientX, clientY) {
        var r = canvas.getBoundingClientRect();
        return { x: (clientX - r.left) / view.scale, y: (clientY - r.top) / view.scale };
      };
      window.addEventListener('resize', fit);
      if (window.ResizeObserver) new ResizeObserver(fit).observe(stage);
      fit();
      return view;
    };

    // Keyboard state + handlers. game.keys['ArrowLeft'] is true while held.
    game.keys = {};
    var PREVENT = ['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight', ' ', 'Space'];
    window.addEventListener('keydown', function (e) {
      if (PREVENT.indexOf(e.key) >= 0 || PREVENT.indexOf(e.code) >= 0) e.preventDefault();
      if ((e.key === 'p' || e.key === 'P' || e.key === 'Escape') && opts.pausable !== false) { game.togglePause(); return; }
      if (e.key === 'Enter' && game._overlay) { var b = game._overlay.querySelector('.ng-action'); if (b && document.activeElement !== b) { b.click(); return; } }
      if (!game.keys[e.key]) emit('keydown', e);
      game.keys[e.key] = true; game.keys[e.code] = true;
      audio.ensure();
    });
    window.addEventListener('keyup', function (e) { game.keys[e.key] = false; game.keys[e.code] = false; emit('keyup', e); });
    window.addEventListener('blur', function () { game.keys = {}; if (game.state === 'playing' && opts.pausable !== false) game.pause(); });

    /** Swipe detection on an element: cb('left'|'right'|'up'|'down'). */
    game.onSwipe = function (target, cb, minDist) {
      var sx = 0, sy = 0, active = false; minDist = minDist || 24;
      target.addEventListener('pointerdown', function (e) { sx = e.clientX; sy = e.clientY; active = true; audio.ensure(); });
      target.addEventListener('pointerup', function (e) {
        if (!active) return; active = false;
        var dx = e.clientX - sx, dy = e.clientY - sy;
        if (Math.max(Math.abs(dx), Math.abs(dy)) < minDist) return;
        cb(Math.abs(dx) > Math.abs(dy) ? (dx > 0 ? 'right' : 'left') : (dy > 0 ? 'down' : 'up'));
      });
      target.addEventListener('pointercancel', function () { active = false; });
    };

    /**
     * On-screen touch buttons. layout: [{key:'ArrowLeft', label:'◀'}, ...], position: 'left'|'right'|'center'.
     * Pressing a button sets game.keys[key] and emits keydown/keyup like a keyboard.
     */
    game.touchButtons = function (buttons, position, alwaysShow) {
      var bar = el('div', { class: 'ng-touch' + (alwaysShow ? '' : ' ng-auto') });
      bar.style.bottom = '14px';
      if (position === 'right') bar.style.right = '14px';
      else if (position === 'center') { bar.style.left = '50%'; bar.style.transform = 'translateX(-50%)'; }
      else bar.style.left = '14px';
      buttons.forEach(function (b) {
        var btn = el('button', { type: 'button', text: b.label, 'aria-label': b.aria || b.key });
        function down(e) { e.preventDefault(); audio.ensure(); btn.classList.add('ng-pressed'); if (!game.keys[b.key]) emit('keydown', { key: b.key, code: b.key, preventDefault: function () {} }); game.keys[b.key] = true; }
        function up(e) { e.preventDefault(); btn.classList.remove('ng-pressed'); game.keys[b.key] = false; emit('keyup', { key: b.key, code: b.key }); }
        btn.addEventListener('pointerdown', down); btn.addEventListener('pointerup', up);
        btn.addEventListener('pointerleave', up); btn.addEventListener('pointercancel', up);
        bar.appendChild(btn);
      });
      stage.appendChild(bar);
      return bar;
    };

    // Parent page protocol
    window.addEventListener('message', function (e) {
      if (!parentWin || e.source !== parentWin) return;
      var m = e.data; if (!m || typeof m !== 'object') return;
      if (m.type === 'nebulo:mute') game.setMuted(!!m.muted, false);
      else if (m.type === 'nebulo:pause') game.pause();
      else if (m.type === 'nebulo:resume') { /* stay paused until the player chooses */ }
      else if (m.type === 'nebulo:session' && Number.isInteger(m.seed)) { game.pendingSeed = m.seed; emit('session', m.seed); }
    });
    document.addEventListener('visibilitychange', function () { if (document.hidden) game.pause(); });

    game.ready = function () { post({ type: 'nebulo:ready', game: game.id }); };
    game.mulberry32 = mulberry32;
    game.post = post;

    window.addEventListener('error', function (e) { post({ type: 'nebulo:error', message: String(e.message || 'Script error').slice(0, 160) }); });

    return game;
  }

  window.NebuloGame = { create: createGame, mulberry32: mulberry32, t: t, lang: LANG };
})();
