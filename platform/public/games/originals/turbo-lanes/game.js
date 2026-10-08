/* Turbo Lanes – original top-down neon racer for Nebulo.
 * Steer along a winding highway, dodge traffic, collect fuel and boost. One crash ends the run.
 */
(function () {
  'use strict';
  var L = { en: 0, ka: 1, tr: 2, ru: 3 }[NebuloGame.lang] || 0;
  var TXT = {
    tagline: ['Weave through neon traffic, grab fuel and boost, and drive as far as you can.',
      'გაიარე ნეონის ნაკადში, აიღე საწვავი და აჩქარება და იარე რაც შეიძლება შორს.',
      'Neon trafiğin arasından sıyrıl, yakıt ve nitro topla, olabildiğince uzağa sür.',
      'Лавируйте в неоновом потоке, собирайте топливо и ускорение и уезжайте как можно дальше.'][L],
    how: [
      ['◀ ▶ or A / D to steer – or drag your finger on the road.', 'Hold ▲ / W / Space (⚡ on touch) to boost, ▼ / S to brake.', 'Avoid the traffic: a single crash ends the run.', 'Green cans refill fuel, blue bolts refill boost. An empty tank stops the car.', '1 point per 10 m, plus bonuses for pickups and near misses.'],
      ['◀ ▶ ან A / D — მართვა, ან თითი გაასრიალე გზაზე.', 'დააჭირე ▲ / W / Space-ს (სენსორზე ⚡) აჩქარებისთვის, ▼ / S — მუხრუჭი.', 'აარიდე მანქანებს: ერთი შეჯახება თამაშს ასრულებს.', 'მწვანე ბიდონი საწვავს ავსებს, ლურჯი ელვა — აჩქარებას. ცარიელი ავზით მანქანა ჩერდება.', '1 ქულა ყოველ 10 მ-ზე, პლუს ბონუსი ნივთებისა და ახლო აცდენებისთვის.'],
      ['◀ ▶ veya A / D ile direksiyon – ya da parmağını yolda sürükle.', 'Nitro için ▲ / W / Space basılı tut (dokunmatikte ⚡), ▼ / S fren.', 'Trafikten kaç: tek bir kaza yarışı bitirir.', 'Yeşil bidonlar yakıt, mavi şimşekler nitro doldurur. Depo boşalırsa araba durur.', 'Her 10 m için 1 puan, ayrıca eşyalar ve kıl payı geçişler için bonus.'],
      ['◀ ▶ или A / D — руль, либо ведите пальцем по дороге.', 'Удерживайте ▲ / W / Пробел (⚡ на сенсоре) для ускорения, ▼ / S — тормоз.', 'Избегайте машин: одна авария завершает заезд.', 'Зелёные канистры — топливо, синие молнии — ускорение. С пустым баком машина встанет.', '1 очко за каждые 10 м плюс бонусы за подборы и опасные обгоны.'],
    ][L],
    speed: ['Speed', 'სიჩქარე', 'Hız', 'Скорость'][L],
    fuel: ['Fuel', 'საწვავი', 'Yakıt', 'Топливо'][L],
    boost: ['Boost', 'აჩქარება', 'Nitro', 'Ускорение'][L],
    crashed: ['You crashed into traffic!', 'მანქანას შეეჯახე!', 'Trafiğe çarptın!', 'Вы врезались в машину!'][L],
    noFuel: ['You ran out of fuel!', 'საწვავი გამოგელია!', 'Yakıtın bitti!', 'У вас кончилось топливо!'][L],
    dist: ['Distance', 'მანძილი', 'Mesafe', 'Дистанция'][L],
    near: ['NEAR MISS +15', 'ახლოს! +15', 'KIL PAYI +15', 'НА ВОЛОСКЕ +15'][L],
    lowFuel: ['LOW FUEL', 'ცოტა საწვავი', 'YAKIT AZ', 'МАЛО ТОПЛИВА'][L],
  };

  var game = NebuloGame.create({ id: 'turbo-lanes', title: 'Turbo Lanes', tagline: TXT.tagline, howTo: TXT.how });
  var W = 480, H = 720, PY = 590, HALF = 138, LANE = 92;
  var view = game.canvas(W, H), ctx = view.ctx;
  var s = null, parts = [], pops = [], clock = 0, shake = 0, lastStat = {};
  var drag = { on: false, x: 0 };
  var CAR_COLORS = ['#f472b6', '#f59e0b', '#a78bfa', '#34d399', '#fb7185', '#60a5fa', '#facc15'];

  function clamp(v, a, b) { return v < a ? a : v > b ? b : v; }
  function ampAt(d) { return Math.min(80, 16 + d * 0.0032); }
  function roadX(d) { return W / 2 + ampAt(d) * (0.62 * Math.sin(d * s.f1 + s.p1) + 0.38 * Math.sin(d * s.f2 + s.p2)); }
  function sy(d) { return PY - (d - s.dist); }

  function reset() {
    var r = game.rng || NebuloGame.mulberry32(7);
    s = {
      r: r, t: 0, dist: 0, x: W / 2, vx: 0, speed: 220, base: 300, fuel: 100, boost: 40, bonus: 0, score: 0,
      f1: 0.0010 + r() * 0.0003, f2: 0.0024 + r() * 0.0007, p1: r() * 6.28, p2: r() * 6.28,
      traffic: [], items: [], nextCar: 760, nextItem: 1100, crashed: 0, endReason: '', tilt: 0, pickups: 0, near: 0,
      boosting: false, offroad: false, ended: false,
    };
    s.x = roadX(0);
    parts = []; pops = []; shake = 0; lastStat = {};
    stats();
  }

  function stats() {
    var sc = Math.floor(s.dist / 10) + s.bonus;
    s.score = sc;
    var sp = Math.round(s.speed * 0.3), fu = Math.ceil(s.fuel);
    if (lastStat.score !== sc) game.setStat('score', sc);
    if (lastStat.speed !== sp) game.setStat('speed', sp, TXT.speed);
    if (lastStat.fuel !== fu) game.setStat('fuel', fu + '%', TXT.fuel);
    lastStat = { score: sc, speed: sp, fuel: fu };
  }

  function burst(x, y, n, colors, spd, life) {
    for (var i = 0; i < n; i++) {
      var a = Math.random() * Math.PI * 2, v = spd * (0.3 + Math.random() * 0.7);
      parts.push({ x: x, y: y, vx: Math.cos(a) * v, vy: Math.sin(a) * v, life: life * (0.5 + Math.random() * 0.5), max: life, c: colors[i % colors.length], size: 2 + Math.random() * 3 });
    }
  }
  function pop(x, y, text, color) { pops.push({ x: x, y: y, text: text, c: color, life: 1.1 }); }

  function spawnRow(d) {
    var r = s.r;
    var lanes = [-1, 0, 1];
    var count = r() < Math.min(0.5, s.dist / 30000) ? 2 : 1;
    for (var i = 0; i < count; i++) {
      var idx = Math.floor(r() * lanes.length);
      var lane = lanes.splice(idx, 1)[0];
      s.traffic.push({
        d: d + r() * 40, lane: lane, lf: lane, speed: 170 + (lane + 1) * 30 + r() * 15,
        c: CAR_COLORS[Math.floor(r() * CAR_COLORS.length)], passed: false,
        sw: s.dist > 5000 && r() < Math.min(0.35, s.dist / 40000) ? 0.6 + r() * 1.6 : -1, blink: 0,
        truck: r() < 0.15,
      });
    }
  }
  function spawnItem(d) {
    var r = s.r;
    var type = r() < (s.fuel < 45 ? 0.75 : 0.5) ? 'fuel' : 'boost';
    s.items.push({ d: d, lane: Math.floor(r() * 3) - 1, type: type });
  }

  function crash() {
    s.crashed = 1.1; s.wrecked = true; s.endReason = TXT.crashed; s.speed = 0; shake = 16;
    burst(s.x, PY, 60, ['#f472b6', '#fde047', '#fb923c', '#ffffff'], 380, 1.0);
    game.audio.sfx('explode');
  }
  function finish() {
    if (s.ended) return; s.ended = true;
    stats();
    game.over({
      score: s.score,
      lines: [s.endReason, TXT.dist + ': ' + Math.floor(s.dist / 10) + ' m'],
      evidence: { dist: Math.round(s.dist), bonus: s.bonus, t: Math.round(s.t * 10) / 10, pickups: s.pickups, near: s.near },
    });
  }

  function stepFx(dt) {
    for (var i = parts.length - 1; i >= 0; i--) {
      var p = parts[i]; p.life -= dt; p.x += p.vx * dt; p.y += p.vy * dt; p.vx *= 0.96; p.vy *= 0.96;
      if (p.life <= 0) parts.splice(i, 1);
    }
    for (var j = pops.length - 1; j >= 0; j--) { pops[j].life -= dt; pops[j].y -= 40 * dt; if (pops[j].life <= 0) pops.splice(j, 1); }
    if (shake > 0) shake = Math.max(0, shake - dt * 30);
  }

  function update(dt) {
    clock += dt;
    stepFx(dt);
    if (s.crashed > 0) { s.crashed -= dt; if (s.crashed <= 0) finish(); return; }
    s.t += dt;
    var k = game.keys;
    var left = k.ArrowLeft || k.a || k.A, right = k.ArrowRight || k.d || k.D;
    var wantBoost = k.ArrowUp || k.w || k.W || k[' '] || k.Space;
    var brake = k.ArrowDown || k.s || k.S;
    var steer = (right ? 1 : 0) - (left ? 1 : 0);
    var targetVx = steer ? steer * 430 : drag.on ? clamp((drag.x - s.x) * 7, -430, 430) : 0;
    s.vx += (targetVx - s.vx) * Math.min(1, dt * 10);
    s.x = clamp(s.x + s.vx * dt, 24, W - 24);
    s.tilt = s.vx / 430 * 0.2;

    var cx = roadX(s.dist);
    s.offroad = Math.abs(s.x - cx) > HALF - 16;
    s.base = Math.min(880, 300 + s.t * 7);
    var target = s.base;
    s.boosting = false;
    if (s.fuel <= 0) target = 0;
    else {
      if (wantBoost && s.boost > 0) { target *= 1.45; s.boost = Math.max(0, s.boost - 26 * dt); s.boosting = true; }
      if (brake) target *= 0.5;
      if (s.offroad) { target *= 0.5; if (Math.random() < 0.5) parts.push({ x: s.x + (Math.random() - 0.5) * 30, y: PY + 36, vx: (Math.random() - 0.5) * 60, vy: 120, life: 0.5, max: 0.5, c: '#3b5b4f', size: 3 }); }
    }
    s.speed += (target - s.speed) * Math.min(1, dt * (target < s.speed ? 2.6 : 1.5));
    s.dist += s.speed * dt;
    s.fuel = Math.max(0, s.fuel - dt * (2.5 + (s.boosting ? 1.5 : 0)));
    if (s.fuel <= 0 && s.speed < 25) { s.endReason = TXT.noFuel; finish(); return; }
    if (s.fuel < 22 && Math.floor(clock * 2) !== Math.floor((clock - dt) * 2)) game.audio.sfx('tick');

    // exhaust
    if (Math.random() < (s.boosting ? 0.9 : 0.35)) {
      parts.push({ x: s.x + (Math.random() < 0.5 ? -9 : 9), y: PY + 40, vx: (Math.random() - 0.5) * 30, vy: 160 + Math.random() * 100,
        life: 0.35, max: 0.35, c: s.boosting ? (Math.random() < 0.5 ? '#22d3ee' : '#7c5cff') : 'rgba(180,185,214,0.6)', size: s.boosting ? 4 : 2.5 });
    }

    // spawn
    var gapBase = Math.max(240, 560 - s.dist * 0.004);
    while (s.nextCar < s.dist + 1000) { spawnRow(s.nextCar); s.nextCar += gapBase + s.r() * 170; }
    while (s.nextItem < s.dist + 1000) { spawnItem(s.nextItem); s.nextItem += 900 + s.r() * 700; }

    // traffic
    for (var i = s.traffic.length - 1; i >= 0; i--) {
      var c = s.traffic[i];
      c.d += c.speed * dt;
      if (c.sw > 0) {
        c.sw -= dt; c.blink = 1;
        if (c.sw <= 0) {
          var nl = c.lane === 0 ? (s.r() < 0.5 ? -1 : 1) : 0;
          c.lane = nl; c.blink = 0;
        }
      }
      c.lf += (c.lane - c.lf) * Math.min(1, dt * 2.2);
      c.x = roadX(c.d) + c.lf * LANE;
      var len = c.truck ? 118 : 78;
      if (Math.abs(c.d - s.dist) < (len + 76) / 2 - 8 && Math.abs(c.x - s.x) < 35) { crash(); return; }
      if (!c.passed && c.d < s.dist - (len / 2 + 40)) {
        c.passed = true;
        if (Math.abs(c.x - s.x) < 64) { s.bonus += 15; s.near++; pop(s.x, PY - 60, TXT.near, '#f472b6'); game.audio.sfx('point'); }
      }
      if (c.d < s.dist - 320) s.traffic.splice(i, 1);
    }
    // items
    for (var j = s.items.length - 1; j >= 0; j--) {
      var it = s.items[j];
      it.x = roadX(it.d) + it.lane * LANE;
      if (Math.abs(it.d - s.dist) < 52 && Math.abs(it.x - s.x) < 40) {
        s.items.splice(j, 1); s.pickups++; s.bonus += 25;
        if (it.type === 'fuel') { s.fuel = Math.min(100, s.fuel + 34); burst(it.x, sy(it.d), 22, ['#34d399', '#a7f3d0'], 220, 0.6); pop(it.x, sy(it.d) - 20, '+' + TXT.fuel, '#34d399'); game.audio.sfx('bonus'); }
        else { s.boost = Math.min(100, s.boost + 38); burst(it.x, sy(it.d), 22, ['#22d3ee', '#7c5cff'], 220, 0.6); pop(it.x, sy(it.d) - 20, '+' + TXT.boost, '#22d3ee'); game.audio.sfx('point'); }
      } else if (it.d < s.dist - 200) s.items.splice(j, 1);
    }
    stats();
  }

  // ---------------------------------------------------------------- drawing
  function rr(x, y, w, h, r) {
    ctx.beginPath(); ctx.moveTo(x + r, y); ctx.lineTo(x + w - r, y); ctx.quadraticCurveTo(x + w, y, x + w, y + r);
    ctx.lineTo(x + w, y + h - r); ctx.quadraticCurveTo(x + w, y + h, x + w - r, y + h); ctx.lineTo(x + r, y + h);
    ctx.quadraticCurveTo(x, y + h, x, y + h - r); ctx.lineTo(x, y + r); ctx.quadraticCurveTo(x, y, x + r, y); ctx.closePath();
  }

  function drawCar(x, y, color, tilt, player, truck, blink) {
    var len = truck ? 118 : 78, hl = len / 2;
    ctx.save(); ctx.translate(x, y); ctx.rotate(tilt);
    if (player) {
      var g = ctx.createLinearGradient(0, -hl, 0, -hl - 190);
      g.addColorStop(0, 'rgba(255,247,194,0.35)'); g.addColorStop(1, 'rgba(255,247,194,0)');
      ctx.fillStyle = g; ctx.beginPath(); ctx.moveTo(-16, -hl); ctx.lineTo(-52, -hl - 190); ctx.lineTo(52, -hl - 190); ctx.lineTo(16, -hl); ctx.closePath(); ctx.fill();
    }
    ctx.fillStyle = 'rgba(0,0,0,0.45)'; rr(-19, -hl + 6, 44, len, 10); ctx.fill();
    ctx.fillStyle = '#05060c';
    ctx.fillRect(-23, -hl + 10, 5, 16); ctx.fillRect(18, -hl + 10, 5, 16); ctx.fillRect(-23, hl - 26, 5, 16); ctx.fillRect(18, hl - 26, 5, 16);
    ctx.shadowColor = color; ctx.shadowBlur = player ? 22 : 12;
    ctx.fillStyle = color; rr(-20, -hl, 40, len, truck ? 6 : 11); ctx.fill();
    ctx.shadowBlur = 0;
    if (truck) {
      ctx.fillStyle = 'rgba(255,255,255,0.12)'; ctx.fillRect(-16, -hl + 30, 32, len - 38);
      ctx.fillStyle = 'rgba(11,13,23,0.85)'; rr(-15, -hl + 6, 30, 14, 4); ctx.fill();
      ctx.strokeStyle = 'rgba(11,13,23,0.5)'; ctx.lineWidth = 2; ctx.beginPath(); ctx.moveTo(-20, -hl + 26); ctx.lineTo(20, -hl + 26); ctx.stroke();
    } else {
      var shade = ctx.createLinearGradient(-20, 0, 20, 0);
      shade.addColorStop(0, 'rgba(0,0,0,0.25)'); shade.addColorStop(0.5, 'rgba(255,255,255,0.12)'); shade.addColorStop(1, 'rgba(0,0,0,0.25)');
      ctx.fillStyle = shade; rr(-20, -hl, 40, len, 11); ctx.fill();
      ctx.fillStyle = 'rgba(11,13,23,0.88)'; rr(-15, -hl + 16, 30, 15, 5); ctx.fill(); rr(-14, hl - 24, 28, 11, 4); ctx.fill();
      ctx.fillStyle = 'rgba(255,255,255,0.16)'; rr(-13, -hl + 34, 26, 18, 5); ctx.fill();
      if (player) { ctx.fillStyle = '#7c5cff'; ctx.fillRect(-4, -hl, 8, 16); ctx.fillRect(-4, -hl + 52, 8, len - 52); }
    }
    ctx.fillStyle = '#fff7c2'; ctx.fillRect(-16, -hl, 8, 4); ctx.fillRect(8, -hl, 8, 4);
    ctx.shadowColor = '#ff3355'; ctx.shadowBlur = player ? 0 : 10;
    ctx.fillStyle = '#ff3355'; ctx.fillRect(-17, hl - 4, 9, 4); ctx.fillRect(8, hl - 4, 9, 4);
    ctx.shadowBlur = 0;
    if (blink && Math.floor(clock * 6) % 2) { ctx.fillStyle = '#fbbf24'; ctx.beginPath(); ctx.arc(-18, hl - 2, 4, 0, 7); ctx.arc(18, hl - 2, 4, 0, 7); ctx.fill(); }
    ctx.restore();
  }

  function drawItem(it) {
    var y = sy(it.d), x = roadX(it.d) + it.lane * LANE;
    if (y < -40 || y > H + 40) return;
    var bob = Math.sin(clock * 5 + it.d) * 3;
    ctx.save(); ctx.translate(x, y + bob);
    var col = it.type === 'fuel' ? '#34d399' : '#22d3ee';
    ctx.shadowColor = col; ctx.shadowBlur = 18;
    ctx.strokeStyle = col; ctx.lineWidth = 2; ctx.beginPath(); ctx.arc(0, 0, 22 + Math.sin(clock * 6) * 2, 0, 7); ctx.stroke();
    if (it.type === 'fuel') {
      ctx.fillStyle = col; rr(-11, -14, 22, 28, 4); ctx.fill();
      ctx.shadowBlur = 0; ctx.fillStyle = '#064e3b'; ctx.fillRect(-6, -18, 8, 5);
      ctx.strokeStyle = '#064e3b'; ctx.lineWidth = 3; ctx.beginPath(); ctx.moveTo(-6, -6); ctx.lineTo(6, 6); ctx.moveTo(6, -6); ctx.lineTo(-6, 6); ctx.stroke();
    } else {
      ctx.fillStyle = col; ctx.beginPath();
      ctx.moveTo(4, -16); ctx.lineTo(-9, 2); ctx.lineTo(-1, 2); ctx.lineTo(-4, 16); ctx.lineTo(9, -3); ctx.lineTo(1, -3); ctx.closePath(); ctx.fill();
    }
    ctx.restore();
  }

  function bar(x, y, w, v, col, label) {
    ctx.fillStyle = 'rgba(11,13,23,0.7)'; rr(x - 2, y - 2, w + 4, 14, 7); ctx.fill();
    ctx.fillStyle = col; ctx.shadowColor = col; ctx.shadowBlur = 8; rr(x, y, Math.max(4, w * v / 100), 10, 5); ctx.fill(); ctx.shadowBlur = 0;
    ctx.fillStyle = '#b4b9d6'; ctx.font = '700 11px system-ui, sans-serif'; ctx.textAlign = 'left'; ctx.fillText(label, x + w + 10, y + 9);
  }

  function render(dt) {
    if (!s) return;
    if (game.state === 'menu') { clock += dt; s.dist += 140 * dt; s.x = roadX(s.dist); }
    ctx.save();
    if (shake > 0) ctx.translate((Math.random() - 0.5) * shake, (Math.random() - 0.5) * shake);
    ctx.fillStyle = '#0a0e1a'; ctx.fillRect(-30, -30, W + 60, H + 60);
    var off = s.dist % 160;
    ctx.fillStyle = '#0d1424';
    for (var y = -160 + off; y < H + 160; y += 160) ctx.fillRect(-30, y, W + 60, 80);
    // roadside trees / neon posts
    var first = Math.floor((s.dist - (H - PY) - 60) / 130) * 130;
    for (var d = first; d < s.dist + PY + 60; d += 130) {
      var py = sy(d), rx = roadX(d), side = (Math.floor(d / 130) % 2) ? 1 : -1;
      var px = rx + side * (HALF + 44 + (Math.abs(Math.sin(d)) * 40));
      ctx.fillStyle = '#0f2a2a'; ctx.beginPath(); ctx.arc(px, py, 16, 0, 7); ctx.fill();
      ctx.fillStyle = '#134040'; ctx.beginPath(); ctx.arc(px - 4, py - 4, 9, 0, 7); ctx.fill();
    }

    // road body
    var STEP = 12, xs = [], ys = [];
    for (var yy = -24; yy <= H + 36; yy += STEP) { ys.push(yy); xs.push(roadX(s.dist + PY - yy)); }
    var i;
    ctx.beginPath();
    for (i = 0; i < ys.length; i++) ctx.lineTo(xs[i] - HALF - 12, ys[i]);
    for (i = ys.length - 1; i >= 0; i--) ctx.lineTo(xs[i] + HALF + 12, ys[i]);
    ctx.closePath(); ctx.fillStyle = '#f4f4ff'; ctx.fill();
    // curb stripes
    for (i = 0; i < ys.length - 1; i++) {
      var wd = s.dist + PY - ys[i];
      if (Math.floor(wd / 36) % 2) {
        ctx.fillStyle = '#f472b6';
        ctx.beginPath(); ctx.moveTo(xs[i] - HALF - 12, ys[i]); ctx.lineTo(xs[i] - HALF, ys[i]); ctx.lineTo(xs[i + 1] - HALF, ys[i + 1]); ctx.lineTo(xs[i + 1] - HALF - 12, ys[i + 1]); ctx.fill();
        ctx.beginPath(); ctx.moveTo(xs[i] + HALF + 12, ys[i]); ctx.lineTo(xs[i] + HALF, ys[i]); ctx.lineTo(xs[i + 1] + HALF, ys[i + 1]); ctx.lineTo(xs[i + 1] + HALF + 12, ys[i + 1]); ctx.fill();
      }
    }
    ctx.beginPath();
    for (i = 0; i < ys.length; i++) ctx.lineTo(xs[i] - HALF, ys[i]);
    for (i = ys.length - 1; i >= 0; i--) ctx.lineTo(xs[i] + HALF, ys[i]);
    ctx.closePath();
    var rg = ctx.createLinearGradient(0, 0, W, 0); rg.addColorStop(0, '#141a2e'); rg.addColorStop(0.5, '#1a2038'); rg.addColorStop(1, '#141a2e');
    ctx.fillStyle = rg; ctx.fill();
    // neon edges
    ctx.lineWidth = 3; ctx.shadowBlur = 14;
    [[-1, '#22d3ee'], [1, '#7c5cff']].forEach(function (e) {
      ctx.strokeStyle = e[1]; ctx.shadowColor = e[1]; ctx.beginPath();
      for (var q = 0; q < ys.length; q++) ctx.lineTo(xs[q] + e[0] * (HALF - 6), ys[q]);
      ctx.stroke();
    });
    ctx.shadowBlur = 0;
    // lane dashes
    ctx.strokeStyle = 'rgba(238,240,255,0.35)'; ctx.lineWidth = 4; ctx.lineCap = 'round';
    var f2 = Math.floor((s.dist - (H - PY) - 80) / 80) * 80;
    for (var dd = f2; dd < s.dist + PY + 80; dd += 80) {
      for (var ln = -1; ln <= 1; ln += 2) {
        ctx.beginPath(); ctx.moveTo(roadX(dd) + ln * LANE / 2, sy(dd)); ctx.lineTo(roadX(dd + 36) + ln * LANE / 2, sy(dd + 36)); ctx.stroke();
      }
    }
    // neon lamps
    var f3 = Math.floor((s.dist - (H - PY) - 60) / 220) * 220;
    for (var ld = f3; ld < s.dist + PY + 60; ld += 220) {
      for (var sd = -1; sd <= 1; sd += 2) {
        var lx = roadX(ld) + sd * (HALF + 24), lyy = sy(ld);
        ctx.fillStyle = sd < 0 ? '#22d3ee' : '#f472b6'; ctx.shadowColor = ctx.fillStyle; ctx.shadowBlur = 16;
        ctx.beginPath(); ctx.arc(lx, lyy, 4, 0, 7); ctx.fill();
      }
    }
    ctx.shadowBlur = 0;

    s.items.forEach(drawItem);
    s.traffic.forEach(function (c) {
      var cy = sy(c.d);
      if (cy > -80 && cy < H + 80) drawCar(roadX(c.d) + c.lf * LANE, cy, c.c, (c.lane - c.lf) * 0.25, false, c.truck, c.blink);
    });
    // particles
    parts.forEach(function (p) { ctx.globalAlpha = Math.max(0, p.life / p.max); ctx.fillStyle = p.c; ctx.fillRect(p.x - p.size / 2, p.y - p.size / 2, p.size, p.size); });
    ctx.globalAlpha = 1;
    if (!s.wrecked) {
      if (s.boosting) {
        ctx.save(); ctx.translate(s.x, PY); ctx.rotate(s.tilt);
        var fl = 18 + Math.random() * 16;
        var fg = ctx.createLinearGradient(0, 38, 0, 38 + fl); fg.addColorStop(0, '#e0f2fe'); fg.addColorStop(0.4, '#22d3ee'); fg.addColorStop(1, 'rgba(124,92,255,0)');
        ctx.fillStyle = fg; ctx.beginPath(); ctx.moveTo(-14, 38); ctx.lineTo(-8, 38 + fl); ctx.lineTo(-2, 38); ctx.moveTo(2, 38); ctx.lineTo(8, 38 + fl); ctx.lineTo(14, 38); ctx.fill();
        ctx.restore();
      }
      drawCar(s.x, PY, '#22d3ee', s.tilt, true, false, false);
    }
    // speed lines
    if (s.boosting) {
      ctx.strokeStyle = 'rgba(34,211,238,0.25)'; ctx.lineWidth = 2;
      for (var sl = 0; sl < 10; sl++) { var sx = Math.random() * W, sY = Math.random() * H; ctx.beginPath(); ctx.moveTo(sx, sY); ctx.lineTo(sx, sY + 60 + Math.random() * 60); ctx.stroke(); }
    }
    pops.forEach(function (p) {
      ctx.globalAlpha = Math.min(1, p.life * 1.5); ctx.fillStyle = p.c; ctx.font = '900 18px system-ui, sans-serif'; ctx.textAlign = 'center';
      ctx.shadowColor = p.c; ctx.shadowBlur = 10; ctx.fillText(p.text, p.x, p.y); ctx.shadowBlur = 0;
    });
    ctx.globalAlpha = 1;
    ctx.restore();

    // in-canvas gauges
    if (game.state !== 'menu') {
      bar(16, 16, 130, s.fuel, s.fuel < 22 ? '#fb7185' : '#34d399', TXT.fuel);
      bar(16, 36, 130, s.boost, '#22d3ee', TXT.boost);
      ctx.textAlign = 'right'; ctx.fillStyle = 'rgba(238,240,255,0.9)'; ctx.font = '900 26px system-ui, sans-serif';
      ctx.fillText(Math.floor(s.dist / 10) + ' m', W - 16, 38);
      ctx.font = '700 13px system-ui, sans-serif'; ctx.fillStyle = '#22d3ee';
      ctx.fillText(Math.round(s.speed * 0.3) + ' km/h', W - 16, 56);
      if (s.fuel < 22 && s.fuel > 0 && Math.floor(clock * 3) % 2) {
        ctx.textAlign = 'center'; ctx.fillStyle = '#fb7185'; ctx.font = '900 20px system-ui, sans-serif'; ctx.fillText(TXT.lowFuel, W / 2, 90);
      }
    }
  }

  // ---------------------------------------------------------------- input
  var cv = view.canvas;
  cv.addEventListener('pointerdown', function (e) {
    drag.on = true; drag.x = view.toLogical(e.clientX, e.clientY).x;
    try { cv.setPointerCapture(e.pointerId); } catch (err) { /* ignore */ }
  });
  cv.addEventListener('pointermove', function (e) { if (drag.on) drag.x = view.toLogical(e.clientX, e.clientY).x; });
  ['pointerup', 'pointercancel', 'lostpointercapture'].forEach(function (ev) { cv.addEventListener(ev, function () { drag.on = false; }); });

  game.touchButtons([{ key: 'ArrowLeft', label: '◀' }, { key: 'ArrowRight', label: '▶' }], 'left');
  game.touchButtons([{ key: 'ArrowDown', label: '▼' }, { key: 'ArrowUp', label: '⚡' }], 'right');

  game.on('start', function () { drag.on = false; reset(); });
  game.loop(update, render);

  reset();
  game.showMenu();
  game.ready();
})();
