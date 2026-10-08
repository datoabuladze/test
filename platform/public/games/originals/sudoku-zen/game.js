/* Sudoku Zen – original Sudoku for Nebulo.
 * Puzzles are generated in the browser from the round's seeded RNG: a random full grid is built by
 * randomized backtracking, then clues are removed one by one while a solution counter confirms the
 * puzzle still has exactly one solution.
 */
(function () {
  'use strict';
  var L = { en: 0, ka: 1, tr: 2, ru: 3 }[NebuloGame.lang] || 0;
  var TXT = {
    tagline: ['Fill the grid so every row, column and 3×3 box holds 1–9 exactly once.', 'შეავსე ბადე ისე, რომ ყველა სტრიქონში, სვეტსა და 3×3 კვადრატში 1–9 ზუსტად ერთხელ იყოს.', 'Izgarayı doldur: her satır, sütun ve 3×3 kutuda 1–9 tam bir kez bulunsun.', 'Заполните сетку так, чтобы в каждой строке, столбце и квадрате 3×3 цифры 1–9 встречались ровно один раз.'][L],
    how: [
      ['Select a cell, then tap a number on the pad (or press 1–9).', 'Notes mode (N) lets you pencil in candidates.', 'Clashing numbers glow red – fix them to finish.', 'Every puzzle has exactly one solution.', 'Faster solves on harder levels score more.'],
      ['აირჩიე უჯრა და შეეხე ციფრს პანელზე (ან დააჭირე 1–9).', 'შენიშვნების რეჟიმი (N) კანდიდატების ჩასაწერად.', 'ერთმანეთთან კონფლიქტში მყოფი ციფრები წითლად ანათებს.', 'ყოველ თავსატეხს ზუსტად ერთი ამოხსნა აქვს.', 'რთულ დონეზე სწრაფი ამოხსნა მეტ ქულას იძლევა.'],
      ['Bir hücre seç, sonra paneldeki bir sayıya dokun (veya 1–9’a bas).', 'Not modu (N) ile adayları karalayabilirsin.', 'Çakışan sayılar kırmızı parlar – bitirmek için düzelt.', 'Her bulmacanın tek bir çözümü vardır.', 'Zor seviyelerde hızlı çözüm daha çok puan getirir.'],
      ['Выберите клетку и нажмите цифру на панели (или клавиши 1–9).', 'Режим заметок (N) — для записи кандидатов.', 'Конфликтующие цифры подсвечиваются красным.', 'У каждой головоломки ровно одно решение.', 'Быстрое решение на высокой сложности даёт больше очков.'],
    ][L],
    difficulty: ['Difficulty', 'სირთულე', 'Zorluk', 'Сложность'][L],
    levels: [['Easy', 'Medium', 'Hard'], ['მარტივი', 'საშუალო', 'რთული'], ['Kolay', 'Orta', 'Zor'], ['Легко', 'Средне', 'Сложно']][L],
    notes: ['Notes', 'შენიშვნა', 'Not', 'Заметки'][L],
    erase: ['Erase', 'წაშლა', 'Sil', 'Стереть'][L],
    undo: ['Undo', 'დაბრუნება', 'Geri al', 'Отмена'][L],
    solved: function (lv, t) { return [lv + ' puzzle solved in ' + t, lv + ' თავსატეხი ამოხსნილია: ' + t, lv + ' bulmaca çözüldü: ' + t, 'Уровень «' + lv + '» решён за ' + t][L]; },
    clues: function (n) { return ['Starting clues: ' + n, 'საწყისი ციფრები: ' + n, 'Başlangıç ipuçları: ' + n, 'Подсказок в начале: ' + n][L]; },
  };
  var game = NebuloGame.create({ id: 'sudoku-zen', title: 'Sudoku Zen', tagline: TXT.tagline, howTo: TXT.how });

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

  // ---------------------------------------------------------------- geometry
  var RW = [], CL = [], BX = [], PEERS = [];
  (function () {
    for (var i = 0; i < 81; i++) { RW[i] = (i / 9) | 0; CL[i] = i % 9; BX[i] = ((RW[i] / 3) | 0) * 3 + ((CL[i] / 3) | 0); }
    for (var a = 0; a < 81; a++) {
      PEERS[a] = [];
      for (var b = 0; b < 81; b++) if (a !== b && (RW[a] === RW[b] || CL[a] === CL[b] || BX[a] === BX[b])) PEERS[a].push(b);
    }
  })();
  function bitCount(m) { var n = 0; while (m) { m &= m - 1; n++; } return n; }

  // ---------------------------------------------------------------- solver / generator
  /** Backtracking with bitmasks + minimum-remaining-values. Returns number of solutions (capped at limit).
   *  If order (array of digits) is given, digits are tried in that order and the first solution is left in g. */
  function solve(g, limit, rng) {
    var rows = [0, 0, 0, 0, 0, 0, 0, 0, 0], cols = rows.slice(), boxes = rows.slice();
    for (var i = 0; i < 81; i++) if (g[i]) {
      var bit = 1 << g[i];
      if ((rows[RW[i]] | cols[CL[i]] | boxes[BX[i]]) & bit) return 0;
      rows[RW[i]] |= bit; cols[CL[i]] |= bit; boxes[BX[i]] |= bit;
    }
    var count = 0;
    function rec() {
      var best = -1, bestMask = 0, bestN = 10;
      for (var k = 0; k < 81; k++) {
        if (g[k]) continue;
        var m = ~(rows[RW[k]] | cols[CL[k]] | boxes[BX[k]]) & 0x3FE;
        var n = bitCount(m);
        if (n < bestN) { best = k; bestMask = m; bestN = n; if (n <= 1) break; }
      }
      if (best < 0) { count++; return true; }
      if (bestN === 0) return false;
      var digits = [];
      for (var d = 1; d <= 9; d++) if (bestMask & (1 << d)) digits.push(d);
      if (rng) for (var s = digits.length - 1; s > 0; s--) { var j = Math.floor(rng() * (s + 1)); var t = digits[s]; digits[s] = digits[j]; digits[j] = t; }
      for (var q = 0; q < digits.length; q++) {
        var dd = digits[q], b2 = 1 << dd;
        g[best] = dd; rows[RW[best]] |= b2; cols[CL[best]] |= b2; boxes[BX[best]] |= b2;
        var stop = rec() && rng; // when generating, keep the first full grid
        if (stop) return true;
        g[best] = 0; rows[RW[best]] &= ~b2; cols[CL[best]] &= ~b2; boxes[BX[best]] &= ~b2;
        if (count >= limit) return true;
      }
      return false;
    }
    rec();
    return count;
  }
  var TARGET_CLUES = [38, 31, 25];
  function generate(level, rng) {
    var full = []; for (var i = 0; i < 81; i++) full.push(0);
    solve(full, 1, rng);
    var puzzle = full.slice();
    var order = []; for (var k = 0; k < 41; k++) order.push(k);
    for (var s = order.length - 1; s > 0; s--) { var j = Math.floor(rng() * (s + 1)); var t = order[s]; order[s] = order[j]; order[j] = t; }
    var clues = 81;
    // remove symmetric pairs (k, 80-k) while the puzzle stays unique
    for (var o = 0; o < order.length && clues > TARGET_CLUES[level]; o++) {
      var a = order[o], b = 80 - a;
      var va = puzzle[a], vb = puzzle[b];
      puzzle[a] = 0; puzzle[b] = 0;
      if (solve(puzzle.slice(), 2, null) === 1) clues -= (a === b ? 1 : 2);
      else { puzzle[a] = va; puzzle[b] = vb; }
    }
    // second pass: single cells, for the sparser levels
    if (clues > TARGET_CLUES[level]) {
      var singles = []; for (var q = 0; q < 81; q++) if (puzzle[q]) singles.push(q);
      for (var s2 = singles.length - 1; s2 > 0; s2--) { var j2 = Math.floor(rng() * (s2 + 1)); var t2 = singles[s2]; singles[s2] = singles[j2]; singles[j2] = t2; }
      for (var u = 0; u < singles.length && clues > TARGET_CLUES[level]; u++) {
        var cIdx = singles[u], cv = puzzle[cIdx];
        puzzle[cIdx] = 0;
        if (solve(puzzle.slice(), 2, null) === 1) clues--; else puzzle[cIdx] = cv;
      }
    }
    return { puzzle: puzzle, solution: full, clues: clues };
  }

  // ---------------------------------------------------------------- state
  var BASE = [1000, 2000, 3500], TARGET_SEC = [600, 1200, 1800], PER_SEC = [2, 3, 4];
  var diff = game.store.get('sudoku-zen.diff', 0); if (!(diff >= 0 && diff <= 2)) diff = 0;
  var level = diff, given = [], val = [], notes = [], sol = [], clueCount = 0, sel = 40, notesMode = false, undoStack = [], lastSec = -1, liveScore = 0, finished = false;
  for (var z = 0; z < 81; z++) { given.push(false); val.push(0); notes.push(0); }

  // ---------------------------------------------------------------- DOM
  var wrap = document.createElement('div'); wrap.id = 'sz-wrap'; game.stage.appendChild(wrap);
  var boardEl = document.createElement('div'); boardEl.id = 'sz-board'; wrap.appendChild(boardEl);
  var padEl = document.createElement('div'); padEl.id = 'sz-pad'; wrap.appendChild(padEl);
  var cellEls = [];
  for (var ci = 0; ci < 81; ci++) (function (i) {
    var c = document.createElement('div');
    c.className = 'sz-cell c' + CL[i] + ' r' + RW[i] + (BX[i] % 2 === 1 ? ' alt' : '');
    c.addEventListener('pointerdown', function (e) { e.preventDefault(); if (game.state !== 'playing' || finished) return; sel = i; game.audio.sfx('tick'); render(); });
    boardEl.appendChild(c); cellEls.push(c);
  })(ci);
  var digitBtns = [];
  var ICON = {
    undo: '<svg viewBox="0 0 24 24"><path d="M9 14 4 9l5-5"/><path d="M4 9h11a5 5 0 0 1 0 10h-3"/></svg>',
    erase: '<svg viewBox="0 0 24 24"><path d="M20 20H9L4 15l10-10 7 7-6 6"/><path d="m9 10 6 6"/></svg>',
    notes: '<svg viewBox="0 0 24 24"><path d="M4 20h4L19 9l-4-4L4 16z"/><path d="m13 7 4 4"/></svg>',
  };
  function padButton(cls, html, onTap) {
    var b = document.createElement('button'); b.type = 'button'; b.className = cls; b.innerHTML = html;
    b.addEventListener('pointerdown', function (e) { e.preventDefault(); game.audio.ensure(); if (game.state === 'playing' && !finished) onTap(); });
    padEl.appendChild(b); return b;
  }
  for (var dg = 1; dg <= 9; dg++) (function (d) { digitBtns[d] = padButton('dig', d + '<small></small>', function () { input(d); }); })(dg);
  var undoBtn = padButton('tool', ICON.undo + '<span>' + TXT.undo + '</span>', undo);
  var eraseBtn = padButton('tool', ICON.erase + '<span>' + TXT.erase + '</span>', erase);
  var notesBtn = padButton('tool', ICON.notes + '<span>' + TXT.notes + '</span>', toggleNotes);

  // particles (cosmetic only)
  var fx = (function () {
    var cv = document.createElement('canvas'); cv.className = 'fx'; game.stage.appendChild(cv);
    var ctx = cv.getContext('2d'); var parts = []; var running = false;
    function size() {
      var r = game.stage.getBoundingClientRect(); var d = Math.min(window.devicePixelRatio || 1, 2);
      cv.width = Math.round(r.width * d); cv.height = Math.round(r.height * d);
      cv.style.width = r.width + 'px'; cv.style.height = r.height + 'px'; ctx.setTransform(d, 0, 0, d, 0, 0);
    }
    function tick() {
      var r = game.stage.getBoundingClientRect(); ctx.clearRect(0, 0, r.width, r.height);
      for (var i = parts.length - 1; i >= 0; i--) {
        var p = parts[i]; p.life -= 1 / 60;
        if (p.life <= 0) { parts.splice(i, 1); continue; }
        p.x += p.vx; p.y += p.vy; p.vy += 0.1; p.vx *= 0.97;
        ctx.globalAlpha = Math.min(1, p.life * 1.5); ctx.fillStyle = p.color;
        ctx.beginPath(); ctx.arc(p.x, p.y, p.size * p.life, 0, Math.PI * 2); ctx.fill();
      }
      ctx.globalAlpha = 1;
      if (parts.length) requestAnimationFrame(tick); else running = false;
    }
    window.addEventListener('resize', size); size();
    return {
      size: size,
      burst: function (x, y, color, n) {
        for (var i = 0; i < n; i++) { var a = Math.random() * 6.283, s = 1 + Math.random() * 4; parts.push({ x: x, y: y, vx: Math.cos(a) * s, vy: Math.sin(a) * s - 1.5, size: 2 + Math.random() * 3, life: 0.7 + Math.random() * 0.5, color: color }); }
        if (!running) { running = true; requestAnimationFrame(tick); }
      },
    };
  })();
  function cellCenter(i) { var r = cellEls[i].getBoundingClientRect(), s = game.stage.getBoundingClientRect(); return { x: r.left - s.left + r.width / 2, y: r.top - s.top + r.height / 2 }; }

  // ---------------------------------------------------------------- layout
  function layout() {
    var r = game.stage.getBoundingClientRect(); if (!r.width || !r.height) return;
    var pad = 12, gap = Math.max(10, Math.min(22, Math.min(r.width, r.height) * 0.025));
    var land = r.width > r.height * 1.12;
    wrap.classList.toggle('port', !land); wrap.style.setProperty('--gap', gap + 'px');
    var board, bw, bh;
    if (land) {
      board = Math.min(r.height - pad * 2, (r.width - pad * 2 - gap) * 0.68);
      bw = Math.min(board * 0.52, r.width - pad * 2 - gap - board);
      var b3 = (bw - 16) / 3;
      padEl.style.gridTemplateColumns = 'repeat(3, ' + b3 + 'px)';
      padEl.style.gridAutoRows = Math.min(b3, (board - 24) / 4.2) + 'px';
      digitBtns.forEach(function (b) { if (b) b.style.fontSize = Math.min(b3, 80) * 0.42 + 'px'; });
      [undoBtn, eraseBtn, notesBtn].forEach(function (b) { b.style.gridColumn = 'auto'; });
    } else {
      board = Math.min(r.width - pad * 2, (r.height - pad * 2 - gap) * 0.7);
      var pw = Math.min(r.width - pad * 2, board * 1.02);
      var colW = (pw - 8 * 8) / 9;
      bh = Math.min(64, Math.max(36, (r.height - pad * 2 - gap - board - 8) / 2));
      padEl.style.gridTemplateColumns = 'repeat(9, ' + colW + 'px)';
      padEl.style.gridAutoRows = bh + 'px';
      digitBtns.forEach(function (b) { if (b) b.style.fontSize = Math.min(colW, bh) * 0.5 + 'px'; });
      undoBtn.style.gridColumn = 'span 3'; eraseBtn.style.gridColumn = 'span 3'; notesBtn.style.gridColumn = 'span 3';
    }
    board = Math.floor(board);
    boardEl.style.width = board + 'px'; boardEl.style.height = board + 'px';
    var cell = board / 9;
    boardEl.style.fontSize = (cell * 0.56) + 'px';
    boardEl.style.setProperty('--note', (cell * 0.25) + 'px');
    cellEls.forEach(function (c) { var n = c.querySelector('.sz-notes'); if (n) n.style.fontSize = (cell * 0.25) + 'px'; });
    fx.size();
  }

  // ---------------------------------------------------------------- rendering
  function conflicts() {
    var bad = [];
    for (var i = 0; i < 81; i++) {
      bad[i] = false;
      if (!val[i]) continue;
      for (var k = 0; k < PEERS[i].length; k++) if (val[PEERS[i][k]] === val[i]) { bad[i] = true; break; }
    }
    return bad;
  }
  function render(popIdx) {
    var bad = conflicts(); var sv = val[sel]; var cell = parseFloat(boardEl.style.width) / 9 || 40;
    var counts = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
    for (var i = 0; i < 81; i++) {
      var c = cellEls[i];
      if (val[i]) counts[val[i]]++;
      var cls = 'sz-cell c' + CL[i] + ' r' + RW[i] + (BX[i] % 2 === 1 ? ' alt' : '') + (given[i] ? ' given' : '');
      if (game.state !== 'menu') {
        if (i === sel) cls += ' sel';
        else if (sv && val[i] === sv) cls += ' same';
        else if (RW[i] === RW[sel] || CL[i] === CL[sel] || BX[i] === BX[sel]) cls += ' peer';
      }
      if (bad[i]) cls += ' bad';
      if (i === popIdx) cls += ' pop';
      if (c.className.indexOf('flash') >= 0) cls += ' flash';
      c.className = cls;
      if (val[i]) {
        c.innerHTML = '<span class="sz-v">' + val[i] + '</span>';
      } else if (notes[i]) {
        var h = '<div class="sz-notes" style="font-size:' + (cell * 0.25) + 'px">';
        for (var d = 1; d <= 9; d++) h += '<span' + (sv === d ? ' class="hl"' : '') + '>' + (notes[i] & (1 << d) ? d : '') + '</span>';
        c.innerHTML = h + '</div>';
      } else c.innerHTML = '';
    }
    for (var dg = 1; dg <= 9; dg++) {
      digitBtns[dg].classList.toggle('done', counts[dg] >= 9);
      digitBtns[dg].querySelector('small').textContent = Math.max(0, 9 - counts[dg]);
    }
    notesBtn.classList.toggle('on', notesMode);
  }

  // ---------------------------------------------------------------- input
  function snapshot(idxs) { return idxs.map(function (i) { return { i: i, v: val[i], n: notes[i] }; }); }
  function input(d) {
    if (game.state !== 'playing' || given[sel]) { if (given[sel]) game.audio.sfx('tick'); return; }
    var i = sel;
    if (notesMode) {
      if (val[i]) return;
      undoStack.push(snapshot([i]));
      notes[i] ^= (1 << d);
      game.audio.tone(900, 0.03, 'triangle', 0.06);
      render(); return;
    }
    if (val[i] === d) { undoStack.push(snapshot([i])); val[i] = 0; game.audio.sfx('move'); render(); return; }
    var touched = [i].concat(PEERS[i].filter(function (p) { return notes[p] & (1 << d); }));
    undoStack.push(snapshot(touched));
    val[i] = d; notes[i] = 0;
    PEERS[i].forEach(function (p) { notes[p] &= ~(1 << d); });
    var bad = conflicts();
    if (bad[i]) { game.audio.sfx('hit'); }
    else {
      var units = completedUnits(i);
      if (units.length) {
        game.audio.sfx('point');
        units.forEach(function (u) { u.forEach(function (k, n) { setTimeout(function () { var c = cellEls[k]; c.classList.remove('flash'); void c.offsetWidth; c.classList.add('flash'); setTimeout(function () { c.classList.remove('flash'); }, 650); }, n * 30); }); });
        var p = cellCenter(i); fx.burst(p.x, p.y, '#f472b6', 18);
      } else game.audio.sfx('click');
    }
    render(i);
    checkWin();
  }
  function completedUnits(i) {
    var out = [];
    [RW, CL, BX].forEach(function (arr) {
      var unit = []; for (var k = 0; k < 81; k++) if (arr[k] === arr[i]) unit.push(k);
      var seen = 0, ok = true;
      unit.forEach(function (k) { if (!val[k] || (seen & (1 << val[k]))) ok = false; seen |= 1 << val[k]; });
      if (ok) out.push(unit);
    });
    return out;
  }
  function erase() {
    if (game.state !== 'playing' || given[sel] || (!val[sel] && !notes[sel])) return;
    undoStack.push(snapshot([sel])); val[sel] = 0; notes[sel] = 0; game.audio.sfx('move'); render();
  }
  function undo() {
    if (game.state !== 'playing' || !undoStack.length) return;
    var snap = undoStack.pop();
    snap.forEach(function (s) { val[s.i] = s.v; notes[s.i] = s.n; });
    sel = snap[0].i; game.audio.tone(440, 0.05, 'triangle', 0.08, 300); render();
  }
  function toggleNotes() { notesMode = !notesMode; game.audio.sfx('tick'); render(); }

  function scoreNow(sec) { return BASE[level] + Math.max(0, TARGET_SEC[level] - sec) * PER_SEC[level]; }
  function fmtTime(sec) { sec = Math.max(0, Math.floor(sec)); var m = Math.floor(sec / 60); return (m >= 60 ? Math.floor(m / 60) + ':' + ('0' + (m % 60)).slice(-2) : m) + ':' + ('0' + (sec % 60)).slice(-2); }

  function checkWin() {
    for (var i = 0; i < 81; i++) if (!val[i]) return;
    var bad = conflicts(); for (var k = 0; k < 81; k++) if (bad[k]) return;
    finished = true;
    var sec = Math.floor(game.elapsed() / 1000);
    var score = scoreNow(sec);
    game.setStat('score', score);
    // celebratory wave
    for (var w = 0; w < 81; w++) (function (j) { setTimeout(function () { cellEls[j].classList.add('flash'); }, (RW[j] + CL[j]) * 35); })(w);
    var s = game.stage.getBoundingClientRect();
    for (var b = 0; b < 5; b++) (function (b) { setTimeout(function () { fx.burst(s.width * (0.2 + b * 0.15), s.height * 0.4, ['#22d3ee', '#7c5cff', '#f472b6'][b % 3], 30); }, b * 100); })(b);
    later(function () {
      game.over({ score: score, win: true, lines: [TXT.solved(TXT.levels[level], fmtTime(sec)), TXT.clues(clueCount)], evidence: { level: level, sec: sec } });
      addDiffPicker();
    }, 700);
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
        diff = i; game.store.set('sudoku-zen.diff', i); game.audio.sfx('click');
        Array.prototype.forEach.call(row.children, function (c, k) { c.classList.toggle('on', k === i); });
      });
      row.appendChild(b);
    });
    card.insertBefore(w, acts);
  }

  game.on('keydown', function (e) {
    if (game.state !== 'playing' || finished) return;
    var k = e.key, code = e.code || '';
    var m = /^(Digit|Numpad)([1-9])$/.exec(code);
    var r = RW[sel], c = CL[sel];
    if (m || /^[1-9]$/.test(k)) {
      var d = m ? +m[2] : +k;
      if (e.shiftKey && !notesMode) { notesMode = true; input(d); notesMode = false; render(); } else input(d);
      return;
    }
    if (k === 'ArrowLeft') c = (c + 8) % 9; else if (k === 'ArrowRight') c = (c + 1) % 9;
    else if (k === 'ArrowUp') r = (r + 8) % 9; else if (k === 'ArrowDown') r = (r + 1) % 9;
    else if (k === 'Backspace' || k === 'Delete' || k === '0' || code === 'Digit0' || code === 'Numpad0') { erase(); return; }
    else if (k === 'n' || k === 'N' || k === ' ') { toggleNotes(); return; }
    else if (k === 'z' || k === 'Z' || k === 'u' || k === 'U') { undo(); return; }
    else return;
    sel = r * 9 + c; game.audio.sfx('tick'); render();
  });

  // ---------------------------------------------------------------- flow
  function newGame() {
    level = diff;
    var g = generate(level, game.rng);
    sol = g.solution; clueCount = g.clues;
    for (var i = 0; i < 81; i++) { val[i] = g.puzzle[i]; given[i] = !!g.puzzle[i]; notes[i] = 0; cellEls[i].classList.remove('flash'); }
    sel = 40; for (var k = 40; k < 81; k++) if (!given[k]) { sel = k; break; }
    notesMode = false; undoStack = []; lastSec = -1; finished = false;
    boardEl.classList.remove('blur'); padEl.classList.remove('blur');
    liveScore = scoreNow(0);
    game.setStat('score', liveScore);
    game.setStat('level', TXT.levels[level]);
    game.setStat('time', '0:00');
    layout(); render();
  }
  game.on('start', newGame);
  game.on('pause', function () { boardEl.classList.add('blur'); padEl.classList.add('blur'); });
  game.on('resume', function () { boardEl.classList.remove('blur'); padEl.classList.remove('blur'); });

  game.loop(function () {}, function () {
    if (game.state !== 'playing' || finished) return;
    var sec = Math.floor(game.elapsed() / 1000);
    if (sec !== lastSec) {
      lastSec = sec; game.setStat('time', fmtTime(sec));
      var s = scoreNow(sec); if (s !== liveScore) { liveScore = s; game.setStat('score', s); }
    }
  });

  window.addEventListener('resize', layout);
  if (window.ResizeObserver) new ResizeObserver(layout).observe(game.stage);

  // decorative preview board behind the menu
  (function () {
    var demo = generate(0, game.mulberry32(20261008));
    for (var i = 0; i < 81; i++) { val[i] = demo.puzzle[i]; given[i] = !!val[i]; }
  })();
  layout(); render();
  game.showMenu();
  addDiffPicker();
  game.ready();
})();
