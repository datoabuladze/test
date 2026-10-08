/* Neon Snake – original grid snake for Nebulo.
 * Eat glowing orbs to grow, every orb speeds you up, level-ups drop crystal blocks on the board.
 * Hitting a wall, a crystal or your own tail ends the run.
 */
(function () {
  'use strict';
  var L = { en: 0, ka: 1, tr: 2, ru: 3 }[NebuloGame.lang] || 0;
  var TXT = {
    tagline: ['Steer the light-serpent, swallow orbs and outrun your own tail.', 'მართე სინათლის გველი, შთანთქე სფეროები და გაასწარი საკუთარ კუდს.', 'Işık yılanını yönlendir, küreleri yut ve kendi kuyruğundan kaç.', 'Управляйте световой змейкой, глотайте сферы и не врежьтесь в собственный хвост.'][L],
    how: [
      ['Arrow keys / WASD, swipe, or the on-screen pad to turn.', 'Cyan orbs make you longer and faster; golden stars are worth 5× but fade away.', 'Every 5 orbs the level rises and a crystal block appears.', 'Walls, crystals or your own body end the run.'],
      ['მოსახვევად: ისრები / WASD, გასრიალება ან ეკრანის პულტი.', 'ცისფერი სფეროები გაგრძელებს და გაგაჩქარებს; ოქროს ვარსკვლავი 5-ჯერ მეტს იძლევა, მაგრამ ქრება.', 'ყოველ 5 სფეროზე დონე იზრდება და ჩნდება კრისტალის ბლოკი.', 'კედელი, კრისტალი ან საკუთარი სხეული თამაშს ასრულებს.'],
      ['Dönmek için ok tuşları / WASD, kaydırma veya ekran pedi.', 'Camgöbeği küreler seni uzatır ve hızlandırır; altın yıldızlar 5 kat değerlidir ama kaybolur.', 'Her 5 kürede seviye artar ve bir kristal blok belirir.', 'Duvar, kristal ya da kendi gövden oyunu bitirir.'],
      ['Повороты: стрелки / WASD, свайп или экранный джойстик.', 'Бирюзовые сферы удлиняют и ускоряют змейку; золотые звёзды дают в 5 раз больше, но исчезают.', 'Каждые 5 сфер растёт уровень и появляется кристальный блок.', 'Стена, кристалл или собственное тело — конец забега.'],
    ][L],
    length: ['Length', 'სიგრძე', 'Uzunluk', 'Длина'][L],
    orbs: ['Orbs eaten', 'შთანთქმული სფეროები', 'Yenen küreler', 'Съедено сфер'][L],
  };

  var game = NebuloGame.create({ id: 'neon-snake', title: 'Neon Snake', tagline: TXT.tagline, howTo: TXT.how });
  var N = 20, CELL = 30, W = N * CELL, H = N * CELL;
  var view = game.canvas(W, H);
  var ctx = view.ctx;

  var snake, prev, dir, queue, orb, star, crystals, score, eaten, level, acc, interval, dying, particles = [], shake = 0, pulse = 0, floaters = [];
  var DIRS = { up: { x: 0, y: -1 }, down: { x: 0, y: 1 }, left: { x: -1, y: 0 }, right: { x: 1, y: 0 } };

  function occupied(x, y) {
    for (var i = 0; i < snake.length; i++) if (snake[i].x === x && snake[i].y === y) return true;
    for (var j = 0; j < crystals.length; j++) if (crystals[j].x === x && crystals[j].y === y) return true;
    if (orb && orb.x === x && orb.y === y) return true;
    if (star && star.x === x && star.y === y) return true;
    return false;
  }
  function freeCell(avoidHead) {
    var free = [];
    var h = snake[0];
    for (var y = 0; y < N; y++) for (var x = 0; x < N; x++) {
      if (occupied(x, y)) continue;
      if (avoidHead && Math.abs(x - h.x) + Math.abs(y - h.y) < 4) continue;
      free.push({ x: x, y: y });
    }
    if (!free.length) return null;
    return free[Math.floor(game.rng() * free.length)];
  }

  function reset() {
    snake = [{ x: 8, y: 10 }, { x: 7, y: 10 }, { x: 6, y: 10 }, { x: 5, y: 10 }];
    prev = snake.map(function (s) { return { x: s.x, y: s.y }; });
    dir = 'right'; queue = []; crystals = []; star = null; orb = null;
    score = 0; eaten = 0; level = 1; acc = 0; interval = 0.14; dying = 0; shake = 0;
    particles = []; floaters = [];
    orb = freeCell(true);
    game.setStat('score', 0); game.setStat('length', snake.length, TXT.length); game.setStat('level', 1);
  }

  function burst(cx, cy, color, n, speed) {
    for (var i = 0; i < n; i++) {
      var a = Math.random() * Math.PI * 2, v = (0.3 + Math.random()) * (speed || 160);
      particles.push({ x: cx, y: cy, vx: Math.cos(a) * v, vy: Math.sin(a) * v, life: 0.5 + Math.random() * 0.5, max: 1, color: color, r: 1.5 + Math.random() * 2.5 });
    }
  }

  function turn(d) {
    if (game.state !== 'playing' || dying) return;
    var last = queue.length ? queue[queue.length - 1] : dir;
    var a = DIRS[last], b = DIRS[d];
    if (!b || (a.x + b.x === 0 && a.y + b.y === 0) || last === d) return;
    if (queue.length < 3) queue.push(d);
  }

  function die() {
    dying = 0.9; shake = 14;
    game.audio.sfx('explode');
    snake.forEach(function (s, i) { if (i % 2 === 0) burst(s.x * CELL + CELL / 2, s.y * CELL + CELL / 2, i ? '#7c5cff' : '#22d3ee', 6, 200); });
  }

  function step() {
    if (queue.length) dir = queue.shift();
    var d = DIRS[dir], h = snake[0];
    var nx = h.x + d.x, ny = h.y + d.y;
    var eatsOrb = orb && orb.x === nx && orb.y === ny;
    var eatsStar = star && star.x === nx && star.y === ny;
    var grows = eatsOrb;
    if (nx < 0 || ny < 0 || nx >= N || ny >= N) return die();
    for (var i = 0; i < snake.length - (grows ? 0 : 1); i++) if (snake[i].x === nx && snake[i].y === ny) return die();
    for (var j = 0; j < crystals.length; j++) if (crystals[j].x === nx && crystals[j].y === ny) return die();

    prev = snake.map(function (s) { return { x: s.x, y: s.y }; });
    snake.unshift({ x: nx, y: ny });
    if (!grows) snake.pop();
    var px = nx * CELL + CELL / 2, py = ny * CELL + CELL / 2;
    if (eatsOrb) {
      eaten++;
      var gain = 10 * level;
      score += gain;
      floaters.push({ x: px, y: py, text: '+' + gain, life: 0.9, color: '#22d3ee' });
      burst(px, py, '#22d3ee', 18, 180);
      game.audio.sfx('point');
      interval = Math.max(0.055, 0.14 - eaten * 0.0022);
      orb = null;
      if (eaten % 5 === 0) {
        level++;
        game.setStat('level', level);
        var c = freeCell(true);
        if (c) { crystals.push(c); burst(c.x * CELL + CELL / 2, c.y * CELL + CELL / 2, '#f472b6', 22, 140); }
        game.audio.sfx('bonus');
      }
      if (!star && eaten % 4 === 0 && game.rng() < 0.75) {
        var sc = freeCell(true);
        if (sc) { star = sc; star.t = 7; }
      }
      orb = freeCell(true);
      if (!orb && !star) {
        // Board completely filled – a perfect run.
        game.setStat('score', score);
        game.over({ score: score, win: true, lines: [TXT.orbs + ': ' + eaten] });
        return;
      }
    }
    if (eatsStar) {
      var g2 = 50 * level;
      score += g2;
      floaters.push({ x: px, y: py, text: '+' + g2, life: 1.1, color: '#fbbf24' });
      burst(px, py, '#fbbf24', 30, 240);
      game.audio.sfx('bonus');
      star = null;
    }
    game.setStat('score', score);
    game.setStat('length', snake.length, TXT.length);
  }

  function update(dt) {
    pulse += dt;
    if (dying) {
      dying -= dt;
      if (dying <= 0) { dying = 0; game.over({ score: score, lines: [TXT.orbs + ': ' + eaten + ' · ' + TXT.length + ': ' + snake.length] }); }
      return;
    }
    if (star) { star.t -= dt; if (star.t <= 0) { burst(star.x * CELL + CELL / 2, star.y * CELL + CELL / 2, '#fbbf24', 10, 80); star = null; } }
    acc += dt;
    while (acc >= interval && !dying && game.state === 'playing') { acc -= interval; step(); }
  }

  // ---------------------------------------------------------------- rendering
  function rr(x, y, w, h, r) {
    ctx.beginPath(); ctx.moveTo(x + r, y); ctx.arcTo(x + w, y, x + w, y + h, r); ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r); ctx.arcTo(x, y, x + w, y, r); ctx.closePath();
  }
  function lerpColor(a, b, t) {
    var pa = [parseInt(a.slice(1, 3), 16), parseInt(a.slice(3, 5), 16), parseInt(a.slice(5, 7), 16)];
    var pb = [parseInt(b.slice(1, 3), 16), parseInt(b.slice(3, 5), 16), parseInt(b.slice(5, 7), 16)];
    return 'rgb(' + Math.round(pa[0] + (pb[0] - pa[0]) * t) + ',' + Math.round(pa[1] + (pb[1] - pa[1]) * t) + ',' + Math.round(pa[2] + (pb[2] - pa[2]) * t) + ')';
  }

  function render(dt) {
    var live = game.state !== 'paused';
    if (live) {
      for (var i = particles.length - 1; i >= 0; i--) {
        var p = particles[i]; p.life -= dt; p.x += p.vx * dt; p.y += p.vy * dt; p.vx *= 0.94; p.vy *= 0.94;
        if (p.life <= 0) particles.splice(i, 1);
      }
      for (var f = floaters.length - 1; f >= 0; f--) { floaters[f].life -= dt; floaters[f].y -= 30 * dt; if (floaters[f].life <= 0) floaters.splice(f, 1); }
      shake = Math.max(0, shake - dt * 30);
      if (game.state !== 'playing') pulse += dt;
    }
    ctx.save();
    if (shake > 0) ctx.translate((Math.random() - 0.5) * shake, (Math.random() - 0.5) * shake);
    var bg = ctx.createRadialGradient(W * 0.5, H * 0.4, 40, W * 0.5, H * 0.5, W * 0.8);
    bg.addColorStop(0, '#161a2e'); bg.addColorStop(1, '#0b0d17');
    ctx.fillStyle = bg; ctx.fillRect(-20, -20, W + 40, H + 40);
    // grid dots
    ctx.fillStyle = 'rgba(124,92,255,0.18)';
    for (var gy = 1; gy < N; gy++) for (var gx = 1; gx < N; gx++) ctx.fillRect(gx * CELL - 1, gy * CELL - 1, 2, 2);
    // border
    ctx.strokeStyle = 'rgba(34,211,238,0.35)'; ctx.lineWidth = 2; ctx.strokeRect(1, 1, W - 2, H - 2);

    if (!snake) { ctx.restore(); return; }
    var t = dying ? 1 : Math.min(1, acc / interval);
    if (game.state !== 'playing') t = 1;

    // crystals
    crystals.forEach(function (c) {
      var cx = c.x * CELL + CELL / 2, cy = c.y * CELL + CELL / 2, s = CELL * 0.42;
      ctx.save(); ctx.shadowColor = '#f472b6'; ctx.shadowBlur = 14;
      ctx.fillStyle = '#3b1530'; ctx.strokeStyle = '#f472b6'; ctx.lineWidth = 2;
      ctx.beginPath(); ctx.moveTo(cx, cy - s); ctx.lineTo(cx + s, cy); ctx.lineTo(cx, cy + s); ctx.lineTo(cx - s, cy); ctx.closePath();
      ctx.fill(); ctx.stroke();
      ctx.fillStyle = 'rgba(255,255,255,0.35)'; ctx.beginPath(); ctx.moveTo(cx, cy - s); ctx.lineTo(cx + s * 0.4, cy - s * 0.2); ctx.lineTo(cx, cy); ctx.closePath(); ctx.fill();
      ctx.restore();
    });

    // orb
    if (orb) {
      var ox = orb.x * CELL + CELL / 2, oy = orb.y * CELL + CELL / 2, pr = CELL * (0.28 + Math.sin(pulse * 6) * 0.04);
      ctx.save(); ctx.shadowColor = '#22d3ee'; ctx.shadowBlur = 22;
      var og = ctx.createRadialGradient(ox - 3, oy - 3, 1, ox, oy, pr);
      og.addColorStop(0, '#e0fbff'); og.addColorStop(1, '#0891b2');
      ctx.fillStyle = og; ctx.beginPath(); ctx.arc(ox, oy, pr, 0, Math.PI * 2); ctx.fill(); ctx.restore();
      ctx.strokeStyle = 'rgba(34,211,238,' + (0.4 + Math.sin(pulse * 6) * 0.2) + ')'; ctx.lineWidth = 1.5;
      ctx.beginPath(); ctx.arc(ox, oy, pr + 6 + Math.sin(pulse * 3) * 2, 0, Math.PI * 2); ctx.stroke();
    }
    // star bonus
    if (star) {
      var sx = star.x * CELL + CELL / 2, sy = star.y * CELL + CELL / 2;
      var blink = star.t < 2 && Math.floor(star.t * 8) % 2 === 0;
      if (!blink) {
        ctx.save(); ctx.translate(sx, sy); ctx.rotate(pulse * 2);
        ctx.shadowColor = '#fbbf24'; ctx.shadowBlur = 20; ctx.fillStyle = '#fde68a';
        ctx.beginPath();
        for (var k = 0; k < 10; k++) { var rad = k % 2 ? CELL * 0.17 : CELL * 0.4; var ang = k * Math.PI / 5 - Math.PI / 2; ctx.lineTo(Math.cos(ang) * rad, Math.sin(ang) * rad); }
        ctx.closePath(); ctx.fill(); ctx.restore();
      }
      ctx.strokeStyle = '#fbbf24'; ctx.lineWidth = 2;
      ctx.beginPath(); ctx.arc(sx, sy, CELL * 0.55, -Math.PI / 2, -Math.PI / 2 + Math.PI * 2 * (star.t / 7)); ctx.stroke();
    }

    // snake (interpolated)
    var pts = [];
    for (var s = 0; s < snake.length; s++) {
      var a = prev[s] || prev[prev.length - 1], b = snake[s];
      pts.push({ x: (a.x + (b.x - a.x) * t) * CELL + CELL / 2, y: (a.y + (b.y - a.y) * t) * CELL + CELL / 2 });
    }
    ctx.save();
    ctx.lineCap = 'round'; ctx.lineJoin = 'round';
    ctx.shadowColor = dying ? '#f472b6' : '#7c5cff'; ctx.shadowBlur = 18;
    var n = pts.length;
    for (var q = n - 1; q > 0; q--) {
      var tt = q / Math.max(1, n - 1);
      ctx.strokeStyle = dying ? lerpColor('#f472b6', '#7c2d55', tt) : lerpColor('#22d3ee', '#7c5cff', tt);
      ctx.lineWidth = CELL * (0.72 - tt * 0.28);
      ctx.beginPath(); ctx.moveTo(pts[q].x, pts[q].y); ctx.lineTo(pts[q - 1].x, pts[q - 1].y); ctx.stroke();
    }
    // head
    var hd = pts[0];
    ctx.fillStyle = dying ? '#f472b6' : '#5eead4';
    ctx.beginPath(); ctx.arc(hd.x, hd.y, CELL * 0.42, 0, Math.PI * 2); ctx.fill();
    ctx.shadowBlur = 0;
    var dv = DIRS[dir];
    var ex = -dv.y, ey = dv.x;
    ctx.fillStyle = '#0b0d17';
    [-1, 1].forEach(function (sgn) {
      var x = hd.x + dv.x * CELL * 0.14 + ex * sgn * CELL * 0.17, y = hd.y + dv.y * CELL * 0.14 + ey * sgn * CELL * 0.17;
      ctx.beginPath(); ctx.arc(x, y, CELL * 0.08, 0, Math.PI * 2); ctx.fill();
    });
    ctx.restore();

    // particles & floaters
    particles.forEach(function (p) {
      ctx.globalAlpha = Math.max(0, Math.min(1, p.life * 1.6));
      ctx.fillStyle = p.color; ctx.beginPath(); ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2); ctx.fill();
    });
    ctx.globalAlpha = 1;
    ctx.font = '800 16px system-ui, sans-serif'; ctx.textAlign = 'center';
    floaters.forEach(function (fl) { ctx.globalAlpha = Math.max(0, Math.min(1, fl.life * 1.5)); ctx.fillStyle = fl.color; ctx.fillText(fl.text, fl.x, fl.y - 14); });
    ctx.globalAlpha = 1;
    ctx.restore();
  }

  // ---------------------------------------------------------------- input
  var KEYMAP = { ArrowUp: 'up', ArrowDown: 'down', ArrowLeft: 'left', ArrowRight: 'right', w: 'up', s: 'down', a: 'left', d: 'right', W: 'up', S: 'down', A: 'left', D: 'right' };
  game.on('keydown', function (e) { if (KEYMAP[e.key]) turn(KEYMAP[e.key]); });
  game.onSwipe(game.stage, function (d) { turn(d); }, 18);
  var pad = game.touchButtons([
    { key: 'ArrowUp', label: '▲' }, { key: 'ArrowLeft', label: '◀' }, { key: 'ArrowDown', label: '▼' }, { key: 'ArrowRight', label: '▶' },
  ], 'center');
  pad.classList.add('ns-pad');

  game.on('start', reset);
  game.loop(update, render);

  game.rng = NebuloGame.mulberry32(20261008); // menu backdrop only; start() reseeds
  reset();
  game.showMenu();
  game.ready();
})();
