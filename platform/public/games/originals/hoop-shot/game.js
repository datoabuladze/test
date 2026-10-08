/* Hoop Shot – original 60-second basketball shooting challenge for Nebulo.
 * Pull back to aim (slingshot style), release to shoot. Real ball physics with rim and backboard.
 */
(function () {
  'use strict';
  var L = { en: 0, ka: 1, tr: 2, ru: 3 }[NebuloGame.lang] || 0;
  var TXT = {
    tagline: ['Sink as many shots as you can in 60 seconds – streaks and swishes pay extra.',
      'ჩააგდე რაც შეიძლება მეტი ბურთი 60 წამში — სერიები და სუფთა სროლები მეტ ქულას იძლევა.',
      '60 saniyede olabildiğince çok basket at – seriler ve file sesi ekstra puan getirir.',
      'Забросьте как можно больше мячей за 60 секунд — серии и чистые броски приносят больше.'][L],
    how: [
      ['Drag back from anywhere and release to shoot – the dotted arc shows your aim.', 'Keyboard: ◀ ▶ change the angle, ▲ ▼ change the power, Space shoots.', 'Basket = 2 points, clean swish without touching the rim = 3.', 'Every basket in a row adds a streak bonus (up to +4). A miss resets it.', 'From level 3 the hoop starts moving. You have 60 seconds.'],
      ['გადაათრიე უკან ნებისმიერი ადგილიდან და გაუშვი სასროლად — წყვეტილი რკალი მიზანს აჩვენებს.', 'კლავიატურა: ◀ ▶ — კუთხე, ▲ ▼ — ძალა, Space — სროლა.', 'კალათი = 2 ქულა, რგოლის შეუხებლად სუფთა სროლა = 3.', 'ყოველი ზედიზედ კალათი სერიის ბონუსს მატებს (მაქს. +4). აცდენა მას ანულებს.', 'მე-3 დონიდან რგოლი მოძრაობს. გაქვს 60 წამი.'],
      ['Herhangi bir yerden geri sürükle ve atmak için bırak – noktalı yay nişanını gösterir.', 'Klavye: ◀ ▶ açıyı, ▲ ▼ gücü değiştirir, Space atar.', 'Basket = 2 puan, çembere değmeden file = 3 puan.', 'Arka arkaya her basket seri bonusu ekler (en fazla +4). Kaçırmak seriyi sıfırlar.', '3. seviyeden itibaren pota hareket eder. 60 saniyen var.'],
      ['Потяните назад из любой точки и отпустите для броска — пунктир показывает траекторию.', 'Клавиатура: ◀ ▶ — угол, ▲ ▼ — сила, Пробел — бросок.', 'Попадание = 2 очка, чистый бросок без касания кольца = 3.', 'Каждое попадание подряд даёт бонус серии (до +4). Промах его сбрасывает.', 'С 3-го уровня кольцо начинает двигаться. У вас 60 секунд.'],
    ][L],
    timeUp: ["Time's up!", 'დრო ამოიწურა!', 'Süre doldu!', 'Время вышло!'][L],
    made: ['Baskets', 'კალათები', 'Basket', 'Попадания'][L],
    acc: ['Accuracy', 'სიზუსტე', 'İsabet', 'Точность'][L],
    streak: ['Streak', 'სერია', 'Seri', 'Серия'][L],
    bestStreak: ['Best streak', 'საუკეთესო სერია', 'En iyi seri', 'Лучшая серия'][L],
    swish: ['SWISH!', 'სუფთა!', 'FİLE!', 'ЧИСТО!'][L],
    onFire: ['ON FIRE', 'ცეცხლი!', 'ALEV ALDI', 'В УДАРЕ'][L],
    drag: ['Drag back & release', 'გადაათრიე უკან და გაუშვი', 'Geri çek ve bırak', 'Потяните и отпустите'][L],
    buzzer: ['BUZZER BEATER!', 'ბოლო წამის კალათი!', 'SON SANİYE BASKETİ!', 'БРОСОК НА СИРЕНУ!'][L],
  };

  var game = NebuloGame.create({ id: 'hoop-shot', title: 'Hoop Shot', tagline: TXT.tagline, howTo: TXT.how });
  var W = 480, H = 720, FLOOR = 650, R = 17, GRAV = 1500, RIM_R = 4.5, RIM_HALF = 34, MAXV = 1450, DUR = 60;
  var view = game.canvas(W, H), ctx = view.ctx;
  var s, parts = [], pops = [], clock = 0, crowd = [];
  (function () { var r = NebuloGame.mulberry32(5); for (var i = 0; i < 140; i++) crowd.push({ x: r() * W, y: 40 + r() * 150, c: ['#7c5cff', '#22d3ee', '#f472b6', '#334155', '#475569'][Math.floor(r() * 5)], ph: r() * 6 }); })();
  var aim = { on: false, sx: 0, sy: 0, x: 0, y: 0 };

  function newGame() {
    s = { score: 0, time: DUR, made: 0, shots: 0, streak: 0, bestStreak: 0, level: 1, ended: false, ang: 58, pow: 0.72,
      hoop: { bx: 360, by: 300, x: 360, y: 300, ax: 0, ay: 0, ph: 0 }, ball: null, net: 0, log: [], timeUp: false };
    parts = []; pops = [];
    newBall();
    stats();
  }
  function stats() {
    game.setStat('score', s.score);
    game.setStat('time', Math.ceil(s.time));
    game.setStat('streak', s.streak, TXT.streak);
    game.setStat('level', s.level);
  }
  function newBall() {
    var r = game.rng || Math.random;
    var x = s.level <= 1 ? 100 : 70 + r() * 110;
    s.ball = { x: x, y: FLOOR - 70, vx: 0, vy: 0, rot: 0, state: 'ready', t: 0, touched: false, scored: false, sx: x, sy: FLOOR - 70, prevY: 0 };
  }
  function relocateHoop() {
    var r = game.rng;
    var h = s.hoop;
    if (s.level >= 2) { h.bx = 300 + r() * 90; h.by = 230 + r() * 150; }
    h.ax = s.level >= 3 ? Math.min(70, 25 + (s.level - 3) * 12) : 0;
    h.ay = s.level >= 5 ? Math.min(60, 20 + (s.level - 5) * 10) : 0;
    h.bx = Math.min(h.bx, W - 60 - h.ax);
    h.bx = Math.max(h.bx, 250 + h.ax);
  }

  function launchVel() {
    if (aim.on) {
      var dx = aim.sx - aim.x, dy = aim.sy - aim.y;
      var len = Math.hypot(dx, dy), k = Math.min(len, 200) / 200;
      if (len < 8) return null;
      return { vx: dx / len * k * MAXV, vy: dy / len * k * MAXV };
    }
    var a = s.ang * Math.PI / 180;
    return { vx: Math.cos(a) * s.pow * MAXV, vy: -Math.sin(a) * s.pow * MAXV };
  }
  function shoot(v) {
    var b = s.ball;
    if (!b || b.state !== 'ready' || !v) return;
    b.vx = v.vx; b.vy = v.vy; b.state = 'fly'; b.t = 0; s.shots++;
    game.audio.tone(180, 0.12, 'triangle', 0.15, 320);
  }

  function burst(x, y, n, cols, spd, life) {
    for (var i = 0; i < n; i++) { var a = Math.random() * 6.283, v = spd * (0.3 + Math.random() * 0.7); parts.push({ x: x, y: y, vx: Math.cos(a) * v, vy: Math.sin(a) * v, life: life, max: life, c: cols[i % cols.length], size: 2 + Math.random() * 3 }); }
  }
  function pop(x, y, text, c, big) { pops.push({ x: x, y: y, text: text, c: c, life: 1.2, big: big }); }

  function collideCircle(b, cx, cy, cr, e) {
    var dx = b.x - cx, dy = b.y - cy, d = Math.hypot(dx, dy), min = R + cr;
    if (d < min && d > 0.0001) {
      var nx = dx / d, ny = dy / d;
      b.x = cx + nx * min; b.y = cy + ny * min;
      var vn = b.vx * nx + b.vy * ny;
      if (vn < 0) { b.vx -= (1 + e) * vn * nx; b.vy -= (1 + e) * vn * ny; b.vx *= 0.92; b.vy *= 0.92; if (-vn > 80) game.audio.tone(520 + Math.random() * 80, 0.06, 'square', 0.07); }
      return true;
    }
    return false;
  }
  function collideRect(b, x0, y0, x1, y1, e) {
    var cx = Math.max(x0, Math.min(b.x, x1)), cy = Math.max(y0, Math.min(b.y, y1));
    var dx = b.x - cx, dy = b.y - cy, d = Math.hypot(dx, dy);
    if (d < R && d > 0.0001) {
      var nx = dx / d, ny = dy / d;
      b.x = cx + nx * R; b.y = cy + ny * R;
      var vn = b.vx * nx + b.vy * ny;
      if (vn < 0) { b.vx -= (1 + e) * vn * nx; b.vy -= (1 + e) * vn * ny; if (-vn > 80) game.audio.tone(140, 0.08, 'triangle', 0.12); }
      return true;
    }
    return false;
  }

  function rimPts() { var h = s.hoop; return { fx: h.x - RIM_HALF, bx: h.x + RIM_HALF, y: h.y }; }

  function scored() {
    var b = s.ball;
    b.scored = true; s.made++; s.streak++; s.bestStreak = Math.max(s.bestStreak, s.streak);
    var base = b.touched ? 2 : 3, bonus = Math.min(4, s.streak - 1), pts = base + bonus;
    s.score += pts; s.net = 1;
    s.log.push(pts);
    var h = s.hoop;
    pop(h.x, h.y - 50, '+' + pts, '#fde047', true);
    if (!b.touched) pop(h.x, h.y - 82, TXT.swish, '#22d3ee');
    if (s.streak >= 3) pop(h.x, h.y - 112, TXT.onFire + ' x' + s.streak, '#f472b6');
    if (s.timeUp) pop(W / 2, 260, TXT.buzzer, '#f472b6', true);
    burst(h.x, h.y + 20, 30, ['#fde047', '#f472b6', '#22d3ee', '#fff'], 260, 0.8);
    game.audio.sfx(b.touched ? 'point' : 'bonus');
    var newLevel = 1 + Math.floor(s.made / 3);
    if (newLevel !== s.level) { s.level = newLevel; }
    stats();
  }

  function update(dt) {
    clock += dt;
    if (!s.timeUp) {
      var before = Math.ceil(s.time);
      s.time = Math.max(0, s.time - dt);
      if (Math.ceil(s.time) !== before) { stats(); if (s.time <= 5 && s.time > 0) game.audio.sfx('tick'); }
      if (s.time <= 0) { s.timeUp = true; game.audio.tone(220, 0.6, 'sawtooth', 0.15, 180); }
    }
    // hoop motion
    var h = s.hoop; h.ph += dt;
    h.x = h.bx + Math.sin(h.ph * 1.3) * h.ax;
    h.y = h.by + Math.sin(h.ph * 0.9 + 1) * h.ay;
    if (s.net > 0) s.net = Math.max(0, s.net - dt * 2.5);

    // keyboard aim
    var k = game.keys;
    if (k.ArrowLeft || k.a || k.A) s.ang = Math.min(85, s.ang + 45 * dt);
    if (k.ArrowRight || k.d || k.D) s.ang = Math.max(15, s.ang - 45 * dt);
    if (k.ArrowUp || k.w || k.W) s.pow = Math.min(1, s.pow + 0.45 * dt);
    if (k.ArrowDown || k.s || k.S) s.pow = Math.max(0.3, s.pow - 0.45 * dt);

    var b = s.ball;
    if (b.state === 'ready') {
      if (s.timeUp) { finish(); return; }
    } else if (b.state === 'fly' || b.state === 'done') {
      var steps = 4, sdt = dt / steps, rp = rimPts();
      for (var i = 0; i < steps; i++) {
        b.prevY = b.y;
        b.vy += GRAV * sdt; b.x += b.vx * sdt; b.y += b.vy * sdt; b.rot += b.vx * sdt * 0.05;
        if (collideCircle(b, rp.fx, rp.y, RIM_R, 0.5)) b.touched = true;
        if (collideCircle(b, rp.bx, rp.y, RIM_R, 0.5)) b.touched = true;
        if (collideRect(b, h.x + RIM_HALF + 6, h.y - 92, h.x + RIM_HALF + 13, h.y + 14, 0.55)) b.touched = true;
        if (!b.scored && b.prevY < rp.y && b.y >= rp.y && b.x > rp.fx + 2 && b.x < rp.bx - 2 && b.vy > 0) scored();
        if (b.y > FLOOR - R) { b.y = FLOOR - R; if (b.vy > 0) { if (b.vy > 160) game.audio.tone(90, 0.08, 'sine', 0.2); b.vy *= -0.55; b.vx *= 0.8; } }
      }
      b.t += dt;
      if (b.state === 'fly') {
        var out = b.x < -40 || b.x > W + 40;
        var rest = b.y >= FLOOR - R - 1 && Math.abs(b.vy) < 90;
        if (b.scored || out || rest || b.t > 4) {
          if (!b.scored) { s.streak = 0; s.log.push(0); stats(); }
          b.state = 'done'; b.t = 0;
        }
      } else if (b.t > (b.scored ? 0.55 : 0.35)) {
        if (s.timeUp) { finish(); return; }
        if (b.scored) relocateHoop();
        newBall();
      }
    }
    for (var j = parts.length - 1; j >= 0; j--) { var p = parts[j]; p.life -= dt; p.vy += 500 * dt; p.x += p.vx * dt; p.y += p.vy * dt; if (p.life <= 0) parts.splice(j, 1); }
    for (var q = pops.length - 1; q >= 0; q--) { pops[q].life -= dt; pops[q].y -= 30 * dt; if (pops[q].life <= 0) pops.splice(q, 1); }
  }

  function finish() {
    if (s.ended) return; s.ended = true;
    var accPct = s.shots ? Math.round(s.made / s.shots * 100) : 0;
    game.over({
      score: s.score, title: TXT.timeUp,
      lines: [TXT.made + ': ' + s.made + ' / ' + s.shots + '  ·  ' + TXT.acc + ': ' + accPct + '%', TXT.bestStreak + ': ' + s.bestStreak],
      evidence: { shots: s.shots, made: s.made, pts: s.log },
    });
  }

  // ---------------------------------------------------------------- drawing
  function rr(x, y, w, h, r) { ctx.beginPath(); ctx.moveTo(x + r, y); ctx.arcTo(x + w, y, x + w, y + h, r); ctx.arcTo(x + w, y + h, x, y + h, r); ctx.arcTo(x, y + h, x, y, r); ctx.arcTo(x, y, x + w, y, r); ctx.closePath(); }

  function drawArena() {
    var g = ctx.createLinearGradient(0, 0, 0, H);
    g.addColorStop(0, '#0b0d17'); g.addColorStop(0.55, '#151033'); g.addColorStop(1, '#0b0d17');
    ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);
    // spotlights
    [[90, '#7c5cff'], [390, '#22d3ee']].forEach(function (sp) {
      var lg = ctx.createRadialGradient(sp[0], 0, 10, sp[0], 0, 420);
      lg.addColorStop(0, sp[1] + '55'); lg.addColorStop(1, 'rgba(0,0,0,0)');
      ctx.fillStyle = lg; ctx.fillRect(0, 0, W, H);
    });
    // crowd
    crowd.forEach(function (c) { ctx.fillStyle = c.c; ctx.globalAlpha = 0.35; ctx.beginPath(); ctx.arc(c.x, c.y + Math.sin(clock * 3 + c.ph) * (s && s.streak >= 3 ? 3 : 1), 6, 0, 7); ctx.fill(); });
    ctx.globalAlpha = 1;
    ctx.fillStyle = 'rgba(11,13,23,0.6)'; ctx.fillRect(0, 196, W, 8);
    // floor
    var fg = ctx.createLinearGradient(0, FLOOR, 0, H);
    fg.addColorStop(0, '#2a1d3f'); fg.addColorStop(1, '#120d22');
    ctx.fillStyle = fg; ctx.fillRect(0, FLOOR, W, H - FLOOR);
    ctx.strokeStyle = 'rgba(255,255,255,0.05)'; ctx.lineWidth = 1;
    for (var x = 0; x < W; x += 40) { ctx.beginPath(); ctx.moveTo(x, FLOOR); ctx.lineTo(x - 30, H); ctx.stroke(); }
    ctx.strokeStyle = '#f472b6'; ctx.shadowColor = '#f472b6'; ctx.shadowBlur = 10; ctx.lineWidth = 3;
    ctx.beginPath(); ctx.moveTo(0, FLOOR); ctx.lineTo(W, FLOOR); ctx.stroke(); ctx.shadowBlur = 0;
    ctx.strokeStyle = 'rgba(34,211,238,0.35)'; ctx.lineWidth = 2;
    ctx.beginPath(); ctx.ellipse(W - 40, FLOOR + 30, 150, 24, 0, Math.PI, 2 * Math.PI); ctx.stroke();
  }

  function drawHoopBack() {
    var h = s.hoop;
    // pole + support
    ctx.fillStyle = '#1e2440'; ctx.fillRect(h.x + RIM_HALF + 13, h.y - 40, 40, 8);
    ctx.fillStyle = '#1e2440'; ctx.fillRect(Math.min(W - 18, h.x + RIM_HALF + 46), h.y - 40, 10, FLOOR - h.y + 40);
    // backboard
    ctx.fillStyle = 'rgba(180,200,255,0.10)'; ctx.fillRect(h.x + RIM_HALF + 6, h.y - 92, 7, 106);
    ctx.strokeStyle = '#eef0ff'; ctx.shadowColor = '#22d3ee'; ctx.shadowBlur = 12; ctx.lineWidth = 2;
    ctx.strokeRect(h.x + RIM_HALF + 6, h.y - 92, 7, 106); ctx.shadowBlur = 0;
    ctx.fillStyle = '#f472b6'; ctx.fillRect(h.x + RIM_HALF + 6, h.y - 40, 7, 30);
    // back rim
    ctx.strokeStyle = '#ea580c'; ctx.lineWidth = 4; ctx.beginPath(); ctx.ellipse(h.x, h.y, RIM_HALF, 7, 0, Math.PI, 2 * Math.PI); ctx.stroke();
  }
  function drawHoopFront() {
    var h = s.hoop, sway = s.net * 10;
    // net
    ctx.strokeStyle = 'rgba(238,240,255,0.75)'; ctx.lineWidth = 1.5;
    var top = h.y, bot = h.y + 52 + sway * 0.6;
    for (var i = 0; i <= 6; i++) {
      var t = i / 6, x0 = h.x - RIM_HALF + t * RIM_HALF * 2, x1 = h.x - 18 + t * 36;
      ctx.beginPath(); ctx.moveTo(x0, top); ctx.quadraticCurveTo((x0 + x1) / 2 + Math.sin(clock * 10 + i) * sway * 0.3, (top + bot) / 2, x1, bot); ctx.stroke();
    }
    for (var j = 1; j <= 3; j++) {
      var yy = top + (bot - top) * j / 3.3, half = RIM_HALF - (RIM_HALF - 18) * j / 3.3;
      ctx.beginPath(); ctx.moveTo(h.x - half, yy); ctx.lineTo(h.x + half, yy); ctx.stroke();
    }
    // front rim
    ctx.strokeStyle = '#fb923c'; ctx.shadowColor = '#fb923c'; ctx.shadowBlur = 12; ctx.lineWidth = 5;
    ctx.beginPath(); ctx.ellipse(h.x, h.y, RIM_HALF, 7, 0, 0, Math.PI); ctx.stroke(); ctx.shadowBlur = 0;
  }

  function drawBall(b) {
    ctx.save(); ctx.translate(b.x, b.y);
    if (s.streak >= 3) { ctx.shadowColor = '#f472b6'; ctx.shadowBlur = 24; }
    var g = ctx.createRadialGradient(-6, -6, 2, 0, 0, R);
    g.addColorStop(0, '#fdba74'); g.addColorStop(1, '#c2410c');
    ctx.fillStyle = g; ctx.beginPath(); ctx.arc(0, 0, R, 0, 7); ctx.fill(); ctx.shadowBlur = 0;
    ctx.rotate(b.rot);
    ctx.strokeStyle = 'rgba(30,10,0,0.75)'; ctx.lineWidth = 1.6;
    ctx.beginPath(); ctx.moveTo(-R, 0); ctx.lineTo(R, 0); ctx.stroke();
    ctx.beginPath(); ctx.moveTo(0, -R); ctx.lineTo(0, R); ctx.stroke();
    ctx.beginPath(); ctx.arc(-R * 1.25, 0, R * 0.85, -0.9, 0.9); ctx.stroke();
    ctx.beginPath(); ctx.arc(R * 1.25, 0, R * 0.85, Math.PI - 0.9, Math.PI + 0.9); ctx.stroke();
    ctx.restore();
    if (s.streak >= 3 && b.state === 'fly' && Math.random() < 0.8) parts.push({ x: b.x, y: b.y, vx: (Math.random() - 0.5) * 40, vy: -40, life: 0.35, max: 0.35, c: Math.random() < 0.5 ? '#f472b6' : '#fb923c', size: 4 });
  }

  function drawAim() {
    var b = s.ball; if (b.state !== 'ready' || game.state !== 'playing') return;
    var v = launchVel();
    if (!v) { ctx.fillStyle = 'rgba(238,240,255,0.5)'; ctx.font = '700 14px system-ui, sans-serif'; ctx.textAlign = 'center'; ctx.fillText(TXT.drag, b.x + 60, b.y + 50); return; }
    var x = b.x, y = b.y, vx = v.vx, vy = v.vy, dt = 1 / 60;
    for (var i = 0; i < 26; i++) {
      for (var k = 0; k < 2; k++) { vy += GRAV * dt; x += vx * dt; y += vy * dt; }
      ctx.globalAlpha = 0.85 - i / 32; ctx.fillStyle = i % 2 ? '#22d3ee' : '#7c5cff';
      ctx.beginPath(); ctx.arc(x, y, 3.2, 0, 7); ctx.fill();
    }
    ctx.globalAlpha = 1;
    var pw = Math.hypot(v.vx, v.vy) / MAXV;
    ctx.fillStyle = 'rgba(11,13,23,0.7)'; rr(b.x - 30, b.y + 28, 60, 8, 4); ctx.fill();
    ctx.fillStyle = pw > 0.85 ? '#f472b6' : '#22d3ee'; rr(b.x - 30, b.y + 28, 60 * pw, 8, 4); ctx.fill();
    if (aim.on) { ctx.strokeStyle = 'rgba(238,240,255,0.3)'; ctx.setLineDash([5, 5]); ctx.beginPath(); ctx.moveTo(aim.sx, aim.sy); ctx.lineTo(aim.x, aim.y); ctx.stroke(); ctx.setLineDash([]); }
  }

  function render(dt) {
    if (!s) return;
    if (game.state === 'menu') { clock += dt; s.hoop.ph += dt; }
    drawArena();
    // shot clock
    ctx.textAlign = 'center';
    ctx.fillStyle = 'rgba(11,13,23,0.7)'; rr(W / 2 - 60, 18, 120, 56, 12); ctx.fill();
    ctx.strokeStyle = 'rgba(244,114,182,0.5)'; ctx.lineWidth = 1.5; rr(W / 2 - 60, 18, 120, 56, 12); ctx.stroke();
    var tl = Math.ceil(s.time);
    ctx.fillStyle = tl <= 10 ? '#f472b6' : '#fde047'; ctx.shadowColor = ctx.fillStyle; ctx.shadowBlur = 12;
    ctx.font = '900 38px ui-monospace, Menlo, monospace'; ctx.fillText((tl < 10 ? '0' : '') + tl, W / 2, 60); ctx.shadowBlur = 0;
    ctx.fillStyle = '#eef0ff'; ctx.font = '900 20px system-ui, sans-serif'; ctx.textAlign = 'left'; ctx.fillText(String(s.score), 18, 44);
    ctx.fillStyle = '#b4b9d6'; ctx.font = '700 11px system-ui, sans-serif'; ctx.fillText(game.t('score').toUpperCase(), 18, 26);
    ctx.textAlign = 'right'; ctx.fillStyle = '#22d3ee'; ctx.font = '900 20px system-ui, sans-serif'; ctx.fillText('x' + s.streak, W - 18, 44);
    ctx.fillStyle = '#b4b9d6'; ctx.font = '700 11px system-ui, sans-serif'; ctx.fillText(TXT.streak.toUpperCase(), W - 18, 26);

    drawHoopBack();
    var b = s.ball;
    var behind = b && b.y < s.hoop.y && b.x > s.hoop.x - RIM_HALF - R && b.x < s.hoop.x + RIM_HALF + R && b.vy > 0;
    if (b && behind) drawBall(b);
    drawHoopFront();
    if (b && !behind) drawBall(b);
    // shooter marker
    if (b && b.state === 'ready') { ctx.fillStyle = 'rgba(34,211,238,0.25)'; ctx.beginPath(); ctx.ellipse(b.x, FLOOR - 2, 30, 6, 0, 0, 7); ctx.fill(); }
    drawAim();
    parts.forEach(function (p) { ctx.globalAlpha = Math.max(0, p.life / p.max); ctx.fillStyle = p.c; ctx.fillRect(p.x - p.size / 2, p.y - p.size / 2, p.size, p.size); });
    ctx.globalAlpha = 1;
    pops.forEach(function (p) {
      ctx.globalAlpha = Math.min(1, p.life * 1.6); ctx.textAlign = 'center'; ctx.fillStyle = p.c; ctx.shadowColor = p.c; ctx.shadowBlur = 12;
      ctx.font = '900 ' + (p.big ? 30 : 18) + 'px system-ui, sans-serif'; ctx.fillText(p.text, p.x, p.y);
    });
    ctx.shadowBlur = 0; ctx.globalAlpha = 1;
  }

  // ---------------------------------------------------------------- input
  var cv = view.canvas;
  cv.addEventListener('pointerdown', function (e) {
    if (game.state !== 'playing' || !s.ball || s.ball.state !== 'ready') return;
    var p = view.toLogical(e.clientX, e.clientY);
    aim.on = true; aim.sx = aim.x = p.x; aim.sy = aim.y = p.y;
    try { cv.setPointerCapture(e.pointerId); } catch (err) { /* ignore */ }
  });
  cv.addEventListener('pointermove', function (e) { if (!aim.on) return; var p = view.toLogical(e.clientX, e.clientY); aim.x = p.x; aim.y = p.y; });
  cv.addEventListener('pointerup', function () {
    if (!aim.on) return;
    var v = launchVel(); aim.on = false;
    if (game.state === 'playing' && v) shoot(v);
  });
  cv.addEventListener('pointercancel', function () { aim.on = false; });
  game.on('keydown', function (e) {
    if (game.state !== 'playing') return;
    if (e.key === ' ' || e.code === 'Space') shoot(launchVel());
  });
  game.touchButtons([{ key: 'ArrowLeft', label: '↺' }, { key: 'ArrowRight', label: '↻' }], 'left');
  game.touchButtons([{ key: 'ArrowDown', label: '−' }, { key: 'ArrowUp', label: '+' }, { key: ' ', label: '●', aria: 'Shoot' }], 'right');

  game.on('start', function () { aim.on = false; newGame(); });
  game.loop(update, render);

  newGame();
  game.showMenu();
  game.ready();
})();
