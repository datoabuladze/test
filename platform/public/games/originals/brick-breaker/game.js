/* Prism Breaker – original breakout game for Nebulo.
 * Six hand-made brick layouts, multi-hit and volatile bricks, falling power-ups
 * (wide paddle, multi-ball, slow-mo, pierce, extra life) and a combo multiplier.
 */
(function () {
  'use strict';
  var L = { en: 0, ka: 1, tr: 2, ru: 3 }[NebuloGame.lang] || 0;
  var TXT = {
    tagline: ['Bounce a light ball, shatter every prism brick and clear all six chambers.', 'აასხლიტე სინათლის ბურთი, დაამსხვრიე ყველა პრიზმის აგური და გაიარე ექვსივე დარბაზი.', 'Işık topunu sektir, tüm prizma tuğlalarını parçala ve altı odanın hepsini temizle.', 'Отбивайте световой шар, разбейте все призменные кирпичи и пройдите все шесть залов.'][L],
    how: [
      ['Move the paddle with the mouse, your finger, or ← → / A D.', 'Click, tap or press Space to launch the ball.', 'Bright bricks take several hits; red volatile bricks blow up their neighbours.', 'Catch capsules: W wide, M multi-ball, S slow, P pierce, ♥ extra life.', 'Clear all 6 chambers to win – lose every ball and a life is gone.'],
      ['მართე ფიცარი მაუსით, თითით ან ← → / A D ღილაკებით.', 'ბურთის გასაშვებად დააწკაპუნე, შეეხე ეკრანს ან დააჭირე Space-ს.', 'კაშკაშა აგურებს რამდენიმე დარტყმა სჭირდება; წითელი ფეთქებადი აგური მეზობლებს ანგრევს.', 'დაიჭირე კაფსულები: W განიერი, M მრავალი ბურთი, S შენელება, P გამჭოლი, ♥ დამატებითი სიცოცხლე.', 'გაიარე 6-ვე დარბაზი მოსაგებად — ყველა ბურთის დაკარგვა ერთ სიცოცხლეს წაგართმევს.'],
      ['Raketi fare, parmağın veya ← → / A D ile hareket ettir.', 'Topu fırlatmak için tıkla, dokun ya da Space’e bas.', 'Parlak tuğlalar birkaç vuruş ister; kırmızı patlayıcı tuğlalar komşularını da yok eder.', 'Kapsülleri yakala: W geniş, M çoklu top, S yavaşlatma, P delici, ♥ ekstra can.', 'Kazanmak için 6 odanın hepsini temizle – tüm topları kaçırırsan bir can gider.'],
      ['Двигайте платформу мышью, пальцем или клавишами ← → / A D.', 'Щелчок, касание или Пробел запускают шар.', 'Яркие кирпичи выдерживают несколько ударов; красные взрывные кирпичи разрушают соседей.', 'Ловите капсулы: W — широкая платформа, M — мультишар, S — замедление, P — пробивание, ♥ — жизнь.', 'Пройдите все 6 залов, чтобы победить; потеряете все шары — минус жизнь.'],
    ][L],
    launch: ['Tap / Space to launch', 'შეეხე / Space გასაშვებად', 'Fırlatmak için dokun / Space', 'Касание / Пробел — запуск'][L],
    chamber: ['CHAMBER', 'დარბაზი', 'ODA', 'ЗАЛ'][L],
    cleared: ['CHAMBER CLEAR', 'დარბაზი გავლილია', 'ODA TEMİZLENDİ', 'ЗАЛ ПРОЙДЕН'][L],
    reached: ['Chamber reached', 'მიღწეული დარბაზი', 'Ulaşılan oda', 'Достигнутый зал'][L],
    allClear: ['All six chambers shattered!', 'ექვსივე დარბაზი დამსხვრეულია!', 'Altı odanın hepsi paramparça!', 'Все шесть залов разбиты!'][L],
    combo: ['COMBO', 'კომბო', 'KOMBO', 'КОМБО'][L],
  };

  var game = NebuloGame.create({ id: 'brick-breaker', title: 'Prism Breaker', tagline: TXT.tagline, howTo: TXT.how });
  var W = 480, H = 640;
  var view = game.canvas(W, H);
  var ctx = view.ctx;

  // '.' empty, 1-3 hit points, 'x' volatile (explodes, 1 hit)
  var LEVELS = [
    ['..........', '1111111111', '1111111111', '1111111111', '1111111111', '1111111111', '..........'],
    ['....22....', '...2112...', '..211112..', '.21111112.', '2111xx1112', '.21111112.', '..211112..', '...2112...', '....22....'],
    ['3.3.3.3.3.', '.1.1.1.1.1', '2.2.2.2.2.', '.1.1.1.1.1', '3.3.3.3.3.', '.x.1.1.x.1', '2.2.2.2.2.'],
    ['1........1', '21......12', '321....123', '2x21..12x2', '1232112321', '.11122111.', '..111111..'],
    ['3333..3333', '1111..1111', '1x11..11x1', '..........', '2222222222', '1111111111', '.3.3xx3.3.', '1111111111'],
    ['x2222222x2', '2333333332', '23x1111x32', '2311221132', '231x22x132', '2311111132', '2333333332', '1111111111', '1.1.1.1.1.'],
  ];
  var COLS = 10, BW = 44, BH = 20, GAP = 2, OX = (W - (COLS * (BW + GAP) - GAP)) / 2, OY = 76;
  var PADDLE_Y = 596, BALL_R = 7;
  var POWERS = {
    W: { color: '#22d3ee', label: 'W' }, M: { color: '#a78bfa', label: 'M' }, S: { color: '#34d399', label: 'S' },
    P: { color: '#fbbf24', label: 'P' }, H: { color: '#f472b6', label: '♥' },
  };

  var bricks, balls, caps, particles = [], floats = [], paddle, score, lives, level, combo, wideT, slowT, pierceT,
    stuck, usePointer = false, targetX = W / 2, banner = null, shake = 0, time = 0, levelPause = 0;

  function hue(row) { return ['#f472b6', '#fb7185', '#fb923c', '#fbbf24', '#a3e635', '#34d399', '#22d3ee', '#60a5fa', '#a78bfa'][row % 9]; }

  function buildLevel(n) {
    bricks = [];
    LEVELS[n].forEach(function (row, r) {
      for (var c = 0; c < COLS; c++) {
        var ch = row[c];
        if (!ch || ch === '.') continue;
        var hp = ch === 'x' ? 1 : +ch;
        bricks.push({ x: OX + c * (BW + GAP), y: OY + r * (BH + GAP), w: BW, h: BH, hp: hp, max: hp, vol: ch === 'x', color: ch === 'x' ? '#ef4444' : hue(r), flash: 0, row: r, col: c });
      }
    });
  }
  function baseSpeed() { return 340 + (level - 1) * 22; }
  function resetBall() {
    balls = [{ x: paddle.x, y: PADDLE_Y - BALL_R - 1, vx: 0, vy: 0, speed: baseSpeed(), trail: [] }];
    stuck = true; combo = 0;
  }
  function startLevel(n) {
    buildLevel(n);
    caps = []; wideT = 0; slowT = 0; pierceT = 0;
    resetBall();
    banner = { text: TXT.chamber + ' ' + (n + 1), life: 1.6 };
  }
  function reset() {
    paddle = { x: W / 2, w: 86 };
    score = 0; lives = 3; level = 1; particles = []; floats = []; shake = 0; levelPause = 0;
    targetX = W / 2;
    startLevel(0);
    stats();
  }
  function stats() { game.setStat('score', score); game.setStat('level', level); game.setStat('lives', lives); }

  function launch() {
    if (!stuck || game.state !== 'playing' || levelPause) return;
    stuck = false;
    var b = balls[0];
    var a = (game.rng() - 0.5) * 0.6;
    b.vx = Math.sin(a) * b.speed; b.vy = -Math.cos(a) * b.speed;
    game.audio.sfx('jump');
  }

  function shards(x, y, color, n) {
    for (var i = 0; i < n; i++) {
      var a = Math.random() * Math.PI * 2, v = 60 + Math.random() * 220;
      particles.push({ x: x, y: y, vx: Math.cos(a) * v, vy: Math.sin(a) * v - 60, rot: Math.random() * 6, vr: (Math.random() - 0.5) * 12, life: 0.6 + Math.random() * 0.5, color: color, s: 3 + Math.random() * 4 });
    }
  }

  function damage(br, fromExplosion) {
    if (br.hp <= 0) return;
    br.hp--; br.flash = 0.15;
    score += 10;
    if (br.hp <= 0) {
      combo++;
      var mult = 1 + Math.min(combo, 12) * 0.25;
      var gain = Math.round(br.max * 40 * mult);
      score += gain;
      floats.push({ x: br.x + br.w / 2, y: br.y + br.h / 2, text: '+' + gain, life: 0.8, color: br.color });
      shards(br.x + br.w / 2, br.y + br.h / 2, br.color, 10);
      game.audio.tone(500 + Math.min(combo, 12) * 60, 0.07, 'square', 0.1);
      if (game.rng() < 0.15) {
        var r = game.rng(), k = r < 0.27 ? 'W' : r < 0.52 ? 'M' : r < 0.74 ? 'S' : r < 0.93 ? 'P' : 'H';
        caps.push({ x: br.x + br.w / 2, y: br.y + br.h / 2, k: k, t: 0 });
      }
      if (br.vol) {
        shake = 9; game.audio.sfx('explode');
        shards(br.x + br.w / 2, br.y + br.h / 2, '#fbbf24', 18);
        bricks.forEach(function (o) {
          if (o !== br && o.hp > 0 && Math.abs(o.row - br.row) <= 1 && Math.abs(o.col - br.col) <= 1) damage(o, true);
        });
      }
    } else if (!fromExplosion) {
      game.audio.tone(320, 0.05, 'triangle', 0.1);
    }
  }

  function applyPower(k) {
    game.audio.sfx('bonus');
    var label = { W: 'WIDE', M: 'MULTI', S: 'SLOW', P: 'PIERCE', H: '+1 ♥' }[k];
    floats.push({ x: paddle.x, y: PADDLE_Y - 30, text: label, life: 1, color: POWERS[k].color });
    if (k === 'W') wideT = 15;
    else if (k === 'S') slowT = 10;
    else if (k === 'P') pierceT = 7;
    else if (k === 'H') { lives = Math.min(lives + 1, 6); stats(); }
    else if (k === 'M') {
      var src = balls[0];
      if (stuck) launch();
      var sp = Math.hypot(src.vx, src.vy) || src.speed;
      for (var i = 0; i < 2 && balls.length < 12; i++) {
        var ang = Math.atan2(src.vy, src.vx) + (i ? 0.45 : -0.45);
        balls.push({ x: src.x, y: src.y, vx: Math.cos(ang) * sp, vy: Math.sin(ang) * sp, speed: src.speed, trail: [] });
      }
    }
  }

  function circleRect(b, r) {
    var cx = Math.max(r.x, Math.min(b.x, r.x + r.w)), cy = Math.max(r.y, Math.min(b.y, r.y + r.h));
    var dx = b.x - cx, dy = b.y - cy;
    return dx * dx + dy * dy <= BALL_R * BALL_R;
  }

  function stepBall(b, dt) {
    var sm = slowT > 0 ? 0.65 : 1;
    var dist = Math.hypot(b.vx, b.vy) * dt * sm, steps = Math.max(1, Math.ceil(dist / 4));
    for (var s = 0; s < steps; s++) {
      b.x += b.vx * dt * sm / steps; b.y += b.vy * dt * sm / steps;
      if (b.x < BALL_R) { b.x = BALL_R; b.vx = Math.abs(b.vx); game.audio.sfx('tick'); }
      if (b.x > W - BALL_R) { b.x = W - BALL_R; b.vx = -Math.abs(b.vx); game.audio.sfx('tick'); }
      if (b.y < BALL_R + 4) { b.y = BALL_R + 4; b.vy = Math.abs(b.vy); game.audio.sfx('tick'); }
      // paddle
      var pw = paddle.w;
      if (b.vy > 0 && b.y + BALL_R >= PADDLE_Y && b.y - BALL_R <= PADDLE_Y + 14 && b.x >= paddle.x - pw / 2 - BALL_R && b.x <= paddle.x + pw / 2 + BALL_R) {
        var off = Math.max(-1, Math.min(1, (b.x - paddle.x) / (pw / 2)));
        var ang = off * 1.05;
        b.speed = Math.min(b.speed * 1.012, baseSpeed() * 1.45);
        b.vx = Math.sin(ang) * b.speed; b.vy = -Math.cos(ang) * b.speed;
        b.y = PADDLE_Y - BALL_R;
        combo = 0;
        game.audio.tone(260 + Math.abs(off) * 120, 0.06, 'square', 0.1);
        for (var i = 0; i < 5; i++) particles.push({ x: b.x, y: PADDLE_Y, vx: (Math.random() - 0.5) * 120, vy: -Math.random() * 120, rot: 0, vr: 0, life: 0.3, color: '#22d3ee', s: 2 });
      }
      // bricks
      for (var j = 0; j < bricks.length; j++) {
        var br = bricks[j];
        if (br.hp <= 0 || !circleRect(b, br)) continue;
        damage(br);
        if (pierceT > 0) continue;
        var ox = Math.min(b.x + BALL_R - br.x, br.x + br.w - (b.x - BALL_R));
        var oy = Math.min(b.y + BALL_R - br.y, br.y + br.h - (b.y - BALL_R));
        if (ox < oy) { b.vx = b.x < br.x + br.w / 2 ? -Math.abs(b.vx) : Math.abs(b.vx); b.x += b.vx > 0 ? ox : -ox; }
        else { b.vy = b.y < br.y + br.h / 2 ? -Math.abs(b.vy) : Math.abs(b.vy); b.y += b.vy > 0 ? oy : -oy; }
        break;
      }
      // keep it from going too flat
      var sp = Math.hypot(b.vx, b.vy);
      if (Math.abs(b.vy) < sp * 0.28) { b.vy = (b.vy < 0 ? -1 : 1) * sp * 0.28; b.vx = (b.vx < 0 ? -1 : 1) * Math.sqrt(sp * sp - b.vy * b.vy); }
      if (b.y > H + 20) { b.dead = true; return; }
    }
  }

  function update(dt) {
    time += dt;
    if (levelPause) {
      levelPause -= dt;
      if (levelPause <= 0) {
        levelPause = 0;
        if (level > LEVELS.length) {
          score += lives * 2000; stats();
          game.over({ score: score, win: true, lines: [TXT.allClear] });
          return;
        }
        startLevel(level - 1);
      }
      return;
    }
    // paddle movement
    var kl = game.keys.ArrowLeft || game.keys.a || game.keys.A, kr = game.keys.ArrowRight || game.keys.d || game.keys.D;
    if (kl || kr) { usePointer = false; paddle.x += (kr ? 1 : -1) * 620 * dt; }
    else if (usePointer) { paddle.x += (targetX - paddle.x) * Math.min(1, dt * 22); }
    var goalW = wideT > 0 ? 136 : 86;
    paddle.w += (goalW - paddle.w) * Math.min(1, dt * 10);
    paddle.x = Math.max(paddle.w / 2, Math.min(W - paddle.w / 2, paddle.x));
    if (wideT > 0) wideT -= dt; if (slowT > 0) slowT -= dt; if (pierceT > 0) pierceT -= dt;

    if (stuck) { balls[0].x = paddle.x; balls[0].y = PADDLE_Y - BALL_R - 1; if (game.keys[' '] || game.keys.Space || game.keys.ArrowUp) launch(); }
    else {
      balls.forEach(function (b) {
        b.trail.push({ x: b.x, y: b.y }); if (b.trail.length > 8) b.trail.shift();
        stepBall(b, dt);
      });
      balls = balls.filter(function (b) { return !b.dead; });
      if (!balls.length) {
        lives--; shake = 10; stats();
        game.audio.sfx('hit');
        if (lives <= 0) { game.over({ score: score, lines: [TXT.reached + ': ' + level + ' / ' + LEVELS.length] }); return; }
        caps = []; wideT = 0; slowT = 0; pierceT = 0;
        resetBall();
      }
    }
    // capsules
    for (var i = caps.length - 1; i >= 0; i--) {
      var c = caps[i]; c.y += 130 * dt; c.t += dt;
      if (c.y > PADDLE_Y - 8 && c.y < PADDLE_Y + 20 && Math.abs(c.x - paddle.x) < paddle.w / 2 + 14) { applyPower(c.k); caps.splice(i, 1); continue; }
      if (c.y > H + 20) caps.splice(i, 1);
    }
    bricks.forEach(function (b) { if (b.flash > 0) b.flash -= dt; });
    if (!bricks.some(function (b) { return b.hp > 0; })) {
      score += 1000 * level;
      level++;
      stats();
      game.audio.sfx('win');
      banner = { text: TXT.cleared, life: 1.6 };
      levelPause = 1.8;
      balls.forEach(function (b) { shards(b.x, b.y, '#fff', 12); });
      balls = [{ x: paddle.x, y: PADDLE_Y - BALL_R - 1, vx: 0, vy: 0, speed: baseSpeed(), trail: [] }];
      stuck = true; caps = [];
      return;
    }
    game.setStat('score', score);
  }

  // ---------------------------------------------------------------- rendering
  function rr(x, y, w, h, r) {
    ctx.beginPath(); ctx.moveTo(x + r, y); ctx.arcTo(x + w, y, x + w, y + h, r); ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r); ctx.arcTo(x, y, x + w, y, r); ctx.closePath();
  }
  function render(dt) {
    var live = game.state !== 'paused';
    if (live) {
      for (var i = particles.length - 1; i >= 0; i--) {
        var p = particles[i]; p.life -= dt; p.x += p.vx * dt; p.y += p.vy * dt; p.vy += 400 * dt; p.rot += p.vr * dt;
        if (p.life <= 0) particles.splice(i, 1);
      }
      for (var f = floats.length - 1; f >= 0; f--) { floats[f].life -= dt; floats[f].y -= 40 * dt; if (floats[f].life <= 0) floats.splice(f, 1); }
      if (banner) { banner.life -= dt; if (banner.life <= 0) banner = null; }
      shake = Math.max(0, shake - dt * 30);
      if (game.state !== 'playing') time += dt;
    }
    ctx.save();
    if (shake) ctx.translate((Math.random() - 0.5) * shake, (Math.random() - 0.5) * shake);
    var g = ctx.createLinearGradient(0, 0, 0, H);
    g.addColorStop(0, '#141832'); g.addColorStop(1, '#0b0d17');
    ctx.fillStyle = g; ctx.fillRect(-20, -20, W + 40, H + 40);
    // prism light beams
    ctx.globalAlpha = 0.07;
    ['#22d3ee', '#7c5cff', '#f472b6'].forEach(function (c, k) {
      ctx.fillStyle = c; ctx.beginPath();
      var x0 = (time * 18 + k * 160) % (W + 300) - 150;
      ctx.moveTo(x0, 0); ctx.lineTo(x0 + 60, 0); ctx.lineTo(x0 + 260, H); ctx.lineTo(x0 + 140, H); ctx.closePath(); ctx.fill();
    });
    ctx.globalAlpha = 1;
    ctx.strokeStyle = 'rgba(124,92,255,0.25)'; ctx.lineWidth = 2; ctx.strokeRect(1, 1, W - 2, H - 2);

    if (!bricks) { ctx.restore(); return; }
    // bricks
    bricks.forEach(function (b) {
      if (b.hp <= 0) return;
      var a = 0.45 + 0.55 * (b.hp / b.max);
      ctx.save();
      ctx.shadowColor = b.color; ctx.shadowBlur = b.hp >= 2 ? 14 : 8;
      var bg = ctx.createLinearGradient(b.x, b.y, b.x + b.w, b.y + b.h);
      bg.addColorStop(0, b.color); bg.addColorStop(1, 'rgba(11,13,23,0.9)');
      ctx.globalAlpha = a; ctx.fillStyle = bg; rr(b.x, b.y, b.w, b.h, 5); ctx.fill();
      ctx.restore();
      ctx.fillStyle = 'rgba(255,255,255,0.25)'; rr(b.x + 3, b.y + 2, b.w - 6, 5, 2); ctx.fill();
      if (b.max >= 2) {
        ctx.strokeStyle = 'rgba(255,255,255,' + (0.25 + 0.2 * b.hp) + ')'; ctx.lineWidth = 1.5;
        for (var k = 0; k < b.hp; k++) { ctx.beginPath(); ctx.moveTo(b.x + b.w / 2 - 8 + k * 8, b.y + b.h - 6); ctx.lineTo(b.x + b.w / 2 - 4 + k * 8, b.y + 8); ctx.stroke(); }
      }
      if (b.vol) {
        ctx.fillStyle = '#fde68a'; ctx.beginPath(); ctx.arc(b.x + b.w / 2, b.y + b.h / 2, 3 + Math.sin(time * 8) * 1.2, 0, Math.PI * 2); ctx.fill();
      }
      if (b.flash > 0) { ctx.fillStyle = 'rgba(255,255,255,' + b.flash * 4 + ')'; rr(b.x, b.y, b.w, b.h, 5); ctx.fill(); }
    });
    // capsules
    caps.forEach(function (c) {
      var pw = POWERS[c.k];
      ctx.save(); ctx.shadowColor = pw.color; ctx.shadowBlur = 14;
      ctx.fillStyle = pw.color; rr(c.x - 16, c.y - 8, 32, 16, 8); ctx.fill(); ctx.restore();
      ctx.fillStyle = '#0b0d17'; ctx.font = '900 12px system-ui, sans-serif'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
      ctx.fillText(pw.label, c.x, c.y + 1); ctx.textBaseline = 'alphabetic';
    });
    // paddle
    if (paddle) {
      ctx.save(); ctx.shadowColor = wideT > 0 ? '#22d3ee' : '#7c5cff'; ctx.shadowBlur = 22;
      var pg = ctx.createLinearGradient(paddle.x - paddle.w / 2, 0, paddle.x + paddle.w / 2, 0);
      pg.addColorStop(0, '#22d3ee'); pg.addColorStop(0.5, '#7c5cff'); pg.addColorStop(1, '#f472b6');
      ctx.fillStyle = pg; rr(paddle.x - paddle.w / 2, PADDLE_Y, paddle.w, 14, 7); ctx.fill(); ctx.restore();
      ctx.fillStyle = 'rgba(255,255,255,0.4)'; rr(paddle.x - paddle.w / 2 + 6, PADDLE_Y + 2, paddle.w - 12, 4, 2); ctx.fill();
    }
    // balls
    if (balls) balls.forEach(function (b) {
      b.trail.forEach(function (t, k) { ctx.globalAlpha = k / b.trail.length * 0.35; ctx.fillStyle = pierceT > 0 ? '#fbbf24' : '#22d3ee'; ctx.beginPath(); ctx.arc(t.x, t.y, BALL_R * (0.4 + k / b.trail.length * 0.6), 0, Math.PI * 2); ctx.fill(); });
      ctx.globalAlpha = 1;
      ctx.save(); ctx.shadowColor = pierceT > 0 ? '#fbbf24' : '#22d3ee'; ctx.shadowBlur = 18;
      ctx.fillStyle = '#fff'; ctx.beginPath(); ctx.arc(b.x, b.y, BALL_R, 0, Math.PI * 2); ctx.fill(); ctx.restore();
    });
    // particles (shards)
    particles.forEach(function (p) {
      ctx.globalAlpha = Math.max(0, Math.min(1, p.life * 2)); ctx.fillStyle = p.color;
      ctx.save(); ctx.translate(p.x, p.y); ctx.rotate(p.rot);
      ctx.beginPath(); ctx.moveTo(0, -p.s); ctx.lineTo(p.s * 0.8, p.s * 0.6); ctx.lineTo(-p.s * 0.8, p.s * 0.6); ctx.closePath(); ctx.fill();
      ctx.restore();
    });
    ctx.globalAlpha = 1;
    ctx.textAlign = 'center';
    floats.forEach(function (fl) { ctx.globalAlpha = Math.max(0, Math.min(1, fl.life * 2)); ctx.fillStyle = fl.color; ctx.font = '800 14px system-ui, sans-serif'; ctx.fillText(fl.text, fl.x, fl.y); });
    ctx.globalAlpha = 1;
    // power timers
    var bars = [[wideT, 15, '#22d3ee'], [slowT, 10, '#34d399'], [pierceT, 7, '#fbbf24']].filter(function (b) { return b[0] > 0; });
    bars.forEach(function (b, k) { ctx.fillStyle = 'rgba(255,255,255,0.1)'; ctx.fillRect(12, H - 18 - k * 8, 120, 4); ctx.fillStyle = b[2]; ctx.fillRect(12, H - 18 - k * 8, 120 * b[0] / b[1], 4); });
    if (combo >= 3 && !stuck) { ctx.fillStyle = '#fbbf24'; ctx.font = '900 16px system-ui, sans-serif'; ctx.textAlign = 'right'; ctx.fillText(TXT.combo + ' ×' + (1 + Math.min(combo, 12) * 0.25).toFixed(2), W - 12, H - 14); }
    if (stuck && game.state === 'playing' && !levelPause) {
      ctx.globalAlpha = 0.6 + Math.sin(time * 4) * 0.3; ctx.fillStyle = '#eef0ff'; ctx.font = '700 16px system-ui, sans-serif'; ctx.textAlign = 'center';
      ctx.fillText(TXT.launch, W / 2, PADDLE_Y - 60); ctx.globalAlpha = 1;
    }
    if (banner) {
      ctx.globalAlpha = Math.min(1, banner.life * 2);
      ctx.save(); ctx.shadowColor = '#7c5cff'; ctx.shadowBlur = 20;
      ctx.fillStyle = '#fff'; ctx.font = '900 32px system-ui, sans-serif'; ctx.textAlign = 'center';
      ctx.fillText(banner.text, W / 2, H * 0.62); ctx.restore(); ctx.globalAlpha = 1;
    }
    ctx.restore();
  }

  // ---------------------------------------------------------------- input
  function pointerTo(e) { var p = view.toLogical(e.clientX, e.clientY); targetX = p.x; usePointer = true; }
  game.stage.addEventListener('pointermove', function (e) { if (e.pointerType === 'mouse' || e.buttons || e.pressure > 0) pointerTo(e); });
  game.stage.addEventListener('pointerdown', function (e) { if (e.target !== view.canvas) return; pointerTo(e); game.audio.ensure(); launch(); });
  game.on('keydown', function (e) { if (e.key === ' ' || e.code === 'Space' || e.key === 'ArrowUp' || e.key === 'w' || e.key === 'W') launch(); });

  game.on('start', reset);
  game.loop(update, render);

  level = 1; paddle = { x: W / 2, w: 86 }; buildLevel(0); balls = [{ x: W / 2, y: PADDLE_Y - BALL_R - 1, vx: 0, vy: 0, speed: 340, trail: [] }]; caps = [];
  game.showMenu();
  game.ready();
})();
