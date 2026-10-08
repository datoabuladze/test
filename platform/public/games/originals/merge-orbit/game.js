/* Merge Orbit – original sliding-merge number puzzle for Nebulo.
 * Deterministic: tile spawns come from the SDK's seeded RNG, and every move is recorded
 * (U/D/L/R) so the server can replay the run and verify the score. Keep this logic in sync
 * with App\Services\Verifiers\MergeOrbitVerifier.
 */
(function () {
  'use strict';
  var L = { en: 0, ka: 1, tr: 2, ru: 3 }[NebuloGame.lang] || 0;
  var TXT = {
    tagline: ['Slide the orbs, merge equal numbers and reach 2048.', 'გადაასრიალე სფეროები, გააერთიანე ტოლი რიცხვები და მიაღწიე 2048-ს.', 'Küreleri kaydır, eşit sayıları birleştir ve 2048’e ulaş.', 'Сдвигайте сферы, объединяйте одинаковые числа и доберитесь до 2048.'][L],
    how: [
      ['Arrow keys / WASD or swipe to slide all orbs.', 'Two equal orbs that touch merge into one.', 'Every merge adds its value to your score.', 'No moves left = game over.'],
      ['ისრები / WASD ან გასრიალება ყველა სფეროს გადასაადგილებლად.', 'ორი ტოლი სფერო ერთად ერთდება.', 'ყოველი გაერთიანება ქულას ამატებს.', 'სვლები აღარ დარჩა — თამაში სრულდება.'],
      ['Ok tuşları / WASD veya kaydırma ile tüm küreleri kaydır.', 'Değen iki eşit küre birleşir.', 'Her birleşme değeri kadar puan kazandırır.', 'Hamle kalmazsa oyun biter.'],
      ['Стрелки / WASD или свайп сдвигают все сферы.', 'Две одинаковые сферы при касании объединяются.', 'Каждое объединение добавляет очки.', 'Нет ходов — игра окончена.'],
    ][L],
    reached: ['You reached 2048! Keep going for a higher score.', 'მიაღწიე 2048-ს! გააგრძელე მეტი ქულისთვის.', '2048’e ulaştın! Daha yüksek puan için devam et.', 'Вы достигли 2048! Продолжайте ради рекорда.'][L],
    keep: ['Keep going', 'გაგრძელება', 'Devam et', 'Продолжить'][L],
  };

  var game = NebuloGame.create({ id: 'merge-orbit', title: 'Merge Orbit', tagline: TXT.tagline, howTo: TXT.how });
  var N = 4;
  var grid, score, moves, reached2048, tileEls = {}, nextId = 1, board, gridEl, cellPx, gapPx;

  var COLORS = { 2: '#334155', 4: '#3b4a6b', 8: '#0ea5e9', 16: '#06b6d4', 32: '#14b8a6', 64: '#22c55e', 128: '#84cc16',
    256: '#eab308', 512: '#f97316', 1024: '#ef4444', 2048: '#ec4899', 4096: '#a855f7', 8192: '#7c5cff' };

  // ---------------------------------------------------------------- pure logic (mirrored in PHP)
  function emptyGrid() { var g = []; for (var i = 0; i < N * N; i++) g.push(null); return g; }
  function spawn(g, rng) {
    var empty = []; for (var i = 0; i < N * N; i++) if (!g[i]) empty.push(i);
    if (!empty.length) return null;
    var idx = empty[Math.floor(rng() * empty.length)];
    var value = rng() < 0.9 ? 2 : 4;
    g[idx] = { v: value, id: nextId++, isNew: true };
    return idx;
  }
  // Returns {moved, gained}. dir: 'L','R','U','D'
  function slide(g, dir) {
    var moved = false, gained = 0;
    for (var line = 0; line < N; line++) {
      var idxs = [];
      for (var k = 0; k < N; k++) {
        var r, c;
        if (dir === 'L') { r = line; c = k; } else if (dir === 'R') { r = line; c = N - 1 - k; }
        else if (dir === 'U') { r = k; c = line; } else { r = N - 1 - k; c = line; }
        idxs.push(r * N + c);
      }
      var tiles = idxs.map(function (i) { return g[i]; }).filter(Boolean);
      var out = [];
      for (var t = 0; t < tiles.length; t++) {
        if (t + 1 < tiles.length && tiles[t].v === tiles[t + 1].v) {
          var merged = { v: tiles[t].v * 2, id: tiles[t].id, mergedFrom: tiles[t + 1].id, merged: true };
          gained += merged.v; out.push(merged); t++;
        } else { out.push({ v: tiles[t].v, id: tiles[t].id }); }
      }
      for (var p = 0; p < N; p++) {
        var before = g[idxs[p]]; var after = out[p] || null;
        if ((before ? before.v : 0) !== (after ? after.v : 0) || (before && after && before.id !== after.id)) moved = true;
        g[idxs[p]] = after;
      }
    }
    return { moved: moved, gained: gained };
  }
  function canMove(g) {
    for (var i = 0; i < N * N; i++) {
      if (!g[i]) return true;
      var r = Math.floor(i / N), c = i % N;
      if (c < N - 1 && g[i + 1] && g[i + 1].v === g[i].v) return true;
      if (r < N - 1 && g[i + N] && g[i + N].v === g[i].v) return true;
    }
    return false;
  }

  // ---------------------------------------------------------------- rendering
  function layout() {
    var rect = game.stage.getBoundingClientRect();
    var size = Math.max(220, Math.min(rect.width, rect.height) - 24);
    gapPx = Math.round(size * 0.025);
    cellPx = (size - gapPx * (N + 1)) / N;
    board.style.width = size + 'px'; board.style.height = size + 'px';
    gridEl.style.setProperty('--gap', gapPx + 'px');
    render(true);
  }
  function posOf(i) {
    var r = Math.floor(i / N), c = i % N;
    return 'translate(' + (gapPx + c * (cellPx + gapPx)) + 'px,' + (gapPx + r * (cellPx + gapPx)) + 'px)';
  }
  function render(instant) {
    var seen = {};
    for (var i = 0; i < N * N; i++) {
      var t = grid && grid[i]; if (!t) continue;
      seen[t.id] = true;
      var e = tileEls[t.id];
      if (!e) { e = document.createElement('div'); e.className = 'mo-tile'; board.appendChild(e); tileEls[t.id] = e; }
      var pos = posOf(i);
      e.style.setProperty('--pos', pos);
      e.style.transform = pos;
      e.style.width = cellPx + 'px'; e.style.height = cellPx + 'px';
      var color = COLORS[t.v] || '#7c5cff';
      e.style.background = 'radial-gradient(circle at 30% 25%, ' + color + ', color-mix(in oklab, ' + color + ' 55%, #0b0d17))';
      e.style.boxShadow = t.v >= 128 ? '0 0 ' + Math.min(40, Math.log2(t.v) * 3) + 'px ' + color : 'none';
      e.style.fontSize = (cellPx * (t.v >= 1024 ? 0.3 : t.v >= 128 ? 0.36 : 0.44)) + 'px';
      e.textContent = t.v;
      e.classList.remove('new', 'merged');
      if (!instant && t.isNew) { void e.offsetWidth; e.classList.add('new'); }
      if (!instant && t.merged) { void e.offsetWidth; e.classList.add('merged'); }
      if (instant) e.style.transition = 'none'; else e.style.transition = '';
    }
    for (var id in tileEls) {
      if (!seen[id]) { tileEls[id].remove(); delete tileEls[id]; }
    }
  }

  // ---------------------------------------------------------------- game flow
  function newGame() {
    for (var id in tileEls) tileEls[id].remove();
    tileEls = {}; nextId = 1;
    grid = emptyGrid(); score = 0; moves = ''; reached2048 = false;
    spawn(grid, game.rng); spawn(grid, game.rng);
    game.setStat('score', 0); game.setStat('best', game.best());
    render(false);
  }
  function move(dir) {
    if (game.state !== 'playing') return;
    grid.forEach(function (t) { if (t) { t.isNew = false; t.merged = false; } });
    var res = slide(grid, dir);
    if (!res.moved) return;
    moves += dir;
    score += res.gained;
    spawn(grid, game.rng);
    game.setStat('score', score);
    game.audio.sfx(res.gained ? 'point' : 'move');
    render(false);
    if (!reached2048 && grid.some(function (t) { return t && t.v >= 2048; })) {
      reached2048 = true;
      game.audio.sfx('win');
      game.state = 'paused'; game._pausedAt = performance.now();
      game.overlay({ title: '2048!', lines: [TXT.reached], actions: [{ label: TXT.keep, onClick: function () { game.resume(); } }] });
      return;
    }
    if (!canMove(grid)) {
      setTimeout(function () { game.over({ score: score, evidence: { moves: moves, v: 1 } }); }, 250);
    }
  }

  board = document.createElement('div'); board.id = 'board';
  gridEl = document.createElement('div'); gridEl.className = 'mo-grid';
  gridEl.style.position = 'absolute'; gridEl.style.inset = '0';
  for (var i = 0; i < N * N; i++) { var c = document.createElement('div'); c.className = 'mo-cell'; gridEl.appendChild(c); }
  board.appendChild(gridEl);
  game.stage.appendChild(board);

  var KEYMAP = { ArrowLeft: 'L', ArrowRight: 'R', ArrowUp: 'U', ArrowDown: 'D', a: 'L', d: 'R', w: 'U', s: 'D', A: 'L', D: 'R', W: 'U', S: 'D' };
  game.on('keydown', function (e) { if (KEYMAP[e.key]) move(KEYMAP[e.key]); });
  game.onSwipe(game.stage, function (d) { move({ left: 'L', right: 'R', up: 'U', down: 'D' }[d]); });
  game.on('start', newGame);
  window.addEventListener('resize', layout);
  if (window.ResizeObserver) new ResizeObserver(layout).observe(game.stage);

  grid = emptyGrid();
  layout();
  game.showMenu();
  game.ready();
})();
