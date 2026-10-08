/* Neon Noughts – original three-in-a-row duel for Nebulo.
 * Modes: vs CPU (easy / medium / unbeatable minimax) or local 2 players on one device.
 * Vs CPU you keep playing rounds until the CPU beats you; wins and draws add points.
 * 2 players: first to 3 round wins takes the match.
 */
(function () {
  'use strict';
  var L = { en: 0, ka: 1, tr: 2, ru: 3 }[NebuloGame.lang] || 0;
  var TXT = {
    title: ['Neon Noughts', 'ნეონის X-O', 'Neon XOX', 'Неоновые крестики-нолики'][L],
    tagline: ['Line up three glowing marks. Beat the CPU again and again – or challenge a friend.',
      'ააწყვე სამი მანათობელი ნიშანი ერთ ხაზზე. დაამარცხე კომპიუტერი ისევ და ისევ – ან გამოიწვიე მეგობარი.',
      'Üç parlayan işareti yan yana getir. Bilgisayarı tekrar tekrar yen ya da bir arkadaşına meydan oku.',
      'Выстройте три светящихся знака в ряд. Побеждайте компьютер раз за разом – или сыграйте с другом.'][L],
    how: [
      ['Pick a mode at the top: Easy, Medium, Unbeatable or 2 Players (key M cycles).', 'Click/tap a cell, or use keys 1–9 / arrows + Space.', 'Three in a row – across, down or diagonal – wins the round.', 'Vs CPU: wins and draws score points, one loss ends the run.', '2 Players: first to 3 round wins takes the match.'],
      ['ზემოთ აირჩიე რეჟიმი: მარტივი, საშუალო, უძლეველი ან 2 მოთამაშე (M ღილაკი ცვლის).', 'დააჭირე უჯრას, ან გამოიყენე 1–9 / ისრები + Space.', 'სამი ერთ ხაზზე – ჰორიზონტალურად, ვერტიკალურად ან დიაგონალზე – რაუნდს იგებს.', 'კომპიუტერთან: მოგება და ფრე ქულას გაძლევს, ერთი წაგება თამაშს ამთავრებს.', '2 მოთამაშე: ვინც პირველი მოიგებს 3 რაუნდს, ის იმარჯვებს.'],
      ['Üstten bir mod seç: Kolay, Orta, Yenilmez veya 2 Oyuncu (M tuşu değiştirir).', 'Bir kareye tıkla/dokun ya da 1–9 tuşlarını / ok tuşları + Boşluk kullan.', 'Yatay, dikey veya çapraz üçlü dizi turu kazanır.', 'Bilgisayara karşı: galibiyet ve beraberlik puan getirir, tek yenilgi oyunu bitirir.', '2 Oyuncu: 3 turu ilk kazanan maçı alır.'],
      ['Выберите режим сверху: Легко, Средне, Непобедимый или 2 игрока (клавиша M переключает).', 'Нажмите на клетку или используйте клавиши 1–9 / стрелки + Пробел.', 'Три в ряд – по горизонтали, вертикали или диагонали – выигрывают раунд.', 'Против компьютера: победы и ничьи приносят очки, одно поражение завершает серию.', '2 игрока: кто первым выиграет 3 раунда, тот победил в матче.'],
    ][L],
    modes: [['Easy', 'Medium', 'Unbeatable', '2 Players'], ['მარტივი', 'საშუალო', 'უძლეველი', '2 მოთამაშე'], ['Kolay', 'Orta', 'Yenilmez', '2 Oyuncu'], ['Легко', 'Средне', 'Непобедимый', '2 игрока']][L],
    you: ['You', 'შენ', 'Sen', 'Вы'][L],
    cpu: ['CPU', 'კომპ.', 'CPU', 'ПК'][L],
    draws: ['Draws', 'ფრე', 'Berabere', 'Ничьи'][L],
    yourTurn: ['Your turn', 'შენი სვლაა', 'Sıra sende', 'Ваш ход'][L],
    thinking: ['CPU is thinking…', 'კომპიუტერი ფიქრობს…', 'Bilgisayar düşünüyor…', 'Компьютер думает…'][L],
    turnOf: ['{p} to move', '{p}-ის სვლაა', 'Sıra {p} oyuncusunda', 'Ходит {p}'][L],
    youWon: ['You win the round!', 'რაუნდი მოიგე!', 'Turu kazandın!', 'Раунд ваш!'][L],
    cpuWon: ['The CPU wins…', 'კომპიუტერმა მოიგო…', 'Bilgisayar kazandı…', 'Компьютер победил…'][L],
    pWon: ['{p} wins the round!', '{p}-მ მოიგო რაუნდი!', '{p} turu kazandı!', '{p} выигрывает раунд!'][L],
    draw: ['Draw!', 'ფრე!', 'Berabere!', 'Ничья!'][L],
    matchWin: ['{p} wins the match!', '{p}-მ მოიგო მატჩი!', '{p} maçı kazandı!', '{p} выигрывает матч!'][L],
    streak: ['Streak', 'სერია', 'Seri', 'Серия'][L],
    bestStreak: ['Best win streak', 'საუკეთესო სერია', 'En iyi galibiyet serisi', 'Лучшая серия побед'][L],
    rounds: ['Rounds played', 'ნათამაშები რაუნდები', 'Oynanan tur', 'Сыграно раундов'][L],
    mode: ['Mode', 'რეჟიმი', 'Mod', 'Режим'][L],
    beaten: ['The CPU broke your run.', 'კომპიუტერმა სერია შეგიწყვიტა.', 'Bilgisayar serini bozdu.', 'Компьютер прервал вашу серию.'][L],
  };
  function fmt(s, p) { return s.replace('{p}', p); }

  var game = NebuloGame.create({ id: 'tic-tac-toe', title: TXT.title, tagline: TXT.tagline, howTo: TXT.how });
  var W = 600, H = 760, BX = 60, BY = 180, CS = 160;
  var view = game.canvas(W, H), ctx = view.ctx;
  var LINES = [[0, 1, 2], [3, 4, 5], [6, 7, 8], [0, 3, 6], [1, 4, 7], [2, 5, 8], [0, 4, 8], [2, 4, 6]];
  var COL = { X: '#22d3ee', O: '#f472b6' };
  var WIN_PTS = [100, 300, 0], DRAW_PTS = [20, 60, 150];

  var modeIdx = game.store.get('tic-tac-toe.mode', 1);
  if (!(modeIdx >= 0 && modeIdx <= 3)) modeIdx = 1;
  var board, marks, turn, starter, phase, timer, winInfo, winT, round, tally, score, streak, bestStreak, cursor, hover = -1, particles = [], pendingOver, flash = 0;

  function isPvp() { return modeIdx === 3; }
  function other(p) { return p === 'X' ? 'O' : 'X'; }
  function winner(b) {
    for (var i = 0; i < LINES.length; i++) {
      var l = LINES[i];
      if (b[l[0]] && b[l[0]] === b[l[1]] && b[l[0]] === b[l[2]]) return { p: b[l[0]], line: l };
    }
    for (var j = 0; j < 9; j++) if (!b[j]) return null;
    return { p: 'draw', line: null };
  }
  function empties(b) { var e = []; for (var i = 0; i < 9; i++) if (!b[i]) e.push(i); return e; }

  // ---------------------------------------------------------------- AI
  function minimax(b, toMove, me, depth) {
    var w = winner(b);
    if (w) return w.p === 'draw' ? 0 : (w.p === me ? 10 - depth : depth - 10);
    var best = toMove === me ? -99 : 99;
    for (var i = 0; i < 9; i++) {
      if (b[i]) continue;
      b[i] = toMove;
      var v = minimax(b, other(toMove), me, depth + 1);
      b[i] = '';
      if (toMove === me ? v > best : v < best) best = v;
    }
    return best;
  }
  function bestMove(b, me) {
    var best = -999, list = [];
    empties(b).forEach(function (i) {
      b[i] = me; var v = minimax(b, other(me), me, 1); b[i] = '';
      if (v > best) { best = v; list = [i]; } else if (v === best) list.push(i);
    });
    return list[Math.floor(game.rng() * list.length)];
  }
  function finishingMove(b, p) {
    var e = empties(b);
    for (var k = 0; k < e.length; k++) { b[e[k]] = p; var w = winner(b); b[e[k]] = ''; if (w && w.p === p) return e[k]; }
    return -1;
  }
  function cpuChoose() {
    var b = board.slice(), e = empties(b), r = game.rng;
    var rand = e[Math.floor(r() * e.length)];
    var win = finishingMove(b, 'O'), block = finishingMove(b, 'X');
    if (modeIdx === 0) {
      if (win >= 0 && r() < 0.6) return win;
      if (block >= 0 && r() < 0.3) return block;
      return rand;
    }
    if (modeIdx === 1) {
      if (win >= 0) return win;
      if (block >= 0 && r() < 0.85) return block;
      return r() < 0.5 ? bestMove(b, 'O') : rand;
    }
    return bestMove(b, 'O');
  }

  // ---------------------------------------------------------------- flow
  function newMatch() {
    round = 0; tally = { X: 0, O: 0, D: 0 }; score = 0; streak = 0; bestStreak = 0; pendingOver = false; particles = [];
    cursor = 4; starter = 'O';
    game.setStat('score', 0);
    game.setStat('streak', 0, TXT.streak);
    newRound();
  }
  function newRound() {
    round++;
    board = ['', '', '', '', '', '', '', '', '']; marks = [];
    winInfo = null; winT = 0;
    starter = other(starter); turn = starter;
    phase = 'play';
    if (!isPvp() && turn === 'O') { phase = 'cpu'; timer = 0.55; }
  }
  function place(i, p) {
    if (board[i]) return;
    board[i] = p; marks[i] = 0;
    game.audio.tone(p === 'X' ? 520 : 390, 0.09, 'triangle', 0.12, p === 'X' ? 780 : 300);
    var w = winner(board);
    if (w) { endRound(w); return; }
    turn = other(p);
    if (!isPvp() && turn === 'O') { phase = 'cpu'; timer = 0.35 + game.rng() * 0.35; } else phase = 'play';
  }
  function burst(x, y, color, n) {
    for (var i = 0; i < n; i++) {
      var a = Math.random() * Math.PI * 2, s = 80 + Math.random() * 260;
      particles.push({ x: x, y: y, vx: Math.cos(a) * s, vy: Math.sin(a) * s, life: 0.6 + Math.random() * 0.6, max: 1.2, c: color, r: 2 + Math.random() * 3 });
    }
  }
  function endRound(w) {
    winInfo = w; phase = 'end'; timer = 1.5; winT = 0;
    var diff = Math.min(modeIdx, 2);
    if (w.p === 'draw') {
      tally.D++;
      if (!isPvp()) { score += DRAW_PTS[diff]; }
      game.audio.sfx('tick');
    } else {
      tally[w.p]++;
      var mid = w.line[1], cx = BX + (mid % 3 + 0.5) * CS, cy = BY + (Math.floor(mid / 3) + 0.5) * CS;
      burst(cx, cy, COL[w.p], 50);
      flash = 1;
      if (isPvp()) {
        game.audio.sfx('bonus');
        if (tally[w.p] >= 3) { pendingOver = true; timer = 1.7; }
      } else if (w.p === 'X') {
        score += WIN_PTS[diff]; streak++; bestStreak = Math.max(bestStreak, streak);
        game.audio.sfx('bonus');
      } else {
        game.audio.sfx('hit');
        pendingOver = true; timer = 1.7;
      }
    }
    game.setStat('score', score);
    game.setStat('streak', isPvp() ? tally.X + ':' + tally.O : streak, TXT.streak);
  }
  function finish() {
    if (isPvp()) {
      var champ = tally.X >= 3 ? 'X' : 'O';
      var line = 'X ' + tally.X + ' : ' + tally.O + ' O';
      game.over({ score: 0, win: true, title: fmt(TXT.matchWin, champ), formatScore: function (v) { return v === 0 ? line : String(v); },
        lines: [TXT.draws + ': ' + tally.D, TXT.rounds + ': ' + round] });
    } else {
      game.over({ score: score, win: false, title: TXT.beaten,
        lines: [TXT.mode + ': ' + TXT.modes[modeIdx], TXT.bestStreak + ': ' + bestStreak, TXT.rounds + ': ' + round],
        evidence: { mode: ['easy', 'medium', 'unbeatable'][modeIdx], rounds: round, wins: tally.X, draws: tally.D } });
    }
  }
  function humanCanPlay() { return game.state === 'playing' && phase === 'play' && (isPvp() || turn === 'X'); }
  function humanPlace(i) { if (humanCanPlay() && !board[i]) place(i, turn); else if (humanCanPlay()) game.audio.sfx('move'); }
  function setMode(i) {
    if (i === modeIdx) return;
    modeIdx = i; game.store.set('tic-tac-toe.mode', i);
    game.audio.sfx('click');
    if (game.state === 'playing') game.restart();
  }

  // ---------------------------------------------------------------- update
  function update(dt) {
    for (var i = 0; i < 9; i++) if (marks[i] !== undefined) marks[i] += dt;
    if (winInfo) winT += dt;
    if (phase === 'cpu') {
      timer -= dt;
      if (timer <= 0) place(cpuChoose(), 'O');
    } else if (phase === 'end') {
      timer -= dt;
      if (timer <= 0) { if (pendingOver) { phase = 'done'; finish(); } else newRound(); }
    }
  }

  // ---------------------------------------------------------------- render
  function rr(x, y, w, h, r) {
    ctx.beginPath(); ctx.moveTo(x + r, y); ctx.arcTo(x + w, y, x + w, y + h, r); ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r); ctx.arcTo(x, y, x + w, y, r); ctx.closePath();
  }
  function modeRect(i) { return { x: 35 + i * 135, y: 22, w: 125, h: 52 }; }
  function drawText(s, x, y, size, color, weight, maxW) {
    ctx.font = (weight || 800) + ' ' + size + 'px system-ui, -apple-system, Segoe UI, Roboto, sans-serif';
    if (maxW) { var w = ctx.measureText(s).width; if (w > maxW) ctx.font = (weight || 800) + ' ' + Math.floor(size * maxW / w) + 'px system-ui, -apple-system, Segoe UI, Roboto, sans-serif'; }
    ctx.fillStyle = color; ctx.fillText(s, x, y);
  }
  function drawX(cx, cy, s, t, alpha) {
    var p1 = Math.min(1, t / 0.12), p2 = Math.max(0, Math.min(1, (t - 0.1) / 0.12));
    ctx.save(); ctx.globalAlpha = alpha; ctx.strokeStyle = COL.X; ctx.lineWidth = s * 0.16; ctx.lineCap = 'round';
    ctx.shadowColor = COL.X; ctx.shadowBlur = 18;
    var d = s * 0.32;
    ctx.beginPath(); ctx.moveTo(cx - d, cy - d); ctx.lineTo(cx - d + 2 * d * p1, cy - d + 2 * d * p1); ctx.stroke();
    if (p2 > 0) { ctx.beginPath(); ctx.moveTo(cx + d, cy - d); ctx.lineTo(cx + d - 2 * d * p2, cy - d + 2 * d * p2); ctx.stroke(); }
    ctx.restore();
  }
  function drawO(cx, cy, s, t, alpha) {
    var p = Math.min(1, t / 0.22);
    ctx.save(); ctx.globalAlpha = alpha; ctx.strokeStyle = COL.O; ctx.lineWidth = s * 0.15; ctx.lineCap = 'round';
    ctx.shadowColor = COL.O; ctx.shadowBlur = 18;
    ctx.beginPath(); ctx.arc(cx, cy, s * 0.32, -Math.PI / 2, -Math.PI / 2 + Math.PI * 2 * p); ctx.stroke();
    ctx.restore();
  }
  function render(dt) {
    var now = performance.now() / 1000;
    ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    var g = ctx.createRadialGradient(W / 2, H * 0.45, 40, W / 2, H * 0.45, H * 0.8);
    g.addColorStop(0, '#1a1640'); g.addColorStop(1, '#0b0d17');
    ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);
    ctx.fillStyle = 'rgba(255,255,255,.05)';
    for (var gx = 15; gx < W; gx += 30) for (var gy = 15; gy < H; gy += 30) ctx.fillRect(gx, gy, 2, 2);

    // mode buttons
    for (var m = 0; m < 4; m++) {
      var r = modeRect(m);
      rr(r.x, r.y, r.w, r.h, 14);
      if (m === modeIdx) {
        var bg = ctx.createLinearGradient(r.x, r.y, r.x + r.w, r.y + r.h); bg.addColorStop(0, '#7c5cff'); bg.addColorStop(1, '#c062f5');
        ctx.fillStyle = bg; ctx.fill();
      } else { ctx.fillStyle = 'rgba(255,255,255,.06)'; ctx.fill(); ctx.strokeStyle = 'rgba(255,255,255,.12)'; ctx.lineWidth = 1.5; ctx.stroke(); }
      drawText(TXT.modes[m], r.x + r.w / 2, r.y + r.h / 2 + 1, 18, m === modeIdx ? '#fff' : '#b4b9d6', 800, r.w - 14);
    }

    // scoreboard
    if (tally) {
      var pv = isPvp();
      var cells = [{ l: pv ? 'X' : TXT.you + ' (X)', v: tally.X, c: COL.X, on: turn === 'X' && phase !== 'end' }, { l: TXT.draws, v: tally.D, c: '#b4b9d6', on: false },
        { l: pv ? 'O' : TXT.cpu + ' (O)', v: tally.O, c: COL.O, on: turn === 'O' && phase !== 'end' }];
      for (var k = 0; k < 3; k++) {
        var x = 60 + k * 166, y = 94;
        rr(x, y, 148, 66, 14);
        ctx.fillStyle = cells[k].on ? 'rgba(255,255,255,.1)' : 'rgba(255,255,255,.04)'; ctx.fill();
        if (cells[k].on) { ctx.strokeStyle = cells[k].c; ctx.lineWidth = 2; ctx.stroke(); }
        drawText(cells[k].l, x + 74, y + 20, 14, cells[k].c, 700, 136);
        drawText(String(cells[k].v), x + 74, y + 46, 24, '#fff', 900);
      }
    }

    // board
    ctx.save();
    if (flash > 0) { flash = Math.max(0, flash - dt * 2); }
    rr(BX - 14, BY - 14, CS * 3 + 28, CS * 3 + 28, 26);
    ctx.fillStyle = 'rgba(18,21,42,.9)'; ctx.fill();
    ctx.strokeStyle = 'rgba(124,92,255,' + (0.25 + flash * 0.6) + ')'; ctx.lineWidth = 2; ctx.stroke();
    ctx.restore();

    if (board) {
      // cursor / hover highlight
      var hl = hover >= 0 ? hover : (cursorVisible ? cursor : -1);
      if (humanCanPlay() && hl >= 0 && !board[hl]) {
        var hx = BX + (hl % 3) * CS, hy = BY + Math.floor(hl / 3) * CS;
        rr(hx + 10, hy + 10, CS - 20, CS - 20, 18); ctx.fillStyle = 'rgba(255,255,255,.05)'; ctx.fill();
        if (turn === 'X') drawX(hx + CS / 2, hy + CS / 2, CS, 1, 0.22); else drawO(hx + CS / 2, hy + CS / 2, CS, 1, 0.22);
      }
      if (cursorVisible && humanCanPlay()) {
        var cx0 = BX + (cursor % 3) * CS, cy0 = BY + Math.floor(cursor / 3) * CS;
        rr(cx0 + 8, cy0 + 8, CS - 16, CS - 16, 18); ctx.strokeStyle = 'rgba(34,211,238,' + (0.5 + 0.3 * Math.sin(now * 6)) + ')'; ctx.lineWidth = 3; ctx.stroke();
      }
    }

    // grid lines
    ctx.save(); ctx.strokeStyle = '#7c5cff'; ctx.shadowColor = '#7c5cff'; ctx.shadowBlur = 14; ctx.lineWidth = 6; ctx.lineCap = 'round';
    for (var li = 1; li < 3; li++) {
      ctx.beginPath(); ctx.moveTo(BX + li * CS, BY + 12); ctx.lineTo(BX + li * CS, BY + 3 * CS - 12); ctx.stroke();
      ctx.beginPath(); ctx.moveTo(BX + 12, BY + li * CS); ctx.lineTo(BX + 3 * CS - 12, BY + li * CS); ctx.stroke();
    }
    ctx.restore();

    if (board) {
      for (var i = 0; i < 9; i++) {
        if (!board[i]) continue;
        var mx = BX + (i % 3 + 0.5) * CS, my = BY + (Math.floor(i / 3) + 0.5) * CS;
        var dim = winInfo && winInfo.line && winInfo.line.indexOf(i) < 0 ? 0.35 : (winInfo && winInfo.p === 'draw' ? 0.55 : 1);
        var pulse = winInfo && winInfo.line && winInfo.line.indexOf(i) >= 0 ? 1 + 0.06 * Math.sin(now * 10) : 1;
        if (board[i] === 'X') drawX(mx, my, CS * pulse, marks[i], dim); else drawO(mx, my, CS * pulse, marks[i], dim);
      }
      if (winInfo && winInfo.line) {
        var a = winInfo.line[0], b = winInfo.line[2];
        var ax = BX + (a % 3 + 0.5) * CS, ay = BY + (Math.floor(a / 3) + 0.5) * CS, bx = BX + (b % 3 + 0.5) * CS, by = BY + (Math.floor(b / 3) + 0.5) * CS;
        var ex = ax - (bx - ax) * 0.18, ey = ay - (by - ay) * 0.18, fx = bx + (bx - ax) * 0.18, fy = by + (by - ay) * 0.18;
        var p = Math.min(1, winT / 0.35);
        ctx.save(); ctx.strokeStyle = '#fff'; ctx.shadowColor = COL[winInfo.p]; ctx.shadowBlur = 30; ctx.lineWidth = 10; ctx.lineCap = 'round';
        ctx.beginPath(); ctx.moveTo(ex, ey); ctx.lineTo(ex + (fx - ex) * p, ey + (fy - ey) * p); ctx.stroke(); ctx.restore();
      }
    }

    // particles
    if (game.state !== 'paused') {
      for (var pi = particles.length - 1; pi >= 0; pi--) {
        var pt = particles[pi]; pt.life -= dt; if (pt.life <= 0) { particles.splice(pi, 1); continue; }
        pt.x += pt.vx * dt; pt.y += pt.vy * dt; pt.vx *= 0.97; pt.vy = pt.vy * 0.97 + 300 * dt;
      }
    }
    particles.forEach(function (q) { ctx.globalAlpha = Math.max(0, q.life / q.max); ctx.fillStyle = q.c; ctx.beginPath(); ctx.arc(q.x, q.y, q.r, 0, Math.PI * 2); ctx.fill(); });
    ctx.globalAlpha = 1;

    // status
    var st = '', sc = '#eef0ff';
    if (board && game.state !== 'menu') {
      if (phase === 'end' || phase === 'done') {
        if (winInfo.p === 'draw') { st = TXT.draw; sc = '#b4b9d6'; }
        else if (isPvp()) { st = fmt(TXT.pWon, winInfo.p); sc = COL[winInfo.p]; }
        else if (winInfo.p === 'X') { st = TXT.youWon; sc = COL.X; } else { st = TXT.cpuWon; sc = COL.O; }
      } else if (phase === 'cpu') { st = TXT.thinking + ''; sc = COL.O; }
      else if (isPvp()) { st = fmt(TXT.turnOf, turn); sc = COL[turn]; }
      else { st = TXT.yourTurn; sc = COL.X; }
    }
    if (st) drawText(st, W / 2, 712, 26, sc, 900, W - 40);
  }

  // ---------------------------------------------------------------- input
  var cursorVisible = false;
  function cellAt(p) {
    if (p.x < BX || p.y < BY || p.x >= BX + 3 * CS || p.y >= BY + 3 * CS) return -1;
    return Math.floor((p.y - BY) / CS) * 3 + Math.floor((p.x - BX) / CS);
  }
  view.canvas.addEventListener('pointerdown', function (e) {
    e.preventDefault(); game.audio.ensure();
    var p = view.toLogical(e.clientX, e.clientY);
    for (var m = 0; m < 4; m++) { var r = modeRect(m); if (p.x >= r.x && p.x <= r.x + r.w && p.y >= r.y && p.y <= r.y + r.h) { setMode(m); return; } }
    var c = cellAt(p);
    if (c >= 0) { cursorVisible = false; cursor = c; humanPlace(c); }
  });
  view.canvas.addEventListener('pointermove', function (e) { if (e.pointerType === 'mouse') { hover = cellAt(view.toLogical(e.clientX, e.clientY)); if (hover >= 0) cursorVisible = false; } });
  view.canvas.addEventListener('pointerleave', function () { hover = -1; });
  game.on('keydown', function (e) {
    var k = e.key;
    if (k === 'm' || k === 'M') { setMode((modeIdx + 1) % 4); return; }
    if (game.state !== 'playing') return;
    if (k >= '1' && k <= '9' && k.length === 1) { cursor = Number(k) - 1; humanPlace(cursor); return; }
    var mv = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -3, ArrowDown: 3, a: -1, d: 1, w: -3, s: 3 }[k];
    if (mv !== undefined) {
      hover = -1;
      if (!cursorVisible) { cursorVisible = true; return; }
      var c = cursor % 3, r = Math.floor(cursor / 3);
      if (mv === -1) c = (c + 2) % 3; else if (mv === 1) c = (c + 1) % 3; else if (mv === -3) r = (r + 2) % 3; else r = (r + 1) % 3;
      cursor = r * 3 + c; game.audio.sfx('move'); return;
    }
    if (k === ' ' || k === 'Enter') { if (!cursorVisible) { cursorVisible = true; return; } humanPlace(cursor); }
  });

  game.on('start', newMatch);
  game.loop(update, render);
  game.showMenu();
  game.ready();
})();
