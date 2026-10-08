/* Block Cascade – original falling-block line-clear puzzle for Nebulo.
 * Seven four-cell shapes, rotation with wall kicks, hold slot, 3-piece preview, ghost piece,
 * soft/hard drop, lock delay, 7-bag randomizer (seeded via game.rng) and level-based gravity.
 */
(function () {
  'use strict';
  var L = { en: 0, ka: 1, tr: 2, ru: 3 }[NebuloGame.lang] || 0;
  var TXT = {
    tagline: ['Stack falling blocks, complete rows and keep the well from overflowing.', 'დააწყვე ჩამოვარდნილი ბლოკები, შეავსე რიგები და არ გაავსო ჭა.', 'Düşen blokları diz, sıraları tamamla ve kuyunun taşmasına izin verme.', 'Укладывайте падающие блоки, заполняйте ряды и не дайте колодцу переполниться.'][L],
    how: [
      ['← → move, ↑ / X rotate, Z rotate back, ↓ soft drop, Space hard drop, C hold.', 'On touch use the buttons; tap the well to rotate.', 'Fill a whole row to clear it – clearing 4 at once scores the most.', 'Every 10 lines the level rises and blocks fall faster.', 'The game ends when a new block cannot enter the well.'],
      ['← → გადაადგილება, ↑ / X შემობრუნება, Z უკან შემობრუნება, ↓ ნელი ჩამოშვება, Space სწრაფი ჩაგდება, C შენახვა.', 'სენსორზე გამოიყენე ღილაკები; შეეხე ჭას შემოსაბრუნებლად.', 'შეავსე მთელი რიგი, რომ გაქრეს — 4 რიგი ერთად ყველაზე მეტ ქულას იძლევა.', 'ყოველ 10 ხაზზე დონე იზრდება და ბლოკები უფრო სწრაფად ვარდება.', 'თამაში სრულდება, როცა ახალი ბლოკი ჭაში ვეღარ ეტევა.'],
      ['← → hareket, ↑ / X döndür, Z geri döndür, ↓ yavaş indir, Space hızlı bırak, C sakla.', 'Dokunmatikte düğmeleri kullan; döndürmek için kuyuya dokun.', 'Silmek için bir sırayı tamamen doldur – aynı anda 4 sıra en çok puanı verir.', 'Her 10 satırda seviye artar ve bloklar daha hızlı düşer.', 'Yeni blok kuyuya giremediğinde oyun biter.'],
      ['← → сдвиг, ↑ / X поворот, Z обратный поворот, ↓ ускорить падение, Пробел — сбросить, C — отложить.', 'На сенсорном экране — кнопки; коснитесь колодца для поворота.', 'Заполните ряд целиком, чтобы убрать его, — 4 ряда сразу дают больше всего очков.', 'Каждые 10 линий уровень растёт, и блоки падают быстрее.', 'Игра заканчивается, когда новый блок не помещается в колодец.'],
    ][L],
    hold: ['HOLD', 'შენახვა', 'SAKLA', 'ЗАПАС'][L],
    next: ['NEXT', 'შემდეგი', 'SIRADAKİ', 'ДАЛЕЕ'][L],
    lines: ['Lines', 'ხაზები', 'Satır', 'Линии'][L],
    level: ['LEVEL', 'დონე', 'SEVİYE', 'УРОВЕНЬ'][L],
    linesU: ['LINES', 'ხაზები', 'SATIR', 'ЛИНИИ'][L],
    clearNames: [
      ['', 'SINGLE', 'DOUBLE', 'TRIPLE', 'CASCADE!'],
      ['', 'ერთი', 'ორი', 'სამი', 'კასკადი!'],
      ['', 'TEKLİ', 'İKİLİ', 'ÜÇLÜ', 'ÇAĞLAYAN!'],
      ['', 'ОДНА', 'ДВЕ', 'ТРИ', 'КАСКАД!'],
    ][L],
    combo: ['COMBO', 'კომბო', 'KOMBO', 'КОМБО'][L],
    levelUp: ['LEVEL UP', 'ახალი დონე', 'SEVİYE ATLADIN', 'НОВЫЙ УРОВЕНЬ'][L],
  };

  var game = NebuloGame.create({ id: 'block-cascade', title: 'Block Cascade', tagline: TXT.tagline, howTo: TXT.how });
  var COLS = 10, ROWS = 22, HIDDEN = 2, CELL = 28;
  var W = 520, H = 620, BX = 120, BY = 30;
  var view = game.canvas(W, H);
  var ctx = view.ctx;

  var SHAPES = {
    I: [[0, 0, 0, 0], [1, 1, 1, 1], [0, 0, 0, 0], [0, 0, 0, 0]],
    O: [[1, 1], [1, 1]],
    T: [[0, 1, 0], [1, 1, 1], [0, 0, 0]],
    S: [[0, 1, 1], [1, 1, 0], [0, 0, 0]],
    Z: [[1, 1, 0], [0, 1, 1], [0, 0, 0]],
    J: [[1, 0, 0], [1, 1, 1], [0, 0, 0]],
    L: [[0, 0, 1], [1, 1, 1], [0, 0, 0]],
  };
  var COLORS = { I: '#22d3ee', O: '#fbbf24', T: '#a78bfa', S: '#34d399', Z: '#f472b6', J: '#60a5fa', L: '#fb923c' };
  var ORDER = ['I', 'O', 'T', 'S', 'Z', 'J', 'L'];
  // Pre-computed rotation states.
  var ROT = {};
  ORDER.forEach(function (k) {
    var states = [SHAPES[k]];
    for (var r = 1; r < 4; r++) {
      var m = states[r - 1], n = m.length, out = [];
      for (var y = 0; y < n; y++) { out.push([]); for (var x = 0; x < n; x++) out[y].push(m[n - 1 - x][y]); }
      states.push(out);
    }
    ROT[k] = states.map(function (m) {
      var cells = [];
      for (var y = 0; y < m.length; y++) for (var x = 0; x < m.length; x++) if (m[y][x]) cells.push([x, y]);
      return cells;
    });
  });
  // Wall-kick offsets (x right, y up as in the usual tables; converted to y-down when applied).
  var KICK_JLSTZ = {
    '0>1': [[0, 0], [-1, 0], [-1, 1], [0, -2], [-1, -2]], '1>0': [[0, 0], [1, 0], [1, -1], [0, 2], [1, 2]],
    '1>2': [[0, 0], [1, 0], [1, -1], [0, 2], [1, 2]], '2>1': [[0, 0], [-1, 0], [-1, 1], [0, -2], [-1, -2]],
    '2>3': [[0, 0], [1, 0], [1, 1], [0, -2], [1, -2]], '3>2': [[0, 0], [-1, 0], [-1, -1], [0, 2], [-1, 2]],
    '3>0': [[0, 0], [-1, 0], [-1, -1], [0, 2], [-1, 2]], '0>3': [[0, 0], [1, 0], [1, 1], [0, -2], [1, -2]],
  };
  var KICK_I = {
    '0>1': [[0, 0], [-2, 0], [1, 0], [-2, -1], [1, 2]], '1>0': [[0, 0], [2, 0], [-1, 0], [2, 1], [-1, -2]],
    '1>2': [[0, 0], [-1, 0], [2, 0], [-1, 2], [2, -1]], '2>1': [[0, 0], [1, 0], [-2, 0], [1, -2], [-2, 1]],
    '2>3': [[0, 0], [2, 0], [-1, 0], [2, 1], [-1, -2]], '3>2': [[0, 0], [-2, 0], [1, 0], [-2, -1], [1, 2]],
    '3>0': [[0, 0], [1, 0], [-2, 0], [1, -2], [-2, 1]], '0>3': [[0, 0], [-1, 0], [2, 0], [-1, 2], [2, -1]],
  };

  var board, bag, queue, cur, holdK, holdUsed, score, lines, level, combo, b2b, gravAcc, lockT, lockResets,
    clearing, clearT, dasDir, dasT, arrT, particles = [], banners = [], flash = 0, shakeY = 0, softHeld = false, time = 0;

  function emptyBoard() { var b = []; for (var y = 0; y < ROWS; y++) { b.push(new Array(COLS).fill(null)); } return b; }
  function refill() {
    var b = ORDER.slice();
    for (var i = b.length - 1; i > 0; i--) { var j = Math.floor(game.rng() * (i + 1)); var t = b[i]; b[i] = b[j]; b[j] = t; }
    bag = bag.concat(b);
  }
  function takeNext() { while (bag.length < 7) refill(); return bag.shift(); }
  function fits(k, r, px, py) {
    var cells = ROT[k][r];
    for (var i = 0; i < 4; i++) {
      var x = px + cells[i][0], y = py + cells[i][1];
      if (x < 0 || x >= COLS || y >= ROWS) return false;
      if (y >= 0 && board[y][x]) return false;
    }
    return true;
  }
  function spawn(k) {
    var px = k === 'O' ? 4 : 3, py = k === 'I' ? 0 : 0;
    cur = { k: k, r: 0, x: px, y: py };
    lockT = 0; lockResets = 0; gravAcc = 0;
    if (!fits(k, 0, px, py)) { cur = null; endGame(); return false; }
    // Drop one row immediately into view if possible (pieces appear at the top edge).
    if (fits(k, 0, px, py + 1)) cur.y++;
    return true;
  }
  function nextPiece() {
    var k = queue.shift(); queue.push(takeNext());
    holdUsed = false;
    spawn(k);
  }
  function onGround() { return cur && !fits(cur.k, cur.r, cur.x, cur.y + 1); }
  function touched() { if (onGround() && lockResets < 15) { lockT = 0; lockResets++; } }
  function shift(dx) {
    if (!cur || clearing) return false;
    if (fits(cur.k, cur.r, cur.x + dx, cur.y)) { cur.x += dx; game.audio.sfx('move'); touched(); return true; }
    return false;
  }
  function rotate(dir) {
    if (!cur || clearing || cur.k === 'O') { if (cur && cur.k === 'O') game.audio.sfx('tick'); return; }
    var from = cur.r, to = (cur.r + dir + 4) % 4;
    var table = (cur.k === 'I' ? KICK_I : KICK_JLSTZ)[from + '>' + to];
    for (var i = 0; i < table.length; i++) {
      var nx = cur.x + table[i][0], ny = cur.y - table[i][1];
      if (fits(cur.k, to, nx, ny)) { cur.x = nx; cur.y = ny; cur.r = to; game.audio.tone(520 + to * 60, 0.05, 'triangle', 0.08); touched(); return; }
    }
  }
  function ghostY() { var y = cur.y; while (fits(cur.k, cur.r, cur.x, y + 1)) y++; return y; }
  function hardDrop() {
    if (!cur || clearing) return;
    var gy = ghostY(), dist = gy - cur.y;
    // streak particles
    ROT[cur.k][cur.r].forEach(function (c) {
      for (var i = 0; i < 3; i++) particles.push({ x: BX + (cur.x + c[0] + Math.random()) * CELL, y: BY + (gy + c[1] - HIDDEN) * CELL, vx: (Math.random() - 0.5) * 40, vy: -60 - Math.random() * 120, life: 0.4, color: COLORS[cur.k], r: 1.5 });
    });
    cur.y = gy; score += dist * 2; shakeY = 5;
    game.audio.sfx('hit');
    lock();
  }
  function hold() {
    if (!cur || clearing || holdUsed) return;
    var k = cur.k;
    game.audio.sfx('click');
    if (holdK) { var h = holdK; holdK = k; spawn(h); } else { holdK = k; nextPiece(); }
    holdUsed = true;
  }
  function lock() {
    var cells = ROT[cur.k][cur.r], above = true;
    for (var i = 0; i < 4; i++) {
      var x = cur.x + cells[i][0], y = cur.y + cells[i][1];
      if (y >= 0) board[y][x] = cur.k;
      if (y >= HIDDEN) above = false;
    }
    cur = null;
    if (above) { endGame(); return; }
    var full = [];
    for (var y2 = 0; y2 < ROWS; y2++) if (board[y2].every(Boolean)) full.push(y2);
    if (full.length) {
      clearing = full; clearT = 0.32;
      var n = full.length;
      var base = [0, 100, 300, 500, 800][n] * level;
      if (n === 4) { if (b2b) base = Math.floor(base * 1.5); b2b = true; } else b2b = false;
      combo++;
      var gain = base + (combo > 1 ? 50 * (combo - 1) * level : 0);
      score += gain;
      lines += n;
      banners.push({ text: TXT.clearNames[n] + (combo > 1 ? '  ' + TXT.combo + ' ×' + combo : ''), sub: '+' + gain, life: 1.2, color: n === 4 ? '#f472b6' : '#22d3ee' });
      full.forEach(function (row) {
        for (var x = 0; x < COLS; x++) for (var k = 0; k < 3; k++) {
          particles.push({ x: BX + (x + 0.5) * CELL, y: BY + (row - HIDDEN + 0.5) * CELL, vx: (Math.random() - 0.5) * 360, vy: (Math.random() - 0.8) * 260, life: 0.6 + Math.random() * 0.5, color: COLORS[board[row][x]] || '#fff', r: 2 + Math.random() * 2, g: 500 });
        }
      });
      flash = n === 4 ? 0.5 : 0.25;
      if (n === 4) { game.audio.sfx('bonus'); shakeY = 10; } else game.audio.sfx('point');
      var newLevel = Math.floor(lines / 10) + 1;
      if (newLevel > level) { level = newLevel; banners.push({ text: TXT.levelUp, sub: String(level), life: 1.6, color: '#fbbf24', big: true }); setTimeout(function () { game.audio.sfx('win'); }, 200); }
    } else {
      combo = 0;
      game.audio.tone(180, 0.06, 'square', 0.08);
      nextPiece();
    }
    pushStats();
  }
  function pushStats() { game.setStat('score', score); game.setStat('level', level); game.setStat('lines', lines, TXT.lines); }
  function endGame() {
    shakeY = 12;
    for (var y = 0; y < ROWS; y++) for (var x = 0; x < COLS; x++) if (board[y][x]) board[y][x] = 'X';
    pushStats();
    game.over({ score: score, lines: [TXT.lines + ': ' + lines + ' · ' + game.t('level') + ': ' + level] });
  }
  function gravity() { return Math.max(0.0008, Math.pow(0.8 - (level - 1) * 0.007, level - 1)); }

  function reset() {
    board = emptyBoard(); bag = []; queue = [];
    for (var i = 0; i < 3; i++) queue.push(takeNext());
    holdK = null; holdUsed = false; score = 0; lines = 0; level = 1; combo = 0; b2b = false;
    clearing = null; clearT = 0; dasDir = 0; dasT = 0; arrT = 0; particles = []; banners = []; flash = 0; shakeY = 0;
    pushStats();
    nextPiece();
  }

  function update(dt) {
    time += dt;
    if (clearing) {
      clearT -= dt;
      if (clearT <= 0) {
        clearing.forEach(function (row) { board.splice(row, 1); board.unshift(new Array(COLS).fill(null)); });
        clearing = null;
        nextPiece();
      }
      return;
    }
    if (!cur) return;
    // auto-shift
    var l = game.keys.ArrowLeft || game.keys.a || game.keys.A, r = game.keys.ArrowRight || game.keys.d || game.keys.D;
    if (dasDir && !((dasDir < 0 && l) || (dasDir > 0 && r))) dasDir = l ? -1 : r ? 1 : 0;
    if (dasDir) {
      dasT -= dt;
      if (dasT <= 0) { arrT -= dt; while (arrT <= 0) { if (!shift(dasDir)) { arrT = 0.04; break; } arrT += 0.04; } }
    }
    softHeld = !!(game.keys.ArrowDown || game.keys.s || game.keys.S);
    var g = gravity();
    if (softHeld) g = Math.min(g, 0.03);
    if (!onGround()) {
      gravAcc += dt;
      while (gravAcc >= g && cur && fits(cur.k, cur.r, cur.x, cur.y + 1)) {
        gravAcc -= g; cur.y++;
        if (softHeld) score += 1;
      }
      if (softHeld) game.setStat('score', score);
    } else {
      gravAcc = 0;
      lockT += dt;
      if (lockT >= 0.5 || lockResets >= 15 && lockT > 0.05) lock();
    }
  }

  // ---------------------------------------------------------------- rendering
  function rr(x, y, w, h, r) {
    ctx.beginPath(); ctx.moveTo(x + r, y); ctx.arcTo(x + w, y, x + w, y + h, r); ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r); ctx.arcTo(x, y, x + w, y, r); ctx.closePath();
  }
  function cell(x, y, size, k, alpha) {
    var color = k === 'X' ? '#475569' : COLORS[k];
    ctx.globalAlpha = alpha == null ? 1 : alpha;
    var g = ctx.createLinearGradient(x, y, x + size, y + size);
    g.addColorStop(0, color); g.addColorStop(1, shade(color));
    ctx.fillStyle = g; rr(x + 1, y + 1, size - 2, size - 2, size * 0.2); ctx.fill();
    ctx.fillStyle = 'rgba(255,255,255,0.28)'; rr(x + 3, y + 3, size - 6, size * 0.28, size * 0.12); ctx.fill();
    ctx.strokeStyle = 'rgba(255,255,255,0.18)'; ctx.lineWidth = 1; rr(x + 1.5, y + 1.5, size - 3, size - 3, size * 0.2); ctx.stroke();
    ctx.globalAlpha = 1;
  }
  var shadeCache = {};
  function shade(hex) {
    if (shadeCache[hex]) return shadeCache[hex];
    var r = parseInt(hex.slice(1, 3), 16), g = parseInt(hex.slice(3, 5), 16), b = parseInt(hex.slice(5, 7), 16);
    return (shadeCache[hex] = 'rgb(' + Math.round(r * 0.45) + ',' + Math.round(g * 0.45) + ',' + Math.round(b * 0.5 + 20) + ')');
  }
  function mini(k, cx, cy, size) {
    var cells = ROT[k][0], minx = 9, maxx = -1, miny = 9, maxy = -1;
    cells.forEach(function (c) { minx = Math.min(minx, c[0]); maxx = Math.max(maxx, c[0]); miny = Math.min(miny, c[1]); maxy = Math.max(maxy, c[1]); });
    var w = (maxx - minx + 1) * size, h = (maxy - miny + 1) * size;
    cells.forEach(function (c) { cell(cx - w / 2 + (c[0] - minx) * size, cy - h / 2 + (c[1] - miny) * size, size, k, holdUsed && k === holdK && cy < 200 ? 0.4 : 1); });
  }
  function panel(x, y, w, h, label) {
    ctx.fillStyle = 'rgba(22,26,43,0.9)'; rr(x, y, w, h, 12); ctx.fill();
    ctx.strokeStyle = 'rgba(124,92,255,0.35)'; ctx.lineWidth = 1.5; ctx.stroke();
    ctx.fillStyle = '#8b90b8'; ctx.font = '800 12px system-ui, sans-serif'; ctx.textAlign = 'center';
    ctx.fillText(label, x + w / 2, y + 18);
  }

  function render(dt) {
    var live = game.state !== 'paused';
    if (live) {
      for (var i = particles.length - 1; i >= 0; i--) {
        var p = particles[i]; p.life -= dt; p.x += p.vx * dt; p.y += p.vy * dt; if (p.g) p.vy += p.g * dt;
        if (p.life <= 0) particles.splice(i, 1);
      }
      for (var b = banners.length - 1; b >= 0; b--) { banners[b].life -= dt; if (banners[b].life <= 0) banners.splice(b, 1); }
      flash = Math.max(0, flash - dt); shakeY = Math.max(0, shakeY - dt * 40);
      if (game.state !== 'playing') time += dt;
    }
    ctx.fillStyle = '#0b0d17'; ctx.fillRect(0, 0, W, H);
    var bgG = ctx.createRadialGradient(W / 2, H * 0.3, 20, W / 2, H / 2, W);
    bgG.addColorStop(0, 'rgba(124,92,255,0.16)'); bgG.addColorStop(1, 'rgba(11,13,23,0)');
    ctx.fillStyle = bgG; ctx.fillRect(0, 0, W, H);

    ctx.save();
    ctx.translate(0, shakeY ? (Math.random() - 0.5) * shakeY : 0);
    // well
    var bw = COLS * CELL, bh = (ROWS - HIDDEN) * CELL;
    ctx.save(); ctx.shadowColor = '#7c5cff'; ctx.shadowBlur = 30;
    ctx.fillStyle = '#10131f'; rr(BX - 6, BY - 6, bw + 12, bh + 12, 14); ctx.fill(); ctx.restore();
    ctx.strokeStyle = 'rgba(34,211,238,0.4)'; ctx.lineWidth = 2; rr(BX - 6, BY - 6, bw + 12, bh + 12, 14); ctx.stroke();
    ctx.strokeStyle = 'rgba(255,255,255,0.035)'; ctx.lineWidth = 1;
    for (var gx = 1; gx < COLS; gx++) { ctx.beginPath(); ctx.moveTo(BX + gx * CELL, BY); ctx.lineTo(BX + gx * CELL, BY + bh); ctx.stroke(); }
    for (var gy = 1; gy < ROWS - HIDDEN; gy++) { ctx.beginPath(); ctx.moveTo(BX, BY + gy * CELL); ctx.lineTo(BX + bw, BY + gy * CELL); ctx.stroke(); }

    if (board) {
      ctx.save(); rr(BX, BY, bw, bh, 8); ctx.clip();
      for (var y = HIDDEN; y < ROWS; y++) {
        var isClr = clearing && clearing.indexOf(y) >= 0;
        for (var x = 0; x < COLS; x++) {
          if (!board[y][x]) continue;
          if (isClr) {
            var k2 = clearT / 0.32;
            ctx.fillStyle = 'rgba(255,255,255,' + (0.4 + k2 * 0.6) + ')';
            var shrink = (1 - k2) * CELL * 0.5;
            rr(BX + x * CELL + shrink / 2, BY + (y - HIDDEN) * CELL + shrink / 2, CELL - shrink, CELL - shrink, 5); ctx.fill();
          } else cell(BX + x * CELL, BY + (y - HIDDEN) * CELL, CELL, board[y][x]);
        }
      }
      if (cur) {
        var gyy = ghostY(), cells = ROT[cur.k][cur.r];
        ctx.strokeStyle = COLORS[cur.k]; ctx.globalAlpha = 0.5; ctx.lineWidth = 2;
        cells.forEach(function (c) { rr(BX + (cur.x + c[0]) * CELL + 3, BY + (gyy + c[1] - HIDDEN) * CELL + 3, CELL - 6, CELL - 6, 5); ctx.stroke(); });
        ctx.globalAlpha = 1;
        ctx.save(); ctx.shadowColor = COLORS[cur.k]; ctx.shadowBlur = 16;
        var lockFade = onGround() ? 0.65 + 0.35 * Math.cos(lockT * 20) : 1;
        cells.forEach(function (c) { cell(BX + (cur.x + c[0]) * CELL, BY + (cur.y + c[1] - HIDDEN) * CELL, CELL, cur.k, lockFade); });
        ctx.restore();
      }
      if (flash > 0) { ctx.fillStyle = 'rgba(255,255,255,' + flash * 0.35 + ')'; ctx.fillRect(BX, BY, bw, bh); }
      ctx.restore();
    }

    // side panels
    panel(14, BY, 92, 96, TXT.hold);
    if (holdK) mini(holdK, 60, BY + 56, 17);
    panel(14, BY + 112, 92, 64, TXT.level);
    panel(14, BY + 188, 92, 64, TXT.linesU);
    ctx.fillStyle = '#eef0ff'; ctx.font = '900 26px system-ui, sans-serif'; ctx.textAlign = 'center';
    ctx.fillText(String(level || 1), 60, BY + 160);
    ctx.fillText(String(lines || 0), 60, BY + 236);
    panel(W - 106, BY, 92, 250, TXT.next);
    if (queue) queue.forEach(function (k, i) { mini(k, W - 60, BY + 62 + i * 70, i === 0 ? 18 : 15); });

    // particles
    particles.forEach(function (p) { ctx.globalAlpha = Math.max(0, Math.min(1, p.life * 2)); ctx.fillStyle = p.color; ctx.fillRect(p.x - p.r, p.y - p.r, p.r * 2, p.r * 2); });
    ctx.globalAlpha = 1;
    // banners
    banners.forEach(function (bn, idx) {
      var a = Math.min(1, bn.life * 2), rise = (1.2 - bn.life) * 24;
      ctx.globalAlpha = a; ctx.textAlign = 'center';
      ctx.save(); ctx.shadowColor = bn.color; ctx.shadowBlur = 16;
      ctx.fillStyle = bn.color; ctx.font = '900 ' + (bn.big ? 30 : 24) + 'px system-ui, sans-serif';
      var yy = BY + bh * (bn.big ? 0.3 : 0.45) - rise + idx * 4;
      ctx.fillText(bn.text, BX + bw / 2, yy);
      ctx.fillStyle = '#fff'; ctx.font = '800 18px system-ui, sans-serif'; ctx.fillText(bn.sub, BX + bw / 2, yy + 26);
      ctx.restore();
    });
    ctx.globalAlpha = 1;
    ctx.restore();
  }

  // ---------------------------------------------------------------- input
  function isKey(e, names) { return names.indexOf(e.key) >= 0 || names.indexOf(e.code) >= 0; }
  game.on('keydown', function (e) {
    if (game.state !== 'playing') return;
    if (isKey(e, ['ArrowLeft', 'a', 'A'])) { shift(-1); dasDir = -1; dasT = 0.16; arrT = 0; }
    else if (isKey(e, ['ArrowRight', 'd', 'D'])) { shift(1); dasDir = 1; dasT = 0.16; arrT = 0; }
    else if (isKey(e, ['ArrowUp', 'x', 'X', 'w', 'W'])) rotate(1);
    else if (isKey(e, ['z', 'Z', 'Control'])) rotate(-1);
    else if (isKey(e, [' ', 'Space'])) hardDrop();
    else if (isKey(e, ['c', 'C', 'Shift'])) hold();
    else if (isKey(e, ['ArrowDown', 's', 'S'])) { gravAcc = Math.max(gravAcc, Math.min(gravity(), 0.03)); }
  });
  var tl = game.touchButtons([{ key: 'ArrowLeft', label: '◀' }, { key: 'ArrowRight', label: '▶' }, { key: 'c', label: '⇄', aria: 'hold' }, { key: 'ArrowDown', label: '▼' }], 'left');
  tl.classList.add('bc-pad');
  var tr = game.touchButtons([{ key: 'z', label: '↺', aria: 'rotate left' }, { key: 'ArrowUp', label: '↻', aria: 'rotate' }, { key: 'Space', label: '⤓', aria: 'hard drop' }], 'right');
  tr.classList.add('bc-pad', 'bc-right');
  // Tap on the well rotates.
  var downAt = null;
  view.canvas.addEventListener('pointerdown', function (e) { downAt = { x: e.clientX, y: e.clientY, t: performance.now() }; });
  view.canvas.addEventListener('pointerup', function (e) {
    if (!downAt || game.state !== 'playing') return;
    var dx = e.clientX - downAt.x, dy = e.clientY - downAt.y;
    if (Math.abs(dx) < 12 && Math.abs(dy) < 12 && performance.now() - downAt.t < 400) rotate(1);
    downAt = null;
  });

  game.on('start', reset);
  game.loop(update, render);

  game.rng = NebuloGame.mulberry32(77); // menu backdrop only; start() reseeds
  board = emptyBoard(); bag = []; queue = [takeNext(), takeNext(), takeNext()]; level = 1; lines = 0;
  game.showMenu();
  game.ready();
})();
