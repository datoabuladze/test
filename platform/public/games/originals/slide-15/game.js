/* Tile Drift – original sliding tile puzzle for Nebulo.
 * Shuffles are random walks of the empty slot from the solved state (seeded RNG), so every board is solvable.
 * The picture is painted procedurally on a canvas each round – no external images.
 */
(function () {
  'use strict';
  var L = { en: 0, ka: 1, tr: 2, ru: 3 }[NebuloGame.lang] || 0;
  var TXT = {
    tagline: ['Slide the tiles back into place and restore the picture.', 'გადაასრიალე ფილები თავის ადგილზე და აღადგინე სურათი.', 'Kareleri yerlerine kaydır ve resmi yeniden oluştur.', 'Сдвигайте плитки на свои места и соберите картину.'][L],
    how: [
      ['Click / tap a tile next to (or in line with) the gap to slide it.', 'Arrow keys, WASD or swipes push a tile into the gap.', 'Restore the picture – or the numbers – in order.', 'A small dot marks tiles that are already in place.', 'Fewer moves and less time give a higher score.'],
      ['დააწკაპუნე / შეეხე ცარიელ ადგილთან (ან მის ხაზზე) მდებარე ფილას გადასაადგილებლად.', 'ისრები, WASD ან გასრიალება ფილას ცარიელ ადგილზე გადაიტანს.', 'აღადგინე სურათი – ან ციფრები – სწორი რიგით.', 'პატარა წერტილი აღნიშნავს უკვე თავის ადგილზე მდგარ ფილებს.', 'ნაკლები სვლა და დრო მეტ ქულას იძლევა.'],
      ['Boşluğun yanındaki (veya aynı hizadaki) kareye tıkla / dokun ve kaydır.', 'Ok tuşları, WASD veya kaydırma hareketi bir kareyi boşluğa iter.', 'Resmi – ya da sayıları – sırasıyla yeniden oluştur.', 'Küçük bir nokta, zaten yerinde olan kareleri gösterir.', 'Daha az hamle ve süre daha yüksek puan demektir.'],
      ['Нажмите на плитку рядом с пустым местом (или на одной линии с ним), чтобы сдвинуть её.', 'Стрелки, WASD или свайпы двигают плитку в пустое место.', 'Восстановите картину — или числа — по порядку.', 'Точка отмечает плитки, которые уже на своём месте.', 'Меньше ходов и времени — больше очков.'],
    ][L],
    difficulty: ['Board size', 'დაფის ზომა', 'Tahta boyutu', 'Размер поля'][L],
    levels: ['3 × 3', '4 × 4', '5 × 5'],
    image: ['Picture', 'სურათი', 'Resim', 'Картина'][L],
    numbers: ['Numbers', 'ციფრები', 'Sayılar', 'Числа'][L],
    summary: function (m, t) { return ['Moves: ' + m + ' · Time: ' + t, 'სვლები: ' + m + ' · დრო: ' + t, 'Hamle: ' + m + ' · Süre: ' + t, 'Ходы: ' + m + ' · Время: ' + t][L]; },
  };
  var game = NebuloGame.create({ id: 'slide-15', title: 'Tile Drift', tagline: TXT.tagline, howTo: TXT.how });

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

  var SIZES = [3, 4, 5], BASE = [500, 1500, 3500], PAR_MOVES = [80, 220, 480], PAR_SEC = [90, 300, 720];
  var diff = game.store.get('slide-15.diff', 1); if (!(diff >= 0 && diff <= 2)) diff = 1;
  var numMode = !!game.store.get('slide-15.numbers', false);
  var n = 4, board = [], tileEls = [], moves = 0, tileSize = 80, gapPx = 8, kbIdx = -1, lastSec = -1, solved = false, imgUrl = '', finalEl = null, level = diff;

  // ---------------------------------------------------------------- DOM
  var wrap = document.createElement('div'); wrap.id = 'sd-wrap'; game.stage.appendChild(wrap);
  var boardEl = document.createElement('div'); boardEl.id = 'sd-board'; wrap.appendChild(boardEl);
  var bar = document.createElement('div'); bar.id = 'sd-bar'; wrap.appendChild(bar);
  var prev = document.createElement('div'); prev.id = 'sd-prev'; bar.appendChild(prev);
  var seg = document.createElement('div'); seg.className = 'sd-seg'; bar.appendChild(seg);
  var imgBtn = document.createElement('button'); imgBtn.type = 'button'; imgBtn.textContent = TXT.image; seg.appendChild(imgBtn);
  var numBtn = document.createElement('button'); numBtn.type = 'button'; numBtn.textContent = TXT.numbers; seg.appendChild(numBtn);
  function setMode(m) {
    numMode = m; game.store.set('slide-15.numbers', m);
    imgBtn.classList.toggle('on', !m); numBtn.classList.toggle('on', m);
    tileEls.forEach(function (t) { t.classList.toggle('num', m); });
    if (finalEl) finalEl.classList.toggle('num', m);
    prev.style.display = m ? 'none' : '';
  }
  imgBtn.addEventListener('click', function () { setMode(false); game.audio.sfx('tick'); });
  numBtn.addEventListener('click', function () { setMode(true); game.audio.sfx('tick'); });

  // particles
  var fx = (function () {
    var cv = document.createElement('canvas'); cv.className = 'fx'; game.stage.appendChild(cv);
    var ctx = cv.getContext('2d'); var parts = []; var running = false;
    function size() { var r = game.stage.getBoundingClientRect(); var d = Math.min(window.devicePixelRatio || 1, 2); cv.width = Math.round(r.width * d); cv.height = Math.round(r.height * d); cv.style.width = r.width + 'px'; cv.style.height = r.height + 'px'; ctx.setTransform(d, 0, 0, d, 0, 0); }
    function tick() {
      var r = game.stage.getBoundingClientRect(); ctx.clearRect(0, 0, r.width, r.height);
      for (var i = parts.length - 1; i >= 0; i--) { var p = parts[i]; p.life -= 1 / 60; if (p.life <= 0) { parts.splice(i, 1); continue; } p.x += p.vx; p.y += p.vy; p.vy += 0.1; ctx.globalAlpha = Math.min(1, p.life * 1.5); ctx.fillStyle = p.color; ctx.beginPath(); ctx.arc(p.x, p.y, p.size * p.life, 0, 6.283); ctx.fill(); }
      ctx.globalAlpha = 1; if (parts.length) requestAnimationFrame(tick); else running = false;
    }
    window.addEventListener('resize', size); size();
    return { size: size, burst: function (x, y, c, k) { for (var i = 0; i < k; i++) { var a = Math.random() * 6.283, s = 1 + Math.random() * 4.5; parts.push({ x: x, y: y, vx: Math.cos(a) * s, vy: Math.sin(a) * s - 1.5, size: 2 + Math.random() * 3, life: 0.7 + Math.random() * 0.6, color: c }); } if (!running) { running = true; requestAnimationFrame(tick); } } };
  })();

  // ---------------------------------------------------------------- procedural picture
  function paint(rng) {
    var S = 720, cv = document.createElement('canvas'); cv.width = S; cv.height = S; var c = cv.getContext('2d');
    function R(a, b) { return a + rng() * (b - a); }
    var PAL = [['#0b0d17', '#3b1d5e', '#f472b6', '#fde047'], ['#04131f', '#0e4a6b', '#22d3ee', '#a7f3d0'], ['#120a1f', '#5b1a4a', '#fb923c', '#fde68a'], ['#0b0d17', '#24135e', '#7c5cff', '#f0abfc']];
    var p = PAL[Math.floor(rng() * PAL.length)];
    var theme = Math.floor(rng() * 3);
    var g = c.createLinearGradient(0, 0, 0, S); g.addColorStop(0, p[0]); g.addColorStop(0.62, p[1]); g.addColorStop(1, p[0]);
    c.fillStyle = g; c.fillRect(0, 0, S, S);
    for (var s = 0; s < 140; s++) { c.globalAlpha = R(0.3, 1); c.fillStyle = '#fff'; c.beginPath(); c.arc(R(0, S), R(0, S * 0.6), R(0.6, 2.2), 0, 6.283); c.fill(); }
    c.globalAlpha = 1;
    if (theme === 0) {
      // neon sunset over mountains and a grid plain
      var sx = R(220, 500), sy = R(230, 300), sr = R(120, 170);
      var sg = c.createLinearGradient(0, sy - sr, 0, sy + sr); sg.addColorStop(0, p[3]); sg.addColorStop(1, p[2]);
      c.save(); c.shadowColor = p[2]; c.shadowBlur = 60; c.fillStyle = sg; c.beginPath(); c.arc(sx, sy, sr, 0, 6.283); c.fill(); c.restore();
      c.fillStyle = p[1]; for (var b = 0; b < 6; b++) c.fillRect(sx - sr, sy + 20 + b * 22, sr * 2, 4 + b * 2.2);
      for (var layer = 0; layer < 3; layer++) {
        c.fillStyle = ['#1c1240', '#140d30', '#0d0a22'][layer];
        c.beginPath(); c.moveTo(0, S); var y0 = 380 + layer * 40; c.lineTo(0, y0);
        for (var x = 0; x <= S; x += 40) c.lineTo(x, y0 - R(10, 110 - layer * 25));
        c.lineTo(S, S); c.closePath(); c.fill();
      }
      var hz = 500; c.fillStyle = p[0]; c.fillRect(0, hz, S, S - hz);
      c.strokeStyle = p[2]; c.lineWidth = 2; c.shadowColor = p[2]; c.shadowBlur = 10;
      for (var gx = -12; gx <= 12; gx++) { c.beginPath(); c.moveTo(S / 2 + gx * 18, hz); c.lineTo(S / 2 + gx * 120, S); c.stroke(); }
      for (var gy = 0; gy < 8; gy++) { var yy = hz + Math.pow(gy / 7, 1.8) * (S - hz); c.beginPath(); c.moveTo(0, yy); c.lineTo(S, yy); c.stroke(); }
      c.shadowBlur = 0;
    } else if (theme === 1) {
      // nebula with a ringed planet and moon
      for (var k = 0; k < 7; k++) {
        var nx = R(0, S), ny = R(0, S), nr = R(120, 300), ng = c.createRadialGradient(nx, ny, 0, nx, ny, nr);
        ng.addColorStop(0, [p[2], p[3], '#22d3ee', '#7c5cff'][k % 4] + '88'); ng.addColorStop(1, 'rgba(0,0,0,0)');
        c.fillStyle = ng; c.fillRect(0, 0, S, S);
      }
      var px = R(260, 460), py = R(300, 420), pr = R(110, 150);
      var pg = c.createRadialGradient(px - pr * 0.4, py - pr * 0.4, pr * 0.1, px, py, pr); pg.addColorStop(0, p[3]); pg.addColorStop(0.5, p[2]); pg.addColorStop(1, p[1]);
      c.save(); c.translate(px, py); c.rotate(-0.35);
      c.strokeStyle = p[3]; c.globalAlpha = 0.7; c.lineWidth = 12; c.beginPath(); c.ellipse(0, 0, pr * 1.9, pr * 0.42, 0, Math.PI, 2 * Math.PI); c.stroke();
      c.globalAlpha = 1; c.fillStyle = pg; c.beginPath(); c.arc(0, 0, pr, 0, 6.283); c.fill();
      c.fillStyle = 'rgba(255,255,255,.12)'; for (var bnd = -3; bnd <= 3; bnd++) c.fillRect(-pr, bnd * pr * 0.25, pr * 2, pr * 0.07);
      c.strokeStyle = p[3]; c.globalAlpha = 0.85; c.lineWidth = 12; c.beginPath(); c.ellipse(0, 0, pr * 1.9, pr * 0.42, 0, 0, Math.PI); c.stroke();
      c.restore(); c.globalAlpha = 1;
      c.fillStyle = '#e2e8f0'; c.beginPath(); c.arc(R(80, 200), R(100, 200), R(28, 44), 0, 6.283); c.fill();
      c.fillStyle = 'rgba(0,0,0,.25)'; c.beginPath(); c.arc(140, 150, 14, 0, 6.283); c.fill();
    } else {
      // concentric neon geometry
      var cx = S / 2 + R(-60, 60), cy = S / 2 + R(-60, 60);
      for (var ring = 12; ring > 0; ring--) {
        c.fillStyle = ring % 2 ? p[1] : p[0]; c.beginPath(); c.arc(cx, cy, ring * 34, 0, 6.283); c.fill();
      }
      var sides = 3 + Math.floor(rng() * 4);
      for (var poly = 0; poly < 5; poly++) {
        c.save(); c.translate(cx, cy); c.rotate(poly * 0.32 + R(0, 1));
        c.strokeStyle = [p[2], p[3], '#22d3ee', '#7c5cff', '#f472b6'][poly]; c.lineWidth = 9; c.shadowColor = c.strokeStyle; c.shadowBlur = 18;
        c.beginPath(); for (var v = 0; v <= sides; v++) { var a = v / sides * 6.283, rr = 70 + poly * 55; if (v) c.lineTo(Math.cos(a) * rr, Math.sin(a) * rr); else c.moveTo(Math.cos(a) * rr, Math.sin(a) * rr); }
        c.stroke(); c.restore();
      }
      c.fillStyle = p[3]; c.shadowColor = p[3]; c.shadowBlur = 30; c.beginPath(); c.arc(cx, cy, 34, 0, 6.283); c.fill(); c.shadowBlur = 0;
      for (var d = 0; d < 26; d++) { c.fillStyle = [p[2], p[3], '#22d3ee'][d % 3]; c.beginPath(); c.arc(R(0, S), R(0, S), R(4, 12), 0, 6.283); c.fill(); }
    }
    // soft vignette
    var vg = c.createRadialGradient(S / 2, S / 2, S * 0.3, S / 2, S / 2, S * 0.75); vg.addColorStop(0, 'rgba(0,0,0,0)'); vg.addColorStop(1, 'rgba(0,0,0,.45)');
    c.fillStyle = vg; c.fillRect(0, 0, S, S);
    return cv.toDataURL('image/jpeg', 0.88);
  }

  // ---------------------------------------------------------------- layout & render
  function layout() {
    var r = game.stage.getBoundingClientRect(); if (!r.width || !r.height) return;
    var S = Math.floor(Math.min(r.width - 24, r.height - 24 - 56 - 12, 720));
    S = Math.max(180, S);
    gapPx = Math.max(4, Math.round(S * 0.014));
    tileSize = (S - gapPx * (n + 1)) / n;
    boardEl.style.width = S + 'px'; boardEl.style.height = S + 'px';
    var full = n * tileSize;
    tileEls.forEach(function (t, id) {
      t.style.width = tileSize + 'px'; t.style.height = tileSize + 'px';
      t.style.fontSize = (tileSize * 0.42) + 'px';
      var hr = (id / n) | 0, hc = id % n;
      t.style.backgroundSize = full + 'px ' + full + 'px';
      t.style.backgroundPosition = (-hc * tileSize) + 'px ' + (-hr * tileSize) + 'px';
    });
    if (finalEl) {
      var last = n * n - 1;
      finalEl.style.width = tileSize + 'px'; finalEl.style.height = tileSize + 'px'; finalEl.style.fontSize = (tileSize * 0.42) + 'px';
      finalEl.style.backgroundSize = full + 'px ' + full + 'px';
      finalEl.style.backgroundPosition = (-(last % n) * tileSize) + 'px ' + (-((last / n) | 0) * tileSize) + 'px';
      finalEl.style.transform = posOf(last);
    }
    place(true);
    fx.size();
  }
  function posOf(cell) { return 'translate(' + (gapPx + (cell % n) * (tileSize + gapPx)) + 'px,' + (gapPx + ((cell / n) | 0) * (tileSize + gapPx)) + 'px)'; }
  function place(instant) {
    board.forEach(function (id, cell) {
      if (id < 0) return;
      var t = tileEls[id];
      if (instant) t.style.transition = 'none';
      t.style.transform = posOf(cell);
      t.classList.toggle('ok', id === cell);
      t.classList.toggle('kb', cell === kbIdx);
      if (instant) { void t.offsetWidth; t.style.transition = ''; }
    });
  }
  function tileColor(id) { var h = 190 + (id / (n * n - 1)) * 140; return 'linear-gradient(145deg, hsl(' + h + ' 85% 58%), hsl(' + (h + 25) + ' 70% 38%))'; }
  function makeTile(id) {
    var t = document.createElement('div'); t.className = 'sd-tile' + (numMode ? ' num' : '');
    t.innerHTML = '<span class="sd-badge">' + (id + 1) + '</span><span class="sd-big">' + (id + 1) + '</span>';
    t.style.backgroundColor = '#1e2342';
    t.style.backgroundImage = 'url(' + imgUrl + ')';
    t.style.setProperty('--numbg', tileColor(id));
    return t;
  }

  // ---------------------------------------------------------------- logic
  function blank() { return board.indexOf(-1); }
  function isSolved() { for (var i = 0; i < n * n - 1; i++) if (board[i] !== i) return false; return true; }
  function slideFrom(cell, silent) {
    var b = blank();
    var br = (b / n) | 0, bc = b % n, cr = (cell / n) | 0, cc = cell % n;
    if (cell === b || (br !== cr && bc !== cc)) return 0;
    var step = br === cr ? (cc > bc ? 1 : -1) : (cr > br ? n : -n);
    var count = 0;
    while (b !== cell) { var nx = b + step; board[b] = board[nx]; board[nx] = -1; b = nx; count++; }
    if (!silent) {
      moves += count; game.setStat('moves', moves);
      game.audio.tone(300 + Math.min(count, 4) * 60, 0.05, 'triangle', 0.1);
      place(false);
      updateScore();
      if (isSolved()) win();
    }
    return count;
  }
  function pushDir(dir) {
    // dir = direction the tile moves; the tile comes from the opposite side of the gap
    var b = blank(), br = (b / n) | 0, bc = b % n, src = -1;
    if (dir === 'left' && bc < n - 1) src = b + 1;
    else if (dir === 'right' && bc > 0) src = b - 1;
    else if (dir === 'up' && br < n - 1) src = b + n;
    else if (dir === 'down' && br > 0) src = b - n;
    if (src >= 0) slideFrom(src); else game.audio.sfx('tick');
  }
  function shuffle() {
    var b = n * n - 1, last = -1, steps = n * n * n * 12;
    for (var tries = 0; tries < 10; tries++) {
      for (var s = 0; s < steps; s++) {
        var br = (b / n) | 0, bc = b % n, opts = [];
        if (bc > 0) opts.push(b - 1); if (bc < n - 1) opts.push(b + 1); if (br > 0) opts.push(b - n); if (br < n - 1) opts.push(b + n);
        opts = opts.filter(function (o) { return o !== last; });
        var nx = opts[Math.floor(game.rng() * opts.length)];
        board[b] = board[nx]; board[nx] = -1; last = b; b = nx;
      }
      var misplaced = 0; for (var i = 0; i < n * n - 1; i++) if (board[i] !== i) misplaced++;
      if (misplaced >= Math.ceil(n * n * 0.6)) break;
    }
  }
  function scoreNow(sec) { return BASE[level] + Math.max(0, PAR_MOVES[level] - moves) * 5 + Math.max(0, PAR_SEC[level] - sec) * 3; }
  function updateScore() { if (!solved) game.setStat('score', scoreNow(Math.floor(game.elapsed() / 1000))); }
  function fmtTime(sec) { sec = Math.max(0, Math.floor(sec)); return Math.floor(sec / 60) + ':' + ('0' + (sec % 60)).slice(-2); }

  function win() {
    solved = true;
    var sec = Math.floor(game.elapsed() / 1000), score = scoreNow(sec);
    game.setStat('score', score);
    kbIdx = -1; place(false);
    var last = n * n - 1;
    finalEl = makeTile(last); finalEl.classList.add('final'); finalEl.classList.toggle('num', numMode); boardEl.appendChild(finalEl);
    layout();
    boardEl.classList.add('won');
    game.audio.sfx('bonus');
    var br = boardEl.getBoundingClientRect(), sr = game.stage.getBoundingClientRect();
    for (var k = 0; k < 6; k++) (function (k) { setTimeout(function () { fx.burst(br.left - sr.left + br.width * (k % 3 + 0.5) / 3, br.top - sr.top + br.height * (k < 3 ? 0.25 : 0.75), ['#22d3ee', '#7c5cff', '#f472b6'][k % 3], 26); }, k * 90); })(k);
    later(function () {
      game.over({ score: score, win: true, lines: [TXT.levels[level] + ' · ' + TXT.summary(moves, fmtTime(sec))], evidence: { size: n, moves: moves, sec: sec } });
      addDiffPicker();
    }, 1200);
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
        diff = i; game.store.set('slide-15.diff', i); game.audio.sfx('click');
        Array.prototype.forEach.call(row.children, function (c, k) { c.classList.toggle('on', k === i); });
      });
      row.appendChild(b);
    });
    card.insertBefore(w, acts);
  }

  // ---------------------------------------------------------------- input
  var down = null;
  boardEl.addEventListener('pointerdown', function (e) { e.preventDefault(); game.audio.ensure(); down = { x: e.clientX, y: e.clientY }; });
  boardEl.addEventListener('pointercancel', function () { down = null; });
  boardEl.addEventListener('pointerup', function (e) {
    if (!down || game.state !== 'playing' || solved) { down = null; return; }
    var dx = e.clientX - down.x, dy = e.clientY - down.y, sx = down.x, sy = down.y; down = null;
    kbIdx = -1;
    if (Math.max(Math.abs(dx), Math.abs(dy)) >= 22) { pushDir(Math.abs(dx) > Math.abs(dy) ? (dx > 0 ? 'right' : 'left') : (dy > 0 ? 'down' : 'up')); return; }
    var r = boardEl.getBoundingClientRect();
    var c = Math.floor((sx - r.left - gapPx / 2) / (tileSize + gapPx)), rr = Math.floor((sy - r.top - gapPx / 2) / (tileSize + gapPx));
    if (c < 0 || rr < 0 || c >= n || rr >= n) return;
    if (!slideFrom(rr * n + c)) { game.audio.sfx('tick'); place(false); }
  });
  game.on('keydown', function (e) {
    if (game.state !== 'playing' || solved) return;
    var map = { ArrowLeft: 'left', ArrowRight: 'right', ArrowUp: 'up', ArrowDown: 'down', a: 'left', d: 'right', w: 'up', s: 'down', A: 'left', D: 'right', W: 'up', S: 'down' };
    if (map[e.key]) { pushDir(map[e.key]); return; }
    if (e.key === 'm' || e.key === 'M') setMode(!numMode);
  });

  // ---------------------------------------------------------------- flow
  function newGame() {
    level = diff; n = SIZES[level]; solved = false; moves = 0; lastSec = -1; kbIdx = -1;
    imgUrl = paint(game.rng);
    prev.style.backgroundImage = 'url(' + imgUrl + ')';
    boardEl.innerHTML = ''; boardEl.classList.remove('blur', 'won'); finalEl = null;
    board = []; tileEls = [];
    for (var i = 0; i < n * n; i++) board.push(i < n * n - 1 ? i : -1);
    for (var id = 0; id < n * n - 1; id++) { var t = makeTile(id); boardEl.appendChild(t); tileEls.push(t); }
    shuffle();
    game.setStat('score', scoreNow(0)); game.setStat('moves', 0); game.setStat('time', '0:00');
    setMode(numMode);
    layout();
  }
  game.on('start', newGame);
  game.on('pause', function () { boardEl.classList.add('blur'); });
  game.on('resume', function () { boardEl.classList.remove('blur'); });
  game.loop(function () {}, function () {
    if (game.state !== 'playing' || solved) return;
    var sec = Math.floor(game.elapsed() / 1000);
    if (sec !== lastSec) { lastSec = sec; game.setStat('time', fmtTime(sec)); updateScore(); }
  });
  window.addEventListener('resize', layout);
  if (window.ResizeObserver) new ResizeObserver(layout).observe(game.stage);

  // menu backdrop: a solved demo board
  (function () {
    n = SIZES[diff]; imgUrl = paint(game.mulberry32(77031));
    prev.style.backgroundImage = 'url(' + imgUrl + ')';
    for (var i = 0; i < n * n; i++) board.push(i < n * n - 1 ? i : -1);
    for (var id = 0; id < n * n - 1; id++) { var t = makeTile(id); boardEl.appendChild(t); tileEls.push(t); }
    setMode(numMode); layout();
  })();
  game.showMenu();
  addDiffPicker();
  game.ready();
})();
