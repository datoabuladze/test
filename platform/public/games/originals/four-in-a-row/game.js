/* Gravity Four – original drop-and-connect duel for Nebulo.
 * 7×6 board, discs fall to the lowest free slot. Connect four in a line to win the round.
 * Vs CPU (alpha-beta search, three difficulty levels) or local 2 players on one device.
 */
(function () {
  'use strict';
  var L = { en: 0, ka: 1, tr: 2, ru: 3 }[NebuloGame.lang] || 0;
  var TXT = {
    title: ['Gravity Four', 'გრავიტაციის ოთხეული', 'Yerçekimi Dörtlü', 'Гравитация: четыре в ряд'][L],
    tagline: ['Drop glowing discs and connect four before your rival does.',
      'ჩააგდე მანათობელი დისკები და შეაერთე ოთხი მეტოქეზე ადრე.',
      'Parlayan diskleri bırak ve rakibinden önce dördünü birleştir.',
      'Бросайте светящиеся диски и соберите четыре в ряд раньше соперника.'][L],
    how: [
      ['Pick a mode at the top: Easy, Medium, Hard or 2 Players (key M cycles).', 'Click/tap a column, or use ← → and Space / ↓ (keys 1–7 drop directly).', 'Discs fall to the lowest free slot.', 'Four in a row – across, down or diagonal – wins the round.', 'Vs CPU: every win scores (faster wins score more), one loss ends the run. 2 Players: first to 3 wins.'],
      ['ზემოთ აირჩიე რეჟიმი: მარტივი, საშუალო, რთული ან 2 მოთამაშე (M ღილაკი ცვლის).', 'დააჭირე სვეტს, ან გამოიყენე ← → და Space / ↓ (1–7 ღილაკები პირდაპირ აგდებს).', 'დისკი ყველაზე დაბალ თავისუფალ ადგილზე ვარდება.', 'ოთხი ერთ ხაზზე – ჰორიზონტალურად, ვერტიკალურად ან დიაგონალზე – რაუნდს იგებს.', 'კომპიუტერთან: ყოველი მოგება ქულას გაძლევს (სწრაფი მოგება – მეტს), ერთი წაგება თამაშს ამთავრებს. 2 მოთამაშე: 3 მოგებამდე.'],
      ['Üstten bir mod seç: Kolay, Orta, Zor veya 2 Oyuncu (M tuşu değiştirir).', 'Bir sütuna tıkla/dokun ya da ← → ve Boşluk / ↓ kullan (1–7 tuşları doğrudan bırakır).', 'Diskler en alttaki boş yuvaya düşer.', 'Yatay, dikey veya çapraz dörtlü dizi turu kazanır.', 'Bilgisayara karşı: her galibiyet puan getirir (hızlı galibiyet daha fazla), tek yenilgi oyunu bitirir. 2 Oyuncu: 3 galibiyete kadar.'],
      ['Выберите режим сверху: Легко, Средне, Сложно или 2 игрока (клавиша M переключает).', 'Нажмите на столбец или используйте ← → и Пробел / ↓ (клавиши 1–7 бросают сразу).', 'Диск падает в самую нижнюю свободную ячейку.', 'Четыре в ряд – по горизонтали, вертикали или диагонали – выигрывают раунд.', 'Против компьютера: каждая победа приносит очки (быстрая – больше), одно поражение завершает серию. 2 игрока: до 3 побед.'],
    ][L],
    modes: [['Easy', 'Medium', 'Hard', '2 Players'], ['მარტივი', 'საშუალო', 'რთული', '2 მოთამაშე'], ['Kolay', 'Orta', 'Zor', '2 Oyuncu'], ['Легко', 'Средне', 'Сложно', '2 игрока']][L],
    you: ['You', 'შენ', 'Sen', 'Вы'][L],
    cpu: ['CPU', 'კომპ.', 'CPU', 'ПК'][L],
    draws: ['Draws', 'ფრე', 'Berabere', 'Ничьи'][L],
    p1: ['Pink', 'ვარდისფერი', 'Pembe', 'Розовый'][L],
    p2: ['Cyan', 'ცისფერი', 'Camgöbeği', 'Голубой'][L],
    yourTurn: ['Your turn', 'შენი სვლაა', 'Sıra sende', 'Ваш ход'][L],
    thinking: ['CPU is thinking…', 'კომპიუტერი ფიქრობს…', 'Bilgisayar düşünüyor…', 'Компьютер думает…'][L],
    turnOf: ['{p} to move', '{p} – შენი სვლაა', 'Sıra: {p}', 'Ходит {p}'][L],
    youWon: ['You win the round!', 'რაუნდი მოიგე!', 'Turu kazandın!', 'Раунд ваш!'][L],
    cpuWon: ['The CPU connects four…', 'კომპიუტერმა ოთხი შეაერთა…', 'Bilgisayar dördü birleştirdi…', 'Компьютер собрал четыре…'][L],
    pWon: ['{p} wins the round!', '{p} იგებს რაუნდს!', '{p} turu kazandı!', '{p} выигрывает раунд!'][L],
    draw: ['Board full – draw!', 'დაფა სავსეა – ფრე!', 'Tahta doldu – berabere!', 'Поле заполнено – ничья!'][L],
    matchWin: ['{p} wins the match!', '{p} იგებს მატჩს!', '{p} maçı kazandı!', '{p} выигрывает матч!'][L],
    beaten: ['The CPU broke your run.', 'კომპიუტერმა სერია შეგიწყვიტა.', 'Bilgisayar serini bozdu.', 'Компьютер прервал вашу серию.'][L],
    wins: ['Wins', 'მოგებები', 'Galibiyet', 'Победы'][L],
    rounds: ['Rounds played', 'ნათამაშები რაუნდები', 'Oynanan tur', 'Сыграно раундов'][L],
    mode: ['Mode', 'რეჟიმი', 'Mod', 'Режим'][L],
  };
  function fmt(s, p) { return s.replace('{p}', p); }

  var game = NebuloGame.create({ id: 'four-in-a-row', title: TXT.title, tagline: TXT.tagline, howTo: TXT.how });
  var W = 720, H = 830, COLS = 7, ROWS = 6, CS = 92, BX = 38, BY = 222, GHOST_Y = 172;
  var view = game.canvas(W, H), ctx = view.ctx;
  var COL = { 1: '#f472b6', 2: '#22d3ee' }, COL_DARK = { 1: '#9d174d', 2: '#0e7490' };
  var WIN_PTS = [100, 300, 700], DRAW_PTS = [30, 90, 250], ORDER = [3, 2, 4, 1, 5, 0, 6];

  var modeIdx = game.store.get('four-in-a-row.mode', 1);
  if (!(modeIdx >= 0 && modeIdx <= 3)) modeIdx = 1;
  var cells, heights, count, turn, starter, phase, timer, falling, winInfo, winT, round, tally, score, streak, cursor = 3, hoverCol = -1, particles = [], pendingOver, shake = 0;

  function isPvp() { return modeIdx === 3; }
  function idx(c, r) { return c * ROWS + r; } // r = 0 is the bottom row
  function nameOf(p) { return isPvp() ? (p === 1 ? TXT.p1 : TXT.p2) : (p === 1 ? TXT.you : TXT.cpu); }

  // ---------------------------------------------------------------- rules
  var WINDOWS = [];
  (function () {
    var dirs = [[1, 0], [0, 1], [1, 1], [1, -1]];
    for (var c = 0; c < COLS; c++) for (var r = 0; r < ROWS; r++) dirs.forEach(function (d) {
      var ec = c + d[0] * 3, er = r + d[1] * 3;
      if (ec < 0 || ec >= COLS || er < 0 || er >= ROWS) return;
      WINDOWS.push([idx(c, r), idx(c + d[0], r + d[1]), idx(c + d[0] * 2, r + d[1] * 2), idx(ec, er)]);
    });
  })();
  function lineThrough(b, c, r) {
    var p = b[idx(c, r)], dirs = [[1, 0], [0, 1], [1, 1], [1, -1]];
    for (var k = 0; k < 4; k++) {
      var dc = dirs[k][0], dr = dirs[k][1], line = [[c, r]];
      for (var s = 1; s < 4; s++) { var cc = c + dc * s, rr2 = r + dr * s; if (cc < 0 || cc >= COLS || rr2 < 0 || rr2 >= ROWS || b[idx(cc, rr2)] !== p) break; line.push([cc, rr2]); }
      for (var s2 = 1; s2 < 4; s2++) { var c2 = c - dc * s2, r2 = r - dr * s2; if (c2 < 0 || c2 >= COLS || r2 < 0 || r2 >= ROWS || b[idx(c2, r2)] !== p) break; line.unshift([c2, r2]); }
      if (line.length >= 4) return line;
    }
    return null;
  }

  // ---------------------------------------------------------------- AI (negamax with alpha-beta)
  var ab, ah, an;
  function evaluate(p) {
    var o = 3 - p, s = 0;
    for (var r = 0; r < ROWS; r++) { var v = ab[idx(3, r)]; if (v === p) s += 3; else if (v === o) s -= 3; }
    for (var i = 0; i < WINDOWS.length; i++) {
      var w = WINDOWS[i], mp = 0, mo = 0;
      for (var j = 0; j < 4; j++) { var x = ab[w[j]]; if (x === p) mp++; else if (x === o) mo++; }
      if (mo === 0) { if (mp === 3) s += 6; else if (mp === 2) s += 2; }
      else if (mp === 0) { if (mo === 3) s -= 7; else if (mo === 2) s -= 2; }
    }
    return s;
  }
  function winsAt(c, p) {
    var r = ah[c]; ab[idx(c, r)] = p;
    var w = lineThrough(ab, c, r) !== null; ab[idx(c, r)] = 0;
    return w;
  }
  function negamax(depth, alpha, beta, p) {
    an++;
    for (var k = 0; k < COLS; k++) if (ah[k] < ROWS && winsAt(k, p)) return 100000 + depth;
    if (depth === 0) return evaluate(p);
    var best = -Infinity;
    for (var i = 0; i < COLS; i++) {
      var c = ORDER[i]; if (ah[c] >= ROWS) continue;
      ab[idx(c, ah[c])] = p; ah[c]++;
      var v = -negamax(depth - 1, -beta, -alpha, 3 - p);
      ah[c]--; ab[idx(c, ah[c])] = 0;
      if (v > best) best = v;
      if (best > alpha) alpha = best;
      if (alpha >= beta) break;
    }
    return best === -Infinity ? 0 : best;
  }
  function cpuChoose() {
    ab = cells.slice(); ah = heights.slice(); an = 0;
    var r = game.rng, legal = [];
    for (var c = 0; c < COLS; c++) if (ah[c] < ROWS) legal.push(c);
    var depth = [2, 4, 7][modeIdx], randomChance = [0.35, 0.08, 0][modeIdx];
    if (r() < randomChance) {
      // even a careless CPU grabs an obvious win most of the time
      for (var w = 0; w < legal.length; w++) if (winsAt(legal[w], 2) && r() < 0.7) return legal[w];
      return legal[Math.floor(r() * legal.length)];
    }
    var best = -Infinity, list = [];
    for (var i = 0; i < COLS; i++) {
      var col = ORDER[i]; if (ah[col] >= ROWS) continue;
      var v;
      if (winsAt(col, 2)) v = 200000;
      else { ab[idx(col, ah[col])] = 2; ah[col]++; v = -negamax(depth - 1, -Infinity, Infinity, 1); ah[col]--; ab[idx(col, ah[col])] = 0; }
      if (v > best) { best = v; list = [col]; } else if (v === best) list.push(col);
    }
    return list[Math.floor(r() * list.length)];
  }

  // ---------------------------------------------------------------- flow
  function newMatch() {
    round = 0; tally = { 1: 0, 2: 0, D: 0 }; score = 0; streak = 0; pendingOver = false; particles = []; starter = 2;
    game.setStat('score', 0);
    game.setStat('wins', 0, TXT.wins);
    newRound();
  }
  function newRound() {
    round++;
    cells = []; for (var i = 0; i < COLS * ROWS; i++) cells.push(0);
    heights = [0, 0, 0, 0, 0, 0, 0]; count = 0;
    winInfo = null; winT = 0; falling = null;
    starter = 3 - starter; turn = starter;
    phase = 'play';
    if (!isPvp() && turn === 2) { phase = 'cpu'; timer = 0.6; }
  }
  function slotY(r) { return BY + (ROWS - 1 - r + 0.5) * CS; }
  function slotX(c) { return BX + (c + 0.5) * CS; }
  function drop(c, p) {
    if (heights[c] >= ROWS) { game.audio.sfx('move'); return false; }
    var r = heights[c];
    cells[idx(c, r)] = p; heights[c]++; count++;
    falling = { c: c, r: r, p: p, y: GHOST_Y, vy: 0, target: slotY(r), bounces: 0 };
    phase = 'drop';
    game.audio.tone(p === 1 ? 640 : 480, 0.08, 'triangle', 0.08, 300);
    return true;
  }
  function landed() {
    var f = falling; falling = null;
    var line = lineThrough(cells, f.c, f.r);
    if (line) { endRound(f.p, line); return; }
    if (count >= COLS * ROWS) { endRound(0, null); return; }
    turn = 3 - f.p;
    if (!isPvp() && turn === 2) { phase = 'cpu'; timer = 0.3 + game.rng() * 0.3; } else phase = 'play';
  }
  function burst(x, y, color, n) {
    for (var i = 0; i < n; i++) {
      var a = Math.random() * Math.PI * 2, s = 60 + Math.random() * 300;
      particles.push({ x: x, y: y, vx: Math.cos(a) * s, vy: Math.sin(a) * s - 80, life: 0.6 + Math.random() * 0.7, max: 1.3, c: color, r: 2 + Math.random() * 3 });
    }
  }
  function endRound(p, line) {
    winInfo = { p: p, line: line }; phase = 'end'; timer = 1.8; winT = 0;
    var diff = Math.min(modeIdx, 2);
    if (!p) {
      tally.D++; if (!isPvp()) score += DRAW_PTS[diff];
      game.audio.sfx('tick');
    } else {
      tally[p]++;
      line.forEach(function (q) { burst(slotX(q[0]), slotY(q[1]), COL[p], 14); });
      if (isPvp()) { game.audio.sfx('bonus'); if (tally[p] >= 3) { pendingOver = true; timer = 2; } }
      else if (p === 1) { score += WIN_PTS[diff] + 10 * (COLS * ROWS - count); streak++; game.audio.sfx('bonus'); }
      else { game.audio.sfx('hit'); shake = 1; pendingOver = true; timer = 2; }
    }
    game.setStat('score', score);
    game.setStat('wins', isPvp() ? tally[1] + ':' + tally[2] : tally[1], TXT.wins);
  }
  function finish() {
    if (isPvp()) {
      var champ = tally[1] >= 3 ? 1 : 2, line = tally[1] + ' : ' + tally[2];
      game.over({ score: 0, win: true, title: fmt(TXT.matchWin, nameOf(champ)), formatScore: function (v) { return v === 0 ? line : String(v); },
        lines: [TXT.draws + ': ' + tally.D, TXT.rounds + ': ' + round] });
    } else {
      game.over({ score: score, win: false, title: TXT.beaten,
        lines: [TXT.mode + ': ' + TXT.modes[modeIdx], TXT.wins + ': ' + tally[1] + ' · ' + TXT.draws + ': ' + tally.D, TXT.rounds + ': ' + round],
        evidence: { mode: ['easy', 'medium', 'hard'][modeIdx], rounds: round, wins: tally[1], draws: tally.D } });
    }
  }
  function humanCanPlay() { return game.state === 'playing' && phase === 'play' && (isPvp() || turn === 1); }
  function humanDrop(c) { if (humanCanPlay()) drop(c, turn); }
  function setMode(i) {
    if (i === modeIdx) return;
    modeIdx = i; game.store.set('four-in-a-row.mode', i); game.audio.sfx('click');
    if (game.state === 'playing') game.restart();
  }

  function update(dt) {
    if (winInfo) winT += dt;
    if (phase === 'cpu') {
      timer -= dt;
      if (timer <= 0) { phase = 'busy'; var c = cpuChoose(); cursorCpu = c; drop(c, 2); }
    } else if (phase === 'drop' && falling) {
      var f = falling;
      f.vy += 5200 * dt; f.y += f.vy * dt;
      if (f.y >= f.target) {
        f.y = f.target;
        if (f.vy > 500 && f.bounces < 2) { f.vy = -f.vy * 0.28; f.bounces++; if (f.bounces === 1) game.audio.tone(170, 0.06, 'square', 0.08); }
        else landed();
      }
    } else if (phase === 'end') {
      timer -= dt;
      if (timer <= 0) { if (pendingOver) { phase = 'done'; finish(); } else newRound(); }
    }
  }

  // ---------------------------------------------------------------- render
  var cursorCpu = 3, keyCursor = false;
  function rr(x, y, w, h, r) {
    ctx.beginPath(); ctx.moveTo(x + r, y); ctx.arcTo(x + w, y, x + w, y + h, r); ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r); ctx.arcTo(x, y, x + w, y, r); ctx.closePath();
  }
  function modeRect(i) { return { x: 38 + i * 164, y: 18, w: 152, h: 50 }; }
  function drawText(s, x, y, size, color, weight, maxW) {
    var f = ' system-ui, -apple-system, Segoe UI, Roboto, sans-serif';
    ctx.font = (weight || 800) + ' ' + size + 'px' + f;
    if (maxW) { var w = ctx.measureText(s).width; if (w > maxW) ctx.font = (weight || 800) + ' ' + Math.floor(size * maxW / w) + 'px' + f; }
    ctx.fillStyle = color; ctx.fillText(s, x, y);
  }
  function disc(x, y, p, alpha, scale) {
    var rad = CS * 0.4 * (scale || 1);
    ctx.save(); ctx.globalAlpha = alpha;
    var g = ctx.createRadialGradient(x - rad * 0.35, y - rad * 0.4, rad * 0.1, x, y, rad);
    g.addColorStop(0, '#fff'); g.addColorStop(0.18, COL[p]); g.addColorStop(1, COL_DARK[p]);
    ctx.shadowColor = COL[p]; ctx.shadowBlur = 16;
    ctx.fillStyle = g; ctx.beginPath(); ctx.arc(x, y, rad, 0, Math.PI * 2); ctx.fill();
    ctx.shadowBlur = 0; ctx.strokeStyle = 'rgba(255,255,255,.35)'; ctx.lineWidth = 2;
    ctx.beginPath(); ctx.arc(x, y, rad * 0.68, 0, Math.PI * 2); ctx.stroke();
    ctx.restore();
  }
  function render(dt) {
    var now = performance.now() / 1000;
    ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    var g = ctx.createLinearGradient(0, 0, 0, H); g.addColorStop(0, '#141032'); g.addColorStop(1, '#0b0d17');
    ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);

    for (var m = 0; m < 4; m++) {
      var r = modeRect(m); rr(r.x, r.y, r.w, r.h, 14);
      if (m === modeIdx) { var bg = ctx.createLinearGradient(r.x, r.y, r.x + r.w, r.y + r.h); bg.addColorStop(0, '#7c5cff'); bg.addColorStop(1, '#c062f5'); ctx.fillStyle = bg; ctx.fill(); }
      else { ctx.fillStyle = 'rgba(255,255,255,.06)'; ctx.fill(); ctx.strokeStyle = 'rgba(255,255,255,.12)'; ctx.lineWidth = 1.5; ctx.stroke(); }
      drawText(TXT.modes[m], r.x + r.w / 2, r.y + r.h / 2 + 1, 19, m === modeIdx ? '#fff' : '#b4b9d6', 800, r.w - 16);
    }

    if (tally) {
      var items = [{ p: 1, l: nameOf(1), v: tally[1] }, { p: 0, l: TXT.draws, v: tally.D }, { p: 2, l: nameOf(2), v: tally[2] }];
      for (var k = 0; k < 3; k++) {
        var x = 38 + k * 222, y = 80, it = items[k], on = it.p && turn === it.p && phase !== 'end' && phase !== 'done';
        rr(x, y, 200, 54, 14); ctx.fillStyle = on ? 'rgba(255,255,255,.1)' : 'rgba(255,255,255,.04)'; ctx.fill();
        if (on) { ctx.strokeStyle = COL[it.p]; ctx.lineWidth = 2; ctx.stroke(); }
        if (it.p) disc(x + 30, y + 27, it.p, 1, 0.42);
        drawText(it.l, x + (it.p ? 110 : 100), y + 18, 14, it.p ? COL[it.p] : '#b4b9d6', 700, 120);
        drawText(String(it.v), x + (it.p ? 110 : 100), y + 39, 21, '#fff', 900);
      }
    }

    var sx = 0, sy = 0;
    if (shake > 0) { shake = Math.max(0, shake - dt * 2.5); sx = (Math.random() - 0.5) * 14 * shake; sy = (Math.random() - 0.5) * 14 * shake; }
    ctx.save(); ctx.translate(sx, sy);

    // ghost disc above the board
    var col = keyCursor || hoverCol < 0 ? cursor : hoverCol;
    if (cells && humanCanPlay()) {
      var bob = Math.sin(now * 5) * 4;
      disc(slotX(col), GHOST_Y + bob, turn, 0.9, 1);
      ctx.fillStyle = 'rgba(255,255,255,.04)'; ctx.fillRect(BX + col * CS, BY, CS, ROWS * CS);
    } else if (cells && phase === 'cpu') {
      disc(slotX(cursorCpu), GHOST_Y + Math.sin(now * 8) * 3, 2, 0.45, 1);
    }

    // discs (drawn behind the board face)
    if (cells) {
      for (var c = 0; c < COLS; c++) for (var rw = 0; rw < heights[c]; rw++) {
        if (falling && falling.c === c && falling.r === rw) continue;
        var isWin = winInfo && winInfo.line && winInfo.line.some(function (q) { return q[0] === c && q[1] === rw; });
        var a = winInfo && winInfo.line && !isWin ? 0.45 : 1;
        disc(slotX(c), slotY(rw), cells[idx(c, rw)], a, isWin ? 1 + 0.05 * Math.sin(now * 10) : 1);
      }
      if (falling) disc(slotX(falling.c), falling.y, falling.p, 1, 1);
    }

    // board face with holes
    ctx.save();
    ctx.beginPath();
    rr(BX - 10, BY - 10, COLS * CS + 20, ROWS * CS + 20, 22);
    for (var hc = 0; hc < COLS; hc++) for (var hr = 0; hr < ROWS; hr++) {
      var cx = slotX(hc), cy = BY + (hr + 0.5) * CS; ctx.moveTo(cx + CS * 0.42, cy); ctx.arc(cx, cy, CS * 0.42, 0, Math.PI * 2, true);
    }
    var fg = ctx.createLinearGradient(0, BY, 0, BY + ROWS * CS); fg.addColorStop(0, '#2a2470'); fg.addColorStop(1, '#17143f');
    ctx.fillStyle = fg; ctx.shadowColor = 'rgba(124,92,255,.55)'; ctx.shadowBlur = 30; ctx.fill('evenodd');
    ctx.restore();
    ctx.strokeStyle = 'rgba(124,92,255,.7)'; ctx.lineWidth = 2; rr(BX - 10, BY - 10, COLS * CS + 20, ROWS * CS + 20, 22); ctx.stroke();
    ctx.strokeStyle = 'rgba(255,255,255,.07)'; ctx.lineWidth = 2;
    for (var hc2 = 0; hc2 < COLS; hc2++) for (var hr2 = 0; hr2 < ROWS; hr2++) { ctx.beginPath(); ctx.arc(slotX(hc2), BY + (hr2 + 0.5) * CS, CS * 0.42, 0, Math.PI * 2); ctx.stroke(); }

    // winning line
    if (winInfo && winInfo.line) {
      var l = winInfo.line, a0 = l[0], a1 = l[l.length - 1], p = Math.min(1, winT / 0.4);
      var x0 = slotX(a0[0]), y0 = slotY(a0[1]), x1 = slotX(a1[0]), y1 = slotY(a1[1]);
      ctx.save(); ctx.lineCap = 'round'; ctx.strokeStyle = '#fff'; ctx.lineWidth = 9; ctx.shadowColor = COL[winInfo.p]; ctx.shadowBlur = 28;
      ctx.beginPath(); ctx.moveTo(x0, y0); ctx.lineTo(x0 + (x1 - x0) * p, y0 + (y1 - y0) * p); ctx.stroke(); ctx.restore();
      l.forEach(function (q) { ctx.strokeStyle = 'rgba(255,255,255,' + (0.5 + 0.5 * Math.sin(now * 8)) + ')'; ctx.lineWidth = 3; ctx.beginPath(); ctx.arc(slotX(q[0]), slotY(q[1]), CS * 0.45, 0, Math.PI * 2); ctx.stroke(); });
    }
    ctx.restore();

    if (game.state !== 'paused') {
      for (var pi = particles.length - 1; pi >= 0; pi--) {
        var pt = particles[pi]; pt.life -= dt; if (pt.life <= 0) { particles.splice(pi, 1); continue; }
        pt.x += pt.vx * dt; pt.y += pt.vy * dt; pt.vx *= 0.97; pt.vy = pt.vy * 0.97 + 420 * dt;
      }
    }
    particles.forEach(function (q) { ctx.globalAlpha = Math.max(0, q.life / q.max); ctx.fillStyle = q.c; ctx.beginPath(); ctx.arc(q.x, q.y, q.r, 0, Math.PI * 2); ctx.fill(); });
    ctx.globalAlpha = 1;

    var st = '', sc = '#eef0ff';
    if (cells && game.state !== 'menu') {
      if (phase === 'end' || phase === 'done') {
        if (!winInfo.p) { st = TXT.draw; sc = '#b4b9d6'; }
        else if (isPvp()) { st = fmt(TXT.pWon, nameOf(winInfo.p)); sc = COL[winInfo.p]; }
        else if (winInfo.p === 1) { st = TXT.youWon; sc = COL[1]; } else { st = TXT.cpuWon; sc = COL[2]; }
      } else if (phase === 'cpu' || (!isPvp() && turn === 2)) { st = TXT.thinking; sc = COL[2]; }
      else if (isPvp()) { st = fmt(TXT.turnOf, nameOf(turn)); sc = COL[turn]; }
      else { st = TXT.yourTurn; sc = COL[1]; }
    }
    if (st) drawText(st, W / 2, 806, 24, sc, 900, W - 40);
  }

  // ---------------------------------------------------------------- input
  function colAt(p) { if (p.x < BX || p.x >= BX + COLS * CS || p.y < 120 || p.y > BY + ROWS * CS + 10) return -1; return Math.floor((p.x - BX) / CS); }
  view.canvas.addEventListener('pointerdown', function (e) {
    e.preventDefault(); game.audio.ensure();
    var p = view.toLogical(e.clientX, e.clientY);
    for (var m = 0; m < 4; m++) { var r = modeRect(m); if (p.x >= r.x && p.x <= r.x + r.w && p.y >= r.y && p.y <= r.y + r.h) { setMode(m); return; } }
    var c = colAt(p);
    if (c >= 0) { keyCursor = false; cursor = c; hoverCol = e.pointerType === 'mouse' ? c : -1; humanDrop(c); }
  });
  view.canvas.addEventListener('pointermove', function (e) {
    if (e.pointerType !== 'mouse') return;
    var c = colAt(view.toLogical(e.clientX, e.clientY));
    if (c >= 0 && c !== hoverCol) { keyCursor = false; hoverCol = c; cursor = c; }
  });
  view.canvas.addEventListener('pointerleave', function () { hoverCol = -1; });
  game.on('keydown', function (e) {
    var k = e.key;
    if (k === 'm' || k === 'M') { setMode((modeIdx + 1) % 4); return; }
    if (game.state !== 'playing') return;
    if (k >= '1' && k <= '7' && k.length === 1) { cursor = Number(k) - 1; keyCursor = true; humanDrop(cursor); return; }
    if (k === 'ArrowLeft' || k === 'a' || k === 'A') { keyCursor = true; cursor = (cursor + COLS - 1) % COLS; game.audio.sfx('move'); }
    else if (k === 'ArrowRight' || k === 'd' || k === 'D') { keyCursor = true; cursor = (cursor + 1) % COLS; game.audio.sfx('move'); }
    else if (k === ' ' || k === 'Enter' || k === 'ArrowDown' || k === 's' || k === 'S') { keyCursor = true; humanDrop(cursor); }
  });

  game.on('start', newMatch);
  game.loop(update, render);
  game.showMenu();
  game.ready();
})();
