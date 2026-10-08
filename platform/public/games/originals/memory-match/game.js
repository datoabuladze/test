/* Neon Pairs – original card-flip memory game for Nebulo.
 * Decks are shuffled with the SDK's seeded RNG; all icons are hand-made inline SVG.
 */
(function () {
  'use strict';
  var L = { en: 0, ka: 1, tr: 2, ru: 3 }[NebuloGame.lang] || 0;
  var TXT = {
    tagline: ['Flip the glowing cards and find every matching pair.', 'გადააბრუნე მანათობელი ბარათები და იპოვე ყველა წყვილი.', 'Parlayan kartları çevir ve tüm eşleri bul.', 'Переворачивайте светящиеся карты и найдите все пары.'][L],
    how: [
      ['Tap or click a card to flip it, then flip a second one.', 'Two equal symbols stay open; otherwise both turn back.', 'Matches in a row give combo points.', 'Fewer moves and a faster time earn more stars.', 'Keyboard: arrow keys to move, Space / Enter to flip.'],
      ['შეეხე ან დააწკაპუნე ბარათს მის გადასაბრუნებლად, შემდეგ — მეორეს.', 'ორი ერთნაირი სიმბოლო ღია რჩება, სხვა შემთხვევაში ორივე ბრუნდება.', 'ზედიზედ გამოცნობილი წყვილები კომბო-ქულებს იძლევა.', 'ნაკლები სვლა და სწრაფი დრო მეტ ვარსკვლავს გაძლევს.', 'კლავიატურა: ისრებით გადაადგილება, Space / Enter — გადაბრუნება.'],
      ['Bir kartı çevirmek için dokun veya tıkla, sonra ikincisini çevir.', 'Aynı iki sembol açık kalır; değilse ikisi de geri döner.', 'Art arda eşleşmeler kombo puanı kazandırır.', 'Daha az hamle ve daha hızlı süre daha çok yıldız getirir.', 'Klavye: ok tuşlarıyla gezin, Space / Enter ile çevir.'],
      ['Нажмите на карту, чтобы открыть её, затем откройте вторую.', 'Два одинаковых символа остаются открытыми, иначе обе карты закрываются.', 'Пары подряд приносят комбо-очки.', 'Меньше ходов и быстрее время — больше звёзд.', 'Клавиатура: стрелки — выбор, Space / Enter — открыть.'],
    ][L],
    difficulty: ['Difficulty', 'სირთულე', 'Zorluk', 'Сложность'][L],
    levels: [['Easy · 12', 'Normal · 16', 'Hard · 30'], ['მარტივი · 12', 'საშუალო · 16', 'რთული · 30'], ['Kolay · 12', 'Orta · 16', 'Zor · 30'], ['Легко · 12', 'Средне · 16', 'Сложно · 30']][L],
    pairs: ['Pairs', 'წყვილი', 'Çift', 'Пары'][L],
    combo: ['Combo', 'კომბო', 'Kombo', 'Комбо'][L],
    summary: function (m, t) {
      return ['Moves: ' + m + ' · Time: ' + t, 'სვლები: ' + m + ' · დრო: ' + t, 'Hamle: ' + m + ' · Süre: ' + t, 'Ходы: ' + m + ' · Время: ' + t][L];
    },
    bonus: function (tb, mb) {
      return ['Time bonus +' + tb + ' · Move bonus +' + mb, 'დროის ბონუსი +' + tb + ' · სვლების ბონუსი +' + mb, 'Süre bonusu +' + tb + ' · Hamle bonusu +' + mb, 'Бонус за время +' + tb + ' · Бонус за ходы +' + mb][L];
    },
  };

  var game = NebuloGame.create({ id: 'memory-match', title: 'Neon Pairs', tagline: TXT.tagline, howTo: TXT.how });

  // Run fn after ms if the same round is still active (waits while paused, drops it after a restart).
  var roundId = 0;
  game.on('start', function () { roundId++; });
  function later(fn, ms) {
    var rid = roundId;
    setTimeout(function tick() {
      if (rid !== roundId) return;
      if (game.state === 'paused') { setTimeout(tick, 250); return; }
      if (game.state === 'playing') fn();
    }, ms);
  }

  // ---------------------------------------------------------------- original icon set (viewBox 0 0 64 64, currentColor)
  var ICONS = [
    { c: '#fde047', d: '<path d="M32 6l7.6 16.4 17.9 2.1-13.2 12.2 3.5 17.7L32 45.6 16.2 54.4l3.5-17.7L6.5 24.5l17.9-2.1z"/>' },
    { c: '#a5b4fc', d: '<path d="M40 7A25 25 0 1 0 57 45 20 20 0 0 1 40 7z"/><circle cx="50" cy="16" r="2.5"/><circle cx="56" cy="27" r="1.6"/>' },
    { c: '#f472b6', d: '<circle cx="32" cy="32" r="14"/><ellipse cx="32" cy="32" rx="28" ry="9" fill="none" stroke="currentColor" stroke-width="4" transform="rotate(-22 32 32)"/>' },
    { c: '#e2e8f0', d: '<path d="M32 5c9 7 13 18 12 30l6 8-2 8-9-5H25l-9 5-2-8 6-8C19 23 23 12 32 5z"/><circle cx="32" cy="25" r="5" fill="#0b0d17"/><path d="M26 49h12l-6 11z" fill="#fb923c"/>' },
    { c: '#22d3ee', d: '<path d="M37 4L12 36h16l-4 24 26-34H34z"/>' },
    { c: '#fb7185', d: '<path d="M32 56S7 41 7 23a12.5 12.5 0 0 1 25-4 12.5 12.5 0 0 1 25 4c0 18-25 33-25 33z"/>' },
    { c: '#38bdf8', d: '<path d="M18 10h28l12 14-26 32L6 24z"/><path d="M6 24h52M24 10l-4 14 12 32 12-32-4-14" fill="none" stroke="#fff" stroke-opacity=".5" stroke-width="2"/>' },
    { c: '#4ade80', d: '<path d="M55 7C24 8 9 24 9 44c0 4 1 8 3 11 4-14 14-24 28-30-12 8-20 18-24 32 30 2 41-22 39-50z"/>' },
    { c: '#fb923c', d: '<path d="M32 4c4 12 18 18 18 34a18 18 0 0 1-36 0c0-9 5-14 9-18 0 6 3 10 7 11-3-9 0-19 2-27z"/><path d="M32 34c2 5 8 7 8 13a8 8 0 0 1-16 0c0-4 3-6 5-8 0 3 1 4 3 4-1-3 0-6 0-9z" fill="#fde047"/>' },
    { c: '#60a5fa', d: '<path d="M32 6C24 20 14 30 14 40a18 18 0 0 0 36 0C50 30 40 20 32 6z"/><path d="M23 41a9 9 0 0 0 8 9" fill="none" stroke="#fff" stroke-opacity=".6" stroke-width="3" stroke-linecap="round"/>' },
    { c: '#fbbf24', d: '<path d="M8 19l12 13 12-21 12 21 12-13-5 30H13z"/><rect x="13" y="52" width="38" height="6" rx="2"/><circle cx="32" cy="36" r="4" fill="#f472b6"/>' },
    { c: '#c084fc', d: '<circle cx="20" cy="32" r="11" fill="none" stroke="currentColor" stroke-width="6"/><path d="M30 29h27v6h-5v9h-6v-9h-4v6h-6v-6h-6z"/>' },
    { c: '#f0abfc', d: '<path d="M24 13l28-7v36a8 8 0 1 1-6-7.7V17l-16 4v27a8 8 0 1 1-6-7.7z"/>' },
    { c: '#67e8f9', d: '<g fill="none" stroke="currentColor" stroke-width="5" stroke-linecap="round"><path d="M32 6v52M9.5 19l45 26M9.5 45l45-26"/><path d="M25 10l7 6 7-6M25 54l7-6 7 6M8 30l9 2-3 9M56 30l-9 2 3 9"/></g>' },
    { c: '#ef4444', d: '<path d="M6 33C6 18 18 8 32 8s26 10 26 25z"/><path d="M24 33h16l3 22H21z" fill="#f1e7d0"/><circle cx="21" cy="21" r="4" fill="#fff" opacity=".85"/><circle cx="40" cy="17" r="5" fill="#fff" opacity=".85"/><circle cx="47" cy="27" r="3" fill="#fff" opacity=".85"/>' },
  ];
  function iconSvg(i) { return '<svg viewBox="0 0 64 64" fill="currentColor" style="color:' + ICONS[i].c + '">' + ICONS[i].d + '</svg>'; }

  var LEVELS = [{ pairs: 6, a: 4, b: 3, mult: 1 }, { pairs: 8, a: 4, b: 4, mult: 2 }, { pairs: 15, a: 6, b: 5, mult: 3 }];
  var diff = game.store.get('memory-match.diff', 1);
  if (!(diff >= 0 && diff <= 2)) diff = 1;
  var lvl, cards = [], open = [], moves, matches, combo, score, missTimer = null, cursor = 0, cols = 4, rows = 3, lastSec = -1, kbMode = false, winTimer = null;

  var board = document.createElement('div'); board.id = 'mm-board';
  game.stage.appendChild(board);

  // ---------------------------------------------------------------- particles / floating text
  var fx = (function () {
    var cv = document.createElement('canvas'); cv.className = 'fx'; game.stage.appendChild(cv);
    var ctx = cv.getContext('2d'); var parts = []; var running = false;
    function size() {
      var r = game.stage.getBoundingClientRect(); var d = Math.min(window.devicePixelRatio || 1, 2);
      cv.width = Math.round(r.width * d); cv.height = Math.round(r.height * d);
      cv.style.width = r.width + 'px'; cv.style.height = r.height + 'px'; ctx.setTransform(d, 0, 0, d, 0, 0);
    }
    function tick() {
      var r = game.stage.getBoundingClientRect();
      ctx.clearRect(0, 0, r.width, r.height);
      for (var i = parts.length - 1; i >= 0; i--) {
        var p = parts[i]; p.life -= 1 / 60;
        if (p.life <= 0) { parts.splice(i, 1); continue; }
        p.x += p.vx; p.y += p.vy; p.vy += p.g; p.vx *= 0.97;
        ctx.globalAlpha = Math.min(1, p.life / p.max * 1.6);
        if (p.text) {
          ctx.font = '900 ' + p.size + 'px system-ui, sans-serif'; ctx.textAlign = 'center'; ctx.fillStyle = p.color;
          ctx.shadowColor = p.color; ctx.shadowBlur = 12; ctx.fillText(p.text, p.x, p.y); ctx.shadowBlur = 0;
        } else {
          ctx.fillStyle = p.color; ctx.beginPath(); ctx.arc(p.x, p.y, p.size * (p.life / p.max), 0, Math.PI * 2); ctx.fill();
        }
      }
      ctx.globalAlpha = 1;
      if (parts.length) requestAnimationFrame(tick); else running = false;
    }
    function kick() { if (!running) { running = true; requestAnimationFrame(tick); } }
    window.addEventListener('resize', size); size();
    return {
      size: size,
      burst: function (x, y, color, n) {
        for (var i = 0; i < n; i++) {
          var a = Math.random() * Math.PI * 2, s = 1.5 + Math.random() * 4.5;
          parts.push({ x: x, y: y, vx: Math.cos(a) * s, vy: Math.sin(a) * s - 1, g: 0.12, size: 2 + Math.random() * 3.5, life: 0.6 + Math.random() * 0.5, max: 1.1, color: color });
        }
        kick();
      },
      text: function (x, y, text, color) { parts.push({ x: x, y: y, vx: 0, vy: -1.1, g: 0.012, size: 22, life: 1, max: 1, color: color, text: text }); kick(); },
      clear: function () { parts.length = 0; },
    };
  })();

  // ---------------------------------------------------------------- helpers
  function fmtTime(sec) { sec = Math.max(0, Math.floor(sec)); return Math.floor(sec / 60) + ':' + ('0' + (sec % 60)).slice(-2); }
  function shuffle(arr, rng) { for (var i = arr.length - 1; i > 0; i--) { var j = Math.floor(rng() * (i + 1)); var t = arr[i]; arr[i] = arr[j]; arr[j] = t; } return arr; }
  function starsFor(m, p) { return m <= Math.ceil(p * 1.5) ? 3 : m <= Math.ceil(p * 2.2) ? 2 : 1; }

  function addDiffPicker() {
    var ov = game._overlay; if (!ov) return;
    var card = ov.querySelector('.ng-card'); var acts = card.querySelector('.ng-actions');
    var wrap = document.createElement('div'); wrap.className = 'xx-diff';
    var lab = document.createElement('div'); lab.className = 'xx-diff-label'; lab.textContent = TXT.difficulty; wrap.appendChild(lab);
    var row = document.createElement('div'); row.className = 'xx-diff-row'; wrap.appendChild(row);
    TXT.levels.forEach(function (name, i) {
      var b = document.createElement('button'); b.type = 'button'; b.className = 'xx-chip' + (i === diff ? ' on' : ''); b.textContent = name;
      b.addEventListener('click', function () {
        diff = i; game.store.set('memory-match.diff', i); game.audio.sfx('click');
        Array.prototype.forEach.call(row.children, function (c, k) { c.classList.toggle('on', k === i); });
      });
      row.appendChild(b);
    });
    card.insertBefore(wrap, acts);
  }

  // ---------------------------------------------------------------- layout
  function layout() {
    var r = game.stage.getBoundingClientRect();
    if (!r.width || !r.height) return;
    var lv = lvl || LEVELS[diff];
    var land = r.width >= r.height;
    cols = land ? Math.max(lv.a, lv.b) : Math.min(lv.a, lv.b);
    rows = (lv.pairs * 2) / cols;
    var pad = Math.max(12, Math.min(r.width, r.height) * 0.04);
    var gap = Math.max(6, Math.min(14, Math.min(r.width, r.height) * 0.018));
    var ratio = 1.18;
    var cw = Math.min((r.width - pad * 2 - gap * (cols - 1)) / cols, (r.height - pad * 2 - gap * (rows - 1)) / rows / ratio);
    cw = Math.max(30, Math.min(150, cw));
    var ch = cw * ratio;
    board.style.width = (cols * cw + (cols - 1) * gap) + 'px';
    board.style.height = (rows * ch + (rows - 1) * gap) + 'px';
    cards.forEach(function (c, i) {
      var cx = i % cols, cy = Math.floor(i / cols);
      c.el.style.left = (cx * (cw + gap)) + 'px'; c.el.style.top = (cy * (ch + gap)) + 'px';
      c.el.style.width = cw + 'px'; c.el.style.height = ch + 'px';
    });
    fx.size();
  }

  // ---------------------------------------------------------------- game flow
  function clearTimers() { if (missTimer) { clearTimeout(missTimer); missTimer = null; } if (winTimer) { clearTimeout(winTimer); winTimer = null; } }

  function newGame() {
    clearTimers(); fx.clear();
    lvl = LEVELS[diff];
    var ids = shuffle(ICONS.map(function (_, i) { return i; }), game.rng).slice(0, lvl.pairs);
    var deck = shuffle(ids.concat(ids), game.rng);
    board.innerHTML = ''; board.classList.remove('blur');
    cards = deck.map(function (icon, i) {
      var el = document.createElement('div'); el.className = 'mm-card';
      el.innerHTML = '<div class="mm-inner"><div class="mm-side mm-back"></div><div class="mm-side mm-face"></div></div>';
      el.style.setProperty('--c', ICONS[icon].c);
      el.style.setProperty('--glow', ICONS[icon].c + '40');
      el.addEventListener('pointerdown', function (e) { e.preventDefault(); kbMode = false; refreshCursor(); flip(i); });
      board.appendChild(el);
      return { icon: icon, el: el, up: false, matched: false };
    });
    open = []; moves = 0; matches = 0; combo = 0; score = 0; cursor = 0; lastSec = -1;
    game.setStat('score', 0);
    game.setStat('pairs', '0/' + lvl.pairs, TXT.pairs);
    game.setStat('moves', 0);
    game.setStat('time', '0:00');
    layout();
    refreshCursor();
  }

  function cardCenter(i) {
    var r = cards[i].el.getBoundingClientRect(), s = game.stage.getBoundingClientRect();
    return { x: r.left - s.left + r.width / 2, y: r.top - s.top + r.height / 2 };
  }

  function hideMismatch() {
    if (missTimer) { clearTimeout(missTimer); missTimer = null; }
    open.forEach(function (k) { var c = cards[k]; c.up = false; c.el.classList.remove('up', 'miss'); });
    open = [];
  }

  function flip(i) {
    if (game.state !== 'playing') return;
    var c = cards[i]; if (!c || c.matched || c.up) return;
    if (open.length === 2) hideMismatch();
    c.up = true;
    c.el.querySelector('.mm-face').innerHTML = iconSvg(c.icon);
    c.el.classList.add('up');
    game.audio.tone(520 + open.length * 140, 0.06, 'triangle', 0.1);
    open.push(i);
    if (open.length < 2) return;
    moves++; game.setStat('moves', moves);
    var a = cards[open[0]], b = cards[open[1]];
    if (a.icon === b.icon) {
      combo++; matches++;
      var gain = 100 * lvl.mult + 25 * lvl.mult * (combo - 1);
      score += gain;
      a.matched = b.matched = true;
      [open[0], open[1]].forEach(function (k) {
        var cc = cards[k]; setTimeout(function () { cc.el.classList.add('matched'); }, 200);
        var p = cardCenter(k); setTimeout(function () { fx.burst(p.x, p.y, ICONS[cc.icon].c, 22); }, 220);
      });
      var pc = cardCenter(open[1]);
      setTimeout(function () { fx.text(pc.x, pc.y - 10, '+' + gain + (combo > 1 ? '  ×' + combo : ''), combo > 1 ? '#fde047' : '#22d3ee'); }, 220);
      open = [];
      game.audio.sfx(combo > 1 ? 'bonus' : 'point');
      game.setStat('score', score);
      game.setStat('pairs', matches + '/' + lvl.pairs, TXT.pairs);
      if (matches === lvl.pairs) later(win, 750);
    } else {
      combo = 0;
      setTimeout(function () { if (a.up && !a.matched) a.el.classList.add('miss'); if (b.up && !b.matched) b.el.classList.add('miss'); }, 330);
      game.audio.tone(200, 0.14, 'triangle', 0.12, 150);
      missTimer = setTimeout(hideMismatch, 900);
    }
  }

  function win() {
    winTimer = null;
    if (game.state !== 'playing') return;
    var sec = Math.floor(game.elapsed() / 1000);
    var tb = Math.max(0, lvl.pairs * 8 - sec) * 5 * lvl.mult;
    var mb = Math.max(0, lvl.pairs * 2 - moves) * 20 * lvl.mult;
    var total = score + tb + mb;
    var stars = starsFor(moves, lvl.pairs);
    game.setStat('score', total);
    game.over({ score: total, win: true, lines: [TXT.summary(moves, fmtTime(sec)), TXT.bonus(tb, mb)], evidence: { moves: moves, pairs: lvl.pairs, diff: diff, sec: sec } });
    var ov = game._overlay;
    if (ov) {
      var st = document.createElement('div'); st.className = 'xx-stars';
      st.textContent = '★★★'.slice(0, stars) + '☆☆☆'.slice(0, 3 - stars);
      var big = ov.querySelector('.ng-big'); big.parentNode.insertBefore(st, big);
    }
    addDiffPicker();
    var s = game.stage.getBoundingClientRect();
    for (var k = 0; k < 6; k++) (function (k) { setTimeout(function () { fx.burst(s.width * (0.15 + 0.14 * k), s.height * 0.35, ['#22d3ee', '#7c5cff', '#f472b6'][k % 3], 30); }, k * 90); })(k);
  }

  function refreshCursor() {
    cards.forEach(function (c, i) { c.el.classList.toggle('kb', kbMode && i === cursor && game.state === 'playing'); });
  }

  game.on('keydown', function (e) {
    if (game.state !== 'playing' || !cards.length) return;
    var x = cursor % cols, y = Math.floor(cursor / cols);
    var k = e.key;
    if (k === 'ArrowLeft' || k === 'a' || k === 'A') x = (x + cols - 1) % cols;
    else if (k === 'ArrowRight' || k === 'd' || k === 'D') x = (x + 1) % cols;
    else if (k === 'ArrowUp' || k === 'w' || k === 'W') y = (y + rows - 1) % rows;
    else if (k === 'ArrowDown' || k === 's' || k === 'S') y = (y + 1) % rows;
    else if (k === ' ' || k === 'Enter') { if (kbMode) flip(cursor); kbMode = true; refreshCursor(); return; }
    else return;
    if (!kbMode) { kbMode = true; refreshCursor(); return; }
    cursor = y * cols + x; refreshCursor();
  });

  game.on('start', newGame);
  game.on('stop', clearTimers);
  game.on('pause', function () { board.classList.add('blur'); });
  game.on('resume', function () { board.classList.remove('blur'); });

  game.loop(function () {}, function () {
    if (game.state !== 'playing') return;
    var sec = Math.floor(game.elapsed() / 1000);
    if (sec !== lastSec) { lastSec = sec; game.setStat('time', fmtTime(sec)); }
  });

  window.addEventListener('resize', layout);
  if (window.ResizeObserver) new ResizeObserver(layout).observe(game.stage);

  game.showMenu();
  addDiffPicker();
  game.ready();
})();
