/* Mine Field – original mine-detection logic game for Nebulo.
 * Mines are laid with the round's seeded RNG after the first reveal (which is always safe and opens an area).
 */
(function () {
  'use strict';
  var L = { en: 0, ka: 1, tr: 2, ru: 3 }[NebuloGame.lang] || 0;
  var TXT = {
    tagline: ['Clear the field without touching a single mine. Numbers tell you how many mines are next to a tile.', 'გაასუფთავე ველი ისე, რომ არც ერთ ნაღმს არ შეეხო. ციფრები გიჩვენებს, რამდენი ნაღმია ახლოს.', 'Tek bir mayına dokunmadan alanı temizle. Sayılar bir karenin yanında kaç mayın olduğunu söyler.', 'Очистите поле, не задев ни одной мины. Цифры показывают, сколько мин рядом с клеткой.'][L],
    how: [
      ['Click / tap a tile to dig. The first dig is always safe.', 'A number shows how many of the 8 neighbours hide a mine.', 'Right-click, long-press or Flag mode marks a mine.', 'Tap a number whose flags are complete to open its neighbours.', 'Open every safe tile to win – dig a mine and it is over.'],
      ['დააწკაპუნე / შეეხე ფილას გასათხრელად. პირველი თხრა ყოველთვის უსაფრთხოა.', 'ციფრი გიჩვენებს, 8 მეზობლიდან რამდენში იმალება ნაღმი.', 'მარჯვენა ღილაკი, ხანგრძლივი შეხება ან დროშის რეჟიმი ნაღმს მონიშნავს.', 'შეეხე ციფრს, რომლის დროშებიც სრულია, მეზობლების გასახსნელად.', 'გახსენი ყველა უსაფრთხო ფილა მოსაგებად – ნაღმი კი თამაშს ასრულებს.'],
      ['Kazmak için bir kareye tıkla / dokun. İlk kazı her zaman güvenlidir.', 'Sayı, 8 komşudan kaçında mayın olduğunu gösterir.', 'Sağ tık, uzun basma veya Bayrak modu mayını işaretler.', 'Bayrakları tamam olan bir sayıya dokunarak komşularını aç.', 'Kazanmak için tüm güvenli kareleri aç – mayına basarsan oyun biter.'],
      ['Нажмите на клетку, чтобы копать. Первый ход всегда безопасен.', 'Цифра показывает, сколько из 8 соседей скрывают мину.', 'Правый клик, долгое нажатие или режим «Флаг» отмечают мину.', 'Нажмите на цифру с полным набором флагов, чтобы открыть соседей.', 'Откройте все безопасные клетки — мина означает поражение.'],
    ][L],
    difficulty: ['Field size', 'ველის ზომა', 'Alan boyutu', 'Размер поля'][L],
    levels: [['Small · 9×9', 'Medium · 16×16', 'Large · 30×16'], ['პატარა · 9×9', 'საშუალო · 16×16', 'დიდი · 30×16'], ['Küçük · 9×9', 'Orta · 16×16', 'Büyük · 30×16'], ['Малое · 9×9', 'Среднее · 16×16', 'Большое · 30×16']][L],
    dig: ['Dig', 'თხრა', 'Kaz', 'Копать'][L],
    flag: ['Flag', 'დროშა', 'Bayrak', 'Флаг'][L],
    mines: ['Mines', 'ნაღმი', 'Mayın', 'Мины'][L],
    boom: ['Boom!', 'აფეთქება!', 'Bum!', 'Бах!'][L],
    cleared: function (t) { return ['Field cleared in ' + t, 'ველი გასუფთავდა: ' + t, 'Alan temizlendi: ' + t, 'Поле очищено за ' + t][L]; },
    opened: function (a, b) { return ['Safe tiles opened: ' + a + ' / ' + b, 'გახსნილი უსაფრთხო ფილები: ' + a + ' / ' + b, 'Açılan güvenli kareler: ' + a + ' / ' + b, 'Открыто безопасных клеток: ' + a + ' / ' + b][L]; },
  };
  var game = NebuloGame.create({ id: 'mine-sweeper', title: 'Mine Field', tagline: TXT.tagline, howTo: TXT.how });

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

  var LEVELS = [
    { a: 9, b: 9, mines: 10, mult: 1, base: 500, target: 300, k: 2 },
    { a: 16, b: 16, mines: 40, mult: 2, base: 2000, target: 900, k: 3 },
    { a: 30, b: 16, mines: 99, mult: 3, base: 5000, target: 1800, k: 4 },
  ];
  var NUMC = ['', '#22d3ee', '#4ade80', '#f472b6', '#a78bfa', '#fb923c', '#2dd4bf', '#facc15', '#e2e8f0'];
  var diff = game.store.get('mine-sweeper.diff', 0); if (!(diff >= 0 && diff <= 2)) diff = 0;

  var lvl = LEVELS[diff], cols = 9, rows = 9, N = 81;
  var mine = [], adj = [], open = [], flag = [], revealAt = [], generated = false, opened = 0, flags = 0, ended = false;
  var cursor = 0, kb = false, flagMode = false, cell = 32, lastSec = -1, hover = -1, deathCell = -1, wrong = [];
  var parts = [];

  // ---------------------------------------------------------------- DOM
  var wrap = document.createElement('div'); wrap.id = 'ms-wrap'; game.stage.appendChild(wrap);
  var canvas = document.createElement('canvas'); canvas.id = 'ms-board'; wrap.appendChild(canvas);
  var ctx = canvas.getContext('2d');
  var bar = document.createElement('div'); bar.id = 'ms-bar'; wrap.appendChild(bar);
  var SHOVEL = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 4l6 6M17 7l-8 8"/><path d="M9 15l-3-3-3 6 3 3 6-3z"/></svg>';
  var FLAGI = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 21V4"/><path d="M6 4h11l-3 4 3 4H6" fill="currentColor"/></svg>';
  var seg = document.createElement('div'); seg.className = 'ms-seg';
  var digBtn = document.createElement('button'); digBtn.type = 'button'; digBtn.innerHTML = SHOVEL + TXT.dig;
  var flagBtn = document.createElement('button'); flagBtn.type = 'button'; flagBtn.innerHTML = FLAGI + TXT.flag;
  seg.appendChild(digBtn); seg.appendChild(flagBtn); bar.appendChild(seg);
  var leftEl = document.createElement('span'); leftEl.className = 'ms-left'; bar.appendChild(leftEl);
  function setFlagMode(m) { flagMode = m; digBtn.classList.toggle('on', !m); flagBtn.classList.toggle('on', m); }
  digBtn.addEventListener('click', function () { setFlagMode(false); game.audio.sfx('tick'); });
  flagBtn.addEventListener('click', function () { setFlagMode(true); game.audio.sfx('tick'); });
  setFlagMode(false);

  function fmtTime(sec) { sec = Math.max(0, Math.floor(sec)); return Math.floor(sec / 60) + ':' + ('0' + (sec % 60)).slice(-2); }
  function neighbors(i) {
    var out = [], x = i % cols, y = (i / cols) | 0;
    for (var dy = -1; dy <= 1; dy++) for (var dx = -1; dx <= 1; dx++) {
      if (!dx && !dy) continue;
      var nx = x + dx, ny = y + dy;
      if (nx >= 0 && ny >= 0 && nx < cols && ny < rows) out.push(ny * cols + nx);
    }
    return out;
  }
  function updateLeft() { leftEl.innerHTML = '<svg viewBox="0 0 24 24"><circle cx="12" cy="13" r="6" fill="currentColor"/><path d="M12 3v4M12 19v2M3 13h3M18 13h3M5.5 6.5l2 2M16.5 17.5l1.5 1.5M18.5 6.5l-2 2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>' + (lvl.mines - flags); game.setStat('mines', lvl.mines - flags, TXT.mines); }

  // ---------------------------------------------------------------- layout
  function layout() {
    var r = game.stage.getBoundingClientRect(); if (!r.width || !r.height) return;
    var barH = 56;
    cell = Math.floor(Math.min((r.width - 20) / cols, (r.height - 20 - barH) / rows, 52));
    cell = Math.max(14, cell);
    var w = cols * cell, h = rows * cell, d = Math.min(window.devicePixelRatio || 1, 2.5);
    canvas.style.width = w + 'px'; canvas.style.height = h + 'px';
    canvas.width = Math.round(w * d); canvas.height = Math.round(h * d);
    ctx.setTransform(d, 0, 0, d, 0, 0);
  }

  // ---------------------------------------------------------------- core logic
  function reset() {
    lvl = LEVELS[diff];
    var r = game.stage.getBoundingClientRect();
    var portrait = r.height > r.width * 1.05;
    cols = portrait ? Math.min(lvl.a, lvl.b) : Math.max(lvl.a, lvl.b);
    rows = portrait ? Math.max(lvl.a, lvl.b) : Math.min(lvl.a, lvl.b);
    N = cols * rows;
    mine = []; adj = []; open = []; flag = []; revealAt = []; wrong = [];
    for (var i = 0; i < N; i++) { mine.push(false); adj.push(0); open.push(false); flag.push(false); revealAt.push(0); }
    generated = false; opened = 0; flags = 0; ended = false; deathCell = -1; parts = [];
    cursor = ((rows / 2) | 0) * cols + ((cols / 2) | 0); lastSec = -1;
    canvas.classList.remove('blur', 'shake');
    layout();
  }
  function layMines(safe) {
    var banned = {}; banned[safe] = true; neighbors(safe).forEach(function (n) { banned[n] = true; });
    var pool = []; for (var i = 0; i < N; i++) if (!banned[i]) pool.push(i);
    for (var s = pool.length - 1; s > 0; s--) { var j = Math.floor(game.rng() * (s + 1)); var t = pool[s]; pool[s] = pool[j]; pool[j] = t; }
    for (var m = 0; m < lvl.mines; m++) mine[pool[m]] = true;
    for (var k = 0; k < N; k++) adj[k] = neighbors(k).filter(function (n) { return mine[n]; }).length;
    generated = true;
  }
  function liveScore() { return opened * lvl.mult; }

  function reveal(start) {
    if (!generated) layMines(start);
    if (open[start] || flag[start]) return 0;
    if (mine[start]) { explode(start); return -1; }
    var now = performance.now(), queue = [start], dist = {}; dist[start] = 0; var count = 0;
    open[start] = true; revealAt[start] = now;
    while (queue.length) {
      var c = queue.shift(); count++;
      if (adj[c] === 0) neighbors(c).forEach(function (n) {
        if (!open[n] && !flag[n] && !mine[n]) { open[n] = true; dist[n] = dist[c] + 1; revealAt[n] = now + dist[n] * 28; queue.push(n); }
      });
    }
    opened += count;
    return count;
  }
  function dig(i) {
    if (game.state !== 'playing' || ended) return;
    if (open[i]) { chord(i); return; }
    if (flag[i]) return;
    var res = reveal(i);
    if (res > 0) afterReveal(res);
  }
  function chord(i) {
    if (!adj[i]) return;
    var nb = neighbors(i), f = nb.filter(function (n) { return flag[n]; }).length;
    if (f !== adj[i]) { game.audio.sfx('tick'); return; }
    var total = 0;
    for (var k = 0; k < nb.length; k++) {
      var n = nb[k]; if (open[n] || flag[n]) continue;
      var res = reveal(n);
      if (res < 0) return;
      total += res;
    }
    if (total) afterReveal(total);
  }
  function afterReveal(count) {
    if (count > 8) game.audio.sfx('bonus'); else game.audio.tone(380 + Math.min(8, count) * 40, 0.05, 'triangle', 0.09);
    game.setStat('score', liveScore());
    if (opened === N - lvl.mines) win();
  }
  function toggleFlag(i) {
    if (game.state !== 'playing' || ended || open[i]) return;
    flag[i] = !flag[i]; flags += flag[i] ? 1 : -1;
    game.audio.tone(flag[i] ? 760 : 420, 0.06, 'square', 0.08, flag[i] ? 1100 : 300);
    if (flag[i]) burst((i % cols + 0.5) * cell, (((i / cols) | 0) + 0.3) * cell, '#f472b6', 8, 1.5);
    updateLeft();
  }
  function explode(i) {
    ended = true; deathCell = i; open[i] = true; revealAt[i] = performance.now();
    game.audio.sfx('explode');
    canvas.classList.remove('shake'); void canvas.offsetWidth; canvas.classList.add('shake');
    burst((i % cols + 0.5) * cell, (((i / cols) | 0) + 0.5) * cell, '#fb7185', 40, 5);
    burst((i % cols + 0.5) * cell, (((i / cols) | 0) + 0.5) * cell, '#fde047', 24, 3);
    // reveal remaining mines in a ripple from the blast, mark wrong flags
    var ix = i % cols, iy = (i / cols) | 0, now = performance.now();
    for (var k = 0; k < N; k++) {
      if (mine[k] && !flag[k] && k !== i) { open[k] = true; revealAt[k] = now + 120 + Math.hypot(k % cols - ix, ((k / cols) | 0) - iy) * 45; }
      if (flag[k] && !mine[k]) wrong[k] = true;
    }
    var sec = Math.floor(game.elapsed() / 1000);
    later(function () {
      var safeTotal = N - lvl.mines, got = Math.min(opened, safeTotal);
      game.over({ score: liveScore(), win: false, title: TXT.boom, lines: [TXT.opened(got, safeTotal), game.t('time') + ': ' + fmtTime(sec)], evidence: { level: diff, opened: opened, sec: sec } });
      addDiffPicker();
    }, 1500);
  }
  function win() {
    ended = true;
    var sec = Math.floor(game.elapsed() / 1000);
    var bonus = lvl.base + Math.max(0, lvl.target - sec) * lvl.k;
    var score = liveScore() + bonus;
    for (var k = 0; k < N; k++) if (mine[k] && !flag[k]) { flag[k] = true; flags++; }
    updateLeft();
    game.setStat('score', score);
    var w = cols * cell, h = rows * cell;
    for (var b = 0; b < 6; b++) (function (b) { setTimeout(function () { burst(w * (0.15 + 0.14 * b), h * (0.3 + 0.1 * (b % 3)), ['#22d3ee', '#7c5cff', '#f472b6'][b % 3], 30, 4); }, b * 110); })(b);
    later(function () {
      game.over({ score: score, win: true, lines: [TXT.cleared(fmtTime(sec)), TXT.levels[diff]], evidence: { level: diff, sec: sec } });
      addDiffPicker();
    }, 1100);
  }

  function burst(x, y, color, n, speed) {
    for (var i = 0; i < n; i++) {
      var a = Math.random() * Math.PI * 2, s = (0.4 + Math.random()) * speed;
      parts.push({ x: x, y: y, vx: Math.cos(a) * s, vy: Math.sin(a) * s - speed * 0.3, life: 0.6 + Math.random() * 0.6, size: 1.5 + Math.random() * 3, color: color });
    }
  }

  function addDiffPicker() {
    var ov = game._overlay; if (!ov) return;
    var card = ov.querySelector('.ng-card'); var acts = card.querySelector('.ng-actions');
    var w = document.createElement('div'); w.className = 'xx-diff';
    var lab = document.createElement('div'); lab.className = 'xx-diff-label'; lab.textContent = TXT.difficulty; w.appendChild(lab);
    var row = document.createElement('div'); row.className = 'xx-diff-row'; w.appendChild(row);
    TXT.levels.forEach(function (name, i) {
      var b = document.createElement('button'); b.type = 'button'; b.className = 'xx-chip' + (i === diff ? ' on' : ''); b.textContent = name;
      b.addEventListener('click', function () {
        diff = i; game.store.set('mine-sweeper.diff', i); game.audio.sfx('click');
        Array.prototype.forEach.call(row.children, function (c, k) { c.classList.toggle('on', k === i); });
      });
      row.appendChild(b);
    });
    card.insertBefore(w, acts);
  }

  // ---------------------------------------------------------------- input
  function cellAt(e) {
    var r = canvas.getBoundingClientRect();
    var x = Math.floor((e.clientX - r.left) / cell), y = Math.floor((e.clientY - r.top) / cell);
    if (x < 0 || y < 0 || x >= cols || y >= rows) return -1;
    return y * cols + x;
  }
  var press = null;
  canvas.addEventListener('contextmenu', function (e) { e.preventDefault(); });
  canvas.addEventListener('pointerdown', function (e) {
    e.preventDefault(); game.audio.ensure();
    if (game.state !== 'playing') return;
    var i = cellAt(e); if (i < 0) return;
    kb = false;
    if (e.button === 2) { toggleFlag(i); press = null; return; }
    if (e.button !== 0) return;
    press = { i: i, x: e.clientX, y: e.clientY, long: false, id: e.pointerId, timer: null };
    if (e.pointerType !== 'mouse') {
      var p = press;
      p.timer = setTimeout(function () { if (press === p) { p.long = true; if (open[i]) chord(i); else toggleFlag(i); if (navigator.vibrate) try { navigator.vibrate(25); } catch (er) { /* ignore */ } } }, 380);
    }
  });
  canvas.addEventListener('pointermove', function (e) {
    var i = cellAt(e); hover = e.pointerType === 'mouse' ? i : -1;
    if (press && Math.hypot(e.clientX - press.x, e.clientY - press.y) > 12) { clearTimeout(press.timer); press = null; }
  });
  canvas.addEventListener('pointerleave', function () { hover = -1; });
  canvas.addEventListener('pointercancel', function () { if (press) clearTimeout(press.timer); press = null; });
  canvas.addEventListener('pointerup', function (e) {
    if (!press) return;
    var p = press; press = null; clearTimeout(p.timer);
    if (p.long || game.state !== 'playing') return;
    var i = p.i;
    if (flagMode && !open[i]) toggleFlag(i); else dig(i);
  });

  game.on('keydown', function (e) {
    if (game.state !== 'playing' || ended) return;
    var x = cursor % cols, y = (cursor / cols) | 0, k = e.key;
    if (k === 'ArrowLeft' || k === 'a' || k === 'A') x = Math.max(0, x - 1);
    else if (k === 'ArrowRight' || k === 'd' || k === 'D') x = Math.min(cols - 1, x + 1);
    else if (k === 'ArrowUp' || k === 'w' || k === 'W') y = Math.max(0, y - 1);
    else if (k === 'ArrowDown' || k === 's' || k === 'S') y = Math.min(rows - 1, y + 1);
    else if (k === ' ' || k === 'Enter') { if (kb) dig(cursor); kb = true; return; }
    else if (k === 'f' || k === 'F' || k === 'x' || k === 'X') { if (kb) toggleFlag(cursor); kb = true; return; }
    else return;
    if (kb) cursor = y * cols + x;
    kb = true;
  });

  // ---------------------------------------------------------------- rendering
  function rr(x, y, w, h, r) { ctx.beginPath(); ctx.moveTo(x + r, y); ctx.arcTo(x + w, y, x + w, y + h, r); ctx.arcTo(x + w, y + h, x, y + h, r); ctx.arcTo(x, y + h, x, y, r); ctx.arcTo(x, y, x + w, y, r); ctx.closePath(); }
  function drawFlag(cx, cy, s, alpha) {
    ctx.globalAlpha = alpha;
    ctx.strokeStyle = '#e2e8f0'; ctx.lineWidth = Math.max(1.5, s * 0.07); ctx.lineCap = 'round';
    ctx.beginPath(); ctx.moveTo(cx - s * 0.18, cy + s * 0.3); ctx.lineTo(cx - s * 0.18, cy - s * 0.3); ctx.stroke();
    ctx.fillStyle = '#f472b6'; ctx.shadowColor = '#f472b6'; ctx.shadowBlur = s * 0.4;
    ctx.beginPath(); ctx.moveTo(cx - s * 0.16, cy - s * 0.3); ctx.lineTo(cx + s * 0.3, cy - s * 0.14); ctx.lineTo(cx - s * 0.16, cy + s * 0.02); ctx.closePath(); ctx.fill();
    ctx.shadowBlur = 0;
    ctx.fillStyle = 'rgba(226,232,240,.5)'; ctx.fillRect(cx - s * 0.32, cy + s * 0.28, s * 0.3, s * 0.06);
    ctx.globalAlpha = 1;
  }
  function drawMine(cx, cy, s, hot) {
    var rad = s * 0.22;
    ctx.strokeStyle = hot ? '#fecdd3' : '#cbd5e1'; ctx.lineWidth = Math.max(1.2, s * 0.06); ctx.lineCap = 'round';
    for (var a = 0; a < 8; a++) {
      var ang = a * Math.PI / 4;
      ctx.beginPath(); ctx.moveTo(cx + Math.cos(ang) * rad * 0.6, cy + Math.sin(ang) * rad * 0.6); ctx.lineTo(cx + Math.cos(ang) * rad * 1.55, cy + Math.sin(ang) * rad * 1.55); ctx.stroke();
    }
    var g = ctx.createRadialGradient(cx - rad * 0.4, cy - rad * 0.4, rad * 0.1, cx, cy, rad);
    g.addColorStop(0, hot ? '#fda4af' : '#94a3b8'); g.addColorStop(1, hot ? '#9f1239' : '#1e293b');
    ctx.fillStyle = g; ctx.shadowColor = hot ? '#fb7185' : '#f472b6'; ctx.shadowBlur = s * 0.35;
    ctx.beginPath(); ctx.arc(cx, cy, rad, 0, Math.PI * 2); ctx.fill(); ctx.shadowBlur = 0;
  }
  function render(dt) {
    var now = performance.now();
    var w = cols * cell, h = rows * cell;
    ctx.clearRect(0, 0, w, h);
    ctx.fillStyle = '#0d1020'; ctx.fillRect(0, 0, w, h);
    var gap = Math.max(1, cell * 0.06), rad = Math.max(2, cell * 0.16);
    ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    ctx.font = '800 ' + Math.round(cell * 0.56) + 'px system-ui, sans-serif';
    for (var i = 0; i < N; i++) {
      var x = (i % cols) * cell, y = ((i / cols) | 0) * cell, cx = x + cell / 2, cy = y + cell / 2;
      var t = open[i] ? Math.max(0, Math.min(1, (now - revealAt[i]) / 160)) : 0;
      if (open[i] && t > 0) {
        // opened tile
        ctx.fillStyle = i === deathCell ? '#5b1023' : (mine[i] ? '#2a1222' : '#141729');
        rr(x + gap / 2, y + gap / 2, cell - gap, cell - gap, rad * 0.7); ctx.fill();
        var sc = 0.5 + 0.5 * t;
        if (mine[i]) { drawMine(cx, cy, cell * sc, i === deathCell); }
        else if (adj[i]) {
          ctx.globalAlpha = t; ctx.fillStyle = NUMC[adj[i]]; ctx.shadowColor = NUMC[adj[i]]; ctx.shadowBlur = cell * 0.3;
          ctx.fillText(String(adj[i]), cx, cy + cell * 0.03); ctx.shadowBlur = 0; ctx.globalAlpha = 1;
        }
        if (t < 1) { ctx.globalAlpha = 1 - t; ctx.fillStyle = '#7c5cff'; rr(x + gap / 2, y + gap / 2, cell - gap, cell - gap, rad * 0.7); ctx.fill(); ctx.globalAlpha = 1; }
      }
      if (!open[i] || t === 0) {
        var gr = ctx.createLinearGradient(x, y, x, y + cell);
        var hl = i === hover && !ended && game.state === 'playing';
        gr.addColorStop(0, hl ? '#3b3478' : '#272c55'); gr.addColorStop(1, hl ? '#2a2560' : '#1a1d3a');
        ctx.fillStyle = gr; rr(x + gap / 2, y + gap / 2, cell - gap, cell - gap, rad); ctx.fill();
        ctx.fillStyle = 'rgba(255,255,255,.07)'; rr(x + gap / 2 + 1, y + gap / 2 + 1, cell - gap - 2, (cell - gap) * 0.35, rad * 0.8); ctx.fill();
        if (flag[i]) drawFlag(cx, cy, cell, 1);
        if (wrong[i]) {
          ctx.strokeStyle = '#fb7185'; ctx.lineWidth = Math.max(2, cell * 0.08);
          ctx.beginPath(); ctx.moveTo(x + cell * 0.25, y + cell * 0.25); ctx.lineTo(x + cell * 0.75, y + cell * 0.75); ctx.moveTo(x + cell * 0.75, y + cell * 0.25); ctx.lineTo(x + cell * 0.25, y + cell * 0.75); ctx.stroke();
        }
      }
    }
    if (kb && game.state === 'playing' && !ended) {
      var kx = (cursor % cols) * cell, ky = ((cursor / cols) | 0) * cell;
      ctx.strokeStyle = '#22d3ee'; ctx.lineWidth = 2.5; ctx.shadowColor = '#22d3ee'; ctx.shadowBlur = 10;
      rr(kx + 1.5, ky + 1.5, cell - 3, cell - 3, rad); ctx.stroke(); ctx.shadowBlur = 0;
    }
    // particles
    var step = Math.min(0.05, dt || 0.016) * 60;
    for (var p = parts.length - 1; p >= 0; p--) {
      var q = parts[p]; q.life -= (dt || 0.016);
      if (q.life <= 0) { parts.splice(p, 1); continue; }
      q.x += q.vx * step; q.y += q.vy * step; q.vy += 0.12 * step;
      ctx.globalAlpha = Math.min(1, q.life * 1.6); ctx.fillStyle = q.color;
      ctx.beginPath(); ctx.arc(q.x, q.y, q.size, 0, Math.PI * 2); ctx.fill();
    }
    ctx.globalAlpha = 1;
    if (game.state === 'playing' && !ended) {
      var sec = Math.floor(game.elapsed() / 1000);
      if (sec !== lastSec) { lastSec = sec; game.setStat('time', fmtTime(sec)); }
    }
  }

  // ---------------------------------------------------------------- flow
  game.on('start', function () {
    reset();
    game.setStat('score', 0); updateLeft(); game.setStat('time', '0:00');
  });
  game.on('pause', function () { canvas.classList.add('blur'); });
  game.on('resume', function () { canvas.classList.remove('blur'); });
  game.loop(function () {}, render);
  window.addEventListener('resize', layout);
  if (window.ResizeObserver) new ResizeObserver(layout).observe(game.stage);

  reset();
  game.setStat('score', 0); updateLeft(); game.setStat('time', '0:00');
  game.showMenu();
  addDiffPicker();
  game.ready();
})();
