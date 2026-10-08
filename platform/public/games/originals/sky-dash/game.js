/* Sky Dash – original endless runner for Nebulo.
 * Auto-running bot on neon rooftops: jump / double jump (hold for height) over gaps, spikes,
 * crates and ledges, collect gems. Level chunks come from the seeded game.rng().
 */
(function () {
  'use strict';
  var L = { en: 0, ka: 1, tr: 2, ru: 3 }[NebuloGame.lang] || 0;
  var TXT = {
    tagline: ['Sprint across neon rooftops, leap the gaps and grab every gem.', 'გაირბინე ნეონის სახურავებზე, გადაახტი უფსკრულებს და შეაგროვე ყველა ძვირფასი ქვა.', 'Neon çatılarda koş, boşlukların üzerinden atla ve her mücevheri kap.', 'Мчитесь по неоновым крышам, перепрыгивайте пропасти и собирайте все кристаллы.'][L],
    how: [
      ['You run automatically and keep speeding up.', 'Space / ↑ / W, click or tap to jump – hold it to jump higher.', 'Press again in the air for a double jump.', 'Clear gaps, spikes, crates and higher ledges – one mistake ends the run.', 'Gems are worth 25 points; distance adds 1 point every 10 m.'],
      ['შენ ავტომატურად გარბიხარ და სიჩქარე სულ იზრდება.', 'Space / ↑ / W, დაწკაპუნება ან შეხება — ხტომა; დაიჭირე, რომ უფრო მაღლა ახტე.', 'ჰაერში ხელახლა დაჭერა ორმაგი ხტომაა.', 'გადალახე უფსკრულები, ეკლები, ყუთები და მაღალი კიდეები — ერთი შეცდომა თამაშს ასრულებს.', 'ძვირფასი ქვა 25 ქულაა; ყოველი 10 მ მანძილი 1 ქულას ამატებს.'],
      ['Otomatik olarak koşarsın ve giderek hızlanırsın.', 'Zıplamak için Space / ↑ / W, tıkla ya da dokun – daha yükseğe zıplamak için basılı tut.', 'Havadayken tekrar basarak çift zıpla.', 'Boşlukları, dikenleri, sandıkları ve yüksek çıkıntıları aş – tek hata koşuyu bitirir.', 'Mücevherler 25 puan değerinde; her 10 m mesafe 1 puan ekler.'],
      ['Вы бежите автоматически, и скорость постоянно растёт.', 'Пробел / ↑ / W, щелчок или касание — прыжок; удерживайте, чтобы прыгнуть выше.', 'Нажмите ещё раз в воздухе для двойного прыжка.', 'Преодолевайте пропасти, шипы, ящики и высокие уступы — одна ошибка завершает забег.', 'Кристалл даёт 25 очков; каждые 10 м пути — 1 очко.'],
    ][L],
    gems: ['Gems', 'ქვები', 'Mücevher', 'Кристаллы'][L],
    dist: ['Distance', 'მანძილი', 'Mesafe', 'Дистанция'][L],
    fell: ['You fell into the void.', 'უფსკრულში ჩავარდი.', 'Boşluğa düştün.', 'Вы сорвались в пропасть.'][L],
    crashed: ['You crashed.', 'შეეჯახე დაბრკოლებას.', 'Bir engele çarptın.', 'Вы врезались в препятствие.'][L],
    tap: ['Tap / Space to jump', 'შეეხე / Space — ხტომა', 'Zıplamak için dokun / Space', 'Касание / Пробел — прыжок'][L],
  };

  var game = NebuloGame.create({ id: 'sky-dash', title: 'Sky Dash', tagline: TXT.tagline, howTo: TXT.how });
  var W = 800, H = 450, GY = 360, PX = 200, PW = 26, PH = 30;
  var G = 2300, JV = 800, MIN_V = 330, MAX_V = 720;
  var view = game.canvas(W, H);
  var ctx = view.ctx;

  var plats, obs, gems, genX, lastY, p, camX, speed, dist, gemCount, gemPts, score, dying, deathMsg, particles = [], floats = [],
    shake = 0, time = 0, jumpHeld = false, buffer = 0, trail = [], runT = 0;

  // decorative background (not gameplay, so Math.random is fine)
  var far = [], mid = [], starsBg = [];
  for (var i = 0; i < 14; i++) far.push({ x: i * 140, h: 60 + Math.random() * 90 });
  for (var j = 0; j < 22; j++) mid.push({ x: j * 90, w: 50 + Math.random() * 40, h: 70 + Math.random() * 130, win: Math.floor(Math.random() * 1000) });
  for (var k = 0; k < 80; k++) starsBg.push({ x: Math.random() * W, y: Math.random() * H * 0.6, r: Math.random() * 1.5 + 0.3 });

  function rnd(a, b) { return a + game.rng() * (b - a); }
  function addPlat(x, w, y, kind) { plats.push({ x: x, w: w, y: y, kind: kind }); }

  function genChunk() {
    var d = Math.min(1, (speed - MIN_V) / (MAX_V - MIN_V));
    // rooftop run
    var runLen = rnd(420, 900) * (1 - 0.25 * d);
    var y = lastY;
    addPlat(genX, runLen, y, 'ground');
    // obstacles on the run
    var ox = genX + rnd(180, 300);
    while (ox < genX + runLen - 160) {
      var r = game.rng();
      if (r < 0.45) { obs.push({ type: 'spike', x: ox, y: y - 24, w: 34, h: 24 }); }
      else if (r < 0.8) {
        var bh = rnd(34, 50 + 30 * d), bw = rnd(40, 64);
        obs.push({ type: 'block', x: ox, y: y - bh, w: bw, h: bh });
        for (var g = 0; g < 3; g++) gems.push({ x: ox + bw / 2 - 30 + g * 30, y: y - bh - 50 - (g === 1 ? 14 : 0) });
      } else {
        for (var g2 = 0; g2 < 5; g2++) gems.push({ x: ox + g2 * 34, y: y - 40 });
      }
      ox += rnd(300, 520) * (1 - 0.3 * d) + speed * 0.25;
    }
    genX += runLen;
    // gap (maybe with floating platform)
    var air = 2 * JV / G; // single-jump airtime
    var single = speed * air;
    var nextY = Math.max(GY - 80, Math.min(GY + 30, y + [0, 0, -40, 40, -30, 30][Math.floor(game.rng() * 6)]));
    var rise = y - nextY; // positive when next roof is higher
    if (d > 0.15 && game.rng() < 0.3) {
      var gapW = single * rnd(1.2, 1.6);
      var fw = rnd(110, 170), fy = Math.min(y, nextY) - rnd(70, 100);
      addPlat(genX + (gapW - fw) / 2, fw, fy, 'float');
      for (var g3 = 0; g3 < 4; g3++) gems.push({ x: genX + (gapW - fw) / 2 + 20 + g3 * (fw - 40) / 3, y: fy - 34 });
      genX += gapW;
    } else {
      var maxGap = single * (0.45 + 0.35 * d) - Math.max(0, rise) * 0.8;
      var gw = Math.max(70, rnd(0.55, 1) * maxGap);
      for (var g4 = 0; g4 < 3; g4++) gems.push({ x: genX + gw * (0.25 + g4 * 0.25), y: Math.min(y, nextY) - 70 - (g4 === 1 ? 30 : 0) });
      genX += gw;
    }
    lastY = nextY;
  }

  function reset() {
    plats = []; obs = []; gems = []; particles = []; floats = []; trail = [];
    genX = -200; lastY = GY; speed = MIN_V;
    addPlat(genX, 1200, GY, 'ground'); genX += 1200;
    p = { x: PX, y: GY - PH, vy: 0, ground: true, jumps: 0, coyote: 0, spin: 0, squash: 0 };
    camX = 0; dist = 0; gemCount = 0; gemPts = 0; score = 0; dying = 0; shake = 0; buffer = 0; runT = 0;
    while (genX < W + 600) genChunk();
    stats();
  }
  function stats() { game.setStat('score', score); game.setStat('gems', gemCount, TXT.gems); }

  function burst(x, y, color, n, sp) {
    for (var i = 0; i < n; i++) {
      var a = Math.random() * Math.PI * 2, v = (0.3 + Math.random()) * (sp || 200);
      particles.push({ x: x, y: y, vx: Math.cos(a) * v, vy: Math.sin(a) * v, life: 0.4 + Math.random() * 0.5, color: color, r: 1.5 + Math.random() * 2.5 });
    }
  }

  function pressJump() {
    if (game.state !== 'playing' || dying) return;
    jumpHeld = true;
    if (p.ground || p.coyote > 0) doJump(1);
    else if (p.jumps < 2) doJump(2);
    else buffer = 0.12;
  }
  function releaseJump() { jumpHeld = false; if (p && p.vy < -200) p.vy *= 0.55; }
  function doJump(n) {
    p.vy = n === 1 ? -JV : -JV * 0.88;
    p.jumps = n; p.ground = false; p.coyote = 0; p.squash = -0.25;
    if (n === 2) { p.spin = 1; burst(p.x + camX, p.y + PH, '#a78bfa', 12, 140); game.audio.tone(520, 0.14, 'square', 0.09, 980); }
    else { burst(p.x + camX, p.y + PH, '#22d3ee', 8, 90); game.audio.sfx('jump'); }
  }

  function die(msg) {
    if (dying) return;
    dying = 1; deathMsg = msg; shake = 14;
    burst(camX + p.x + PW / 2, p.y + PH / 2, '#22d3ee', 40, 320); burst(camX + p.x + PW / 2, p.y + PH / 2, '#f472b6', 20, 240);
    game.audio.sfx('explode');
  }

  function update(dt) {
    time += dt;
    if (dying) {
      dying -= dt;
      if (dying <= 0) { dying = 0; game.over({ score: score, lines: [deathMsg, TXT.dist + ': ' + Math.floor(dist / 10) + ' m · ' + TXT.gems + ': ' + gemCount] }); }
      return;
    }
    speed = Math.min(MAX_V, speed + dt * 5.5);
    var dx = speed * dt;
    camX += dx; dist += dx; runT += dt;
    // physics
    var prevBottom = p.y + PH;
    p.vy += G * dt; if (p.vy > 1400) p.vy = 1400;
    p.y += p.vy * dt;
    if (p.coyote > 0) p.coyote -= dt;
    if (buffer > 0) buffer -= dt;
    if (p.spin > 0) p.spin = Math.max(0, p.spin - dt * 2.6);
    p.squash += (0 - p.squash) * Math.min(1, dt * 10);
    var wasGround = p.ground; p.ground = false;
    var left = camX + p.x, right = left + PW, bottom = p.y + PH;
    var solids = plats.concat(obs.filter(function (o) { return o.type === 'block'; }));
    for (var i = 0; i < solids.length; i++) {
      var s = solids[i];
      if (right <= s.x + 2 || left >= s.x + s.w - 2) continue;
      if (p.vy >= 0 && prevBottom <= s.y + 3 && bottom >= s.y) {
        p.y = s.y - PH; p.vy = 0; p.ground = true; p.jumps = 0;
        if (!wasGround) { p.squash = 0.3; burst(left + PW / 2, s.y, '#22d3ee', 5, 60); game.audio.tone(160, 0.05, 'triangle', 0.08); }
        if (buffer > 0) { buffer = 0; doJump(1); }
        break;
      }
      var depth = s.kind === 'float' ? 0 : (s.kind === 'ground' ? 999 : s.h);
      if (s.kind !== 'float' && bottom > s.y + 6 && p.y < s.y + depth) { die(TXT.crashed); return; }
    }
    if (wasGround && !p.ground && p.vy >= 0) p.coyote = 0.08;
    // spikes
    for (var o = 0; o < obs.length; o++) {
      var ob = obs[o];
      if (ob.type !== 'spike') continue;
      if (right > ob.x + 8 && left < ob.x + ob.w - 8 && bottom > ob.y + 8) { die(TXT.crashed); return; }
    }
    if (p.y > H + 60) { die(TXT.fell); return; }
    // gems
    for (var g = 0; g < gems.length; g++) {
      var gm = gems[g];
      if (gm.taken) continue;
      if (Math.abs(gm.x - (left + PW / 2)) < 24 && Math.abs(gm.y - (p.y + PH / 2)) < 28) {
        gm.taken = true; gemCount++; gemPts += 25;
        floats.push({ x: gm.x, y: gm.y, text: '+25', life: 0.7 });
        burst(gm.x, gm.y, '#f472b6', 10, 120);
        game.audio.tone(1100 + (gemCount % 5) * 120, 0.07, 'square', 0.07);
      }
    }
    // trail
    trail.push({ x: left + PW / 2, y: p.y + PH / 2 }); if (trail.length > 14) trail.shift();
    // generate & cull
    while (genX < camX + W + 600) genChunk();
    plats = plats.filter(function (s) { return s.x + s.w > camX - 200; });
    obs = obs.filter(function (s) { return s.x + s.w > camX - 200; });
    gems = gems.filter(function (s) { return s.x > camX - 200; });
    score = Math.floor(dist / 10) + gemPts;
    stats();
  }

  // ---------------------------------------------------------------- render
  function rr(x, y, w, h, r) {
    ctx.beginPath(); ctx.moveTo(x + r, y); ctx.arcTo(x + w, y, x + w, y + h, r); ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r); ctx.arcTo(x, y, x + w, y, r); ctx.closePath();
  }
  function render(dt) {
    var live = game.state !== 'paused';
    if (live) {
      for (var i = particles.length - 1; i >= 0; i--) { var q = particles[i]; q.life -= dt; q.x += q.vx * dt; q.y += q.vy * dt; q.vy += 500 * dt; if (q.life <= 0) particles.splice(i, 1); }
      for (var f = floats.length - 1; f >= 0; f--) { floats[f].life -= dt; floats[f].y -= 40 * dt; if (floats[f].life <= 0) floats.splice(f, 1); }
      shake = Math.max(0, shake - dt * 30);
      if (game.state !== 'playing') time += dt;
    }
    var cx = camX || 0;
    ctx.save();
    if (shake) ctx.translate((Math.random() - 0.5) * shake, (Math.random() - 0.5) * shake);
    var sky = ctx.createLinearGradient(0, 0, 0, H);
    sky.addColorStop(0, '#0b0d17'); sky.addColorStop(0.55, '#1d1240'); sky.addColorStop(1, '#3a1642');
    ctx.fillStyle = sky; ctx.fillRect(-20, -20, W + 40, H + 40);
    starsBg.forEach(function (s) { ctx.globalAlpha = 0.4 + 0.4 * Math.sin(time * 2 + s.x); ctx.fillStyle = '#fff'; ctx.fillRect(s.x, s.y, s.r, s.r); });
    ctx.globalAlpha = 1;
    // moon
    ctx.save(); ctx.shadowColor = '#f472b6'; ctx.shadowBlur = 40; ctx.fillStyle = '#f9a8d4';
    ctx.beginPath(); ctx.arc(640, 90, 38, 0, Math.PI * 2); ctx.fill(); ctx.restore();
    ctx.strokeStyle = 'rgba(11,13,23,0.35)'; ctx.lineWidth = 3;
    for (var mline = 0; mline < 4; mline++) { ctx.beginPath(); ctx.moveTo(600, 92 + mline * 9); ctx.lineTo(680, 92 + mline * 9); ctx.stroke(); }
    // far mountains
    ctx.fillStyle = '#20154a'; ctx.strokeStyle = 'rgba(34,211,238,0.35)'; ctx.lineWidth = 1.5;
    var off1 = (cx * 0.15) % 140;
    ctx.beginPath(); ctx.moveTo(-140, H);
    far.forEach(function (m, idx) { var x = idx * 140 - off1 - 140; ctx.lineTo(x + 70, 300 - m.h); ctx.lineTo(x + 140, 300); });
    ctx.lineTo(W + 140, H); ctx.closePath(); ctx.fill(); ctx.stroke();
    // mid skyline
    var span = 90 * mid.length, off2 = (cx * 0.4) % span;
    mid.forEach(function (b) {
      var x = b.x - off2; if (x < -100) x += span;
      if (x > W + 10) return;
      ctx.fillStyle = '#150f30'; ctx.fillRect(x, 330 - b.h, b.w, b.h + 140);
      ctx.fillStyle = 'rgba(124,92,255,0.5)';
      for (var wy = 0; wy < b.h - 14; wy += 16) for (var wx = 6; wx < b.w - 8; wx += 12) if (((b.win + wy * 7 + wx * 13) % 5) === 0) ctx.fillRect(x + wx, 330 - b.h + 8 + wy, 5, 7);
    });
    if (!plats) { ctx.restore(); return; }
    ctx.save(); ctx.translate(-cx, 0);
    // platforms
    plats.forEach(function (s) {
      if (s.x > cx + W + 20 || s.x + s.w < cx - 20) return;
      var hgt = s.kind === 'float' ? 16 : H - s.y + 20;
      var g = ctx.createLinearGradient(0, s.y, 0, s.y + Math.min(hgt, 120));
      g.addColorStop(0, s.kind === 'float' ? '#3b2a7a' : '#22184a'); g.addColorStop(1, '#0f0b22');
      ctx.fillStyle = g; ctx.fillRect(s.x, s.y, s.w, hgt);
      if (s.kind !== 'float') {
        ctx.strokeStyle = 'rgba(124,92,255,0.18)'; ctx.lineWidth = 1;
        for (var gx = Math.ceil(s.x / 40) * 40; gx < s.x + s.w; gx += 40) { ctx.beginPath(); ctx.moveTo(gx, s.y + 6); ctx.lineTo(gx, H); ctx.stroke(); }
        for (var gy = s.y + 30; gy < H; gy += 30) { ctx.beginPath(); ctx.moveTo(s.x, gy); ctx.lineTo(s.x + s.w, gy); ctx.stroke(); }
      }
      ctx.save(); ctx.shadowColor = s.kind === 'float' ? '#f472b6' : '#22d3ee'; ctx.shadowBlur = 14;
      ctx.fillStyle = s.kind === 'float' ? '#f472b6' : '#22d3ee'; ctx.fillRect(s.x, s.y, s.w, 3); ctx.restore();
    });
    // obstacles
    obs.forEach(function (o) {
      if (o.x > cx + W + 20 || o.x + o.w < cx - 20) return;
      if (o.type === 'spike') {
        ctx.save(); ctx.shadowColor = '#f472b6'; ctx.shadowBlur = 12; ctx.fillStyle = '#f472b6';
        ctx.beginPath();
        for (var t = 0; t < 2; t++) { var bx = o.x + t * o.w / 2; ctx.moveTo(bx, o.y + o.h); ctx.lineTo(bx + o.w / 4, o.y); ctx.lineTo(bx + o.w / 2, o.y + o.h); }
        ctx.fill(); ctx.restore();
      } else {
        ctx.fillStyle = '#2b1d55'; rr(o.x, o.y, o.w, o.h, 4); ctx.fill();
        ctx.strokeStyle = '#fbbf24'; ctx.lineWidth = 2; ctx.save(); ctx.shadowColor = '#fbbf24'; ctx.shadowBlur = 10; rr(o.x + 1, o.y + 1, o.w - 2, o.h - 2, 4); ctx.stroke(); ctx.restore();
        ctx.strokeStyle = 'rgba(251,191,36,0.4)'; ctx.beginPath(); ctx.moveTo(o.x + 6, o.y + 6); ctx.lineTo(o.x + o.w - 6, o.y + o.h - 6); ctx.moveTo(o.x + o.w - 6, o.y + 6); ctx.lineTo(o.x + 6, o.y + o.h - 6); ctx.stroke();
      }
    });
    // gems
    gems.forEach(function (gm) {
      if (gm.taken || gm.x > cx + W + 20 || gm.x < cx - 20) return;
      var bob = Math.sin(time * 4 + gm.x * 0.05) * 4, sx = Math.abs(Math.cos(time * 3 + gm.x * 0.02));
      ctx.save(); ctx.translate(gm.x, gm.y + bob); ctx.scale(0.4 + 0.6 * sx, 1);
      ctx.shadowColor = '#f472b6'; ctx.shadowBlur = 14; ctx.fillStyle = '#f9a8d4';
      ctx.beginPath(); ctx.moveTo(0, -11); ctx.lineTo(9, -2); ctx.lineTo(0, 11); ctx.lineTo(-9, -2); ctx.closePath(); ctx.fill();
      ctx.fillStyle = '#fff'; ctx.beginPath(); ctx.moveTo(0, -11); ctx.lineTo(4, -2); ctx.lineTo(-4, -2); ctx.closePath(); ctx.fill();
      ctx.restore();
    });
    // trail
    if (p && !dying) {
      for (var tr = 1; tr < trail.length; tr++) {
        ctx.strokeStyle = 'rgba(34,211,238,' + (tr / trail.length * 0.35) + ')'; ctx.lineWidth = tr / trail.length * 14;
        ctx.beginPath(); ctx.moveTo(trail[tr - 1].x, trail[tr - 1].y); ctx.lineTo(trail[tr].x, trail[tr].y); ctx.stroke();
      }
    }
    // player
    if (p && !dying) {
      var px = cx + p.x + PW / 2, py = p.y + PH;
      ctx.save(); ctx.translate(px, py - PH / 2);
      if (p.spin > 0) ctx.rotate((1 - p.spin) * Math.PI * 2);
      var sq = p.squash, sw = 1 + sq * 0.5, sh = 1 - sq * 0.5;
      ctx.scale(sw, sh);
      // legs
      if (p.ground) {
        var ph = runT * speed * 0.045;
        ctx.strokeStyle = '#a78bfa'; ctx.lineWidth = 4; ctx.lineCap = 'round';
        ctx.beginPath(); ctx.moveTo(-5, PH / 2 - 4); ctx.lineTo(-5 + Math.sin(ph) * 7, PH / 2 + 4);
        ctx.moveTo(5, PH / 2 - 4); ctx.lineTo(5 + Math.sin(ph + Math.PI) * 7, PH / 2 + 4); ctx.stroke();
      }
      ctx.shadowColor = '#22d3ee'; ctx.shadowBlur = 18;
      var bg = ctx.createLinearGradient(0, -PH / 2, 0, PH / 2); bg.addColorStop(0, '#67e8f9'); bg.addColorStop(1, '#7c5cff');
      ctx.fillStyle = bg; rr(-PW / 2, -PH / 2, PW, PH - 4, 8); ctx.fill();
      ctx.shadowBlur = 0;
      ctx.fillStyle = '#0b0d17'; rr(-2, -PH / 2 + 6, PW / 2 + 1, 9, 4); ctx.fill();
      ctx.fillStyle = '#f472b6'; ctx.fillRect(4, -PH / 2 + 9, 5, 3);
      // scarf
      ctx.strokeStyle = '#f472b6'; ctx.lineWidth = 3;
      ctx.beginPath(); ctx.moveTo(-PW / 2, -2); ctx.quadraticCurveTo(-PW / 2 - 10, -6 + Math.sin(time * 18) * 4, -PW / 2 - 18, -2 + Math.sin(time * 14) * 5); ctx.stroke();
      ctx.restore();
    }
    // particles
    particles.forEach(function (q) { ctx.globalAlpha = Math.max(0, Math.min(1, q.life * 2)); ctx.fillStyle = q.color; ctx.beginPath(); ctx.arc(q.x, q.y, q.r, 0, Math.PI * 2); ctx.fill(); });
    ctx.globalAlpha = 1;
    ctx.font = '800 15px system-ui, sans-serif'; ctx.textAlign = 'center';
    floats.forEach(function (fl) { ctx.globalAlpha = Math.max(0, Math.min(1, fl.life * 2)); ctx.fillStyle = '#f9a8d4'; ctx.fillText(fl.text, fl.x, fl.y - 14); });
    ctx.globalAlpha = 1;
    ctx.restore();
    // speed lines
    if (speed > 480 && game.state === 'playing') {
      ctx.strokeStyle = 'rgba(255,255,255,' + ((speed - 480) / 600) + ')'; ctx.lineWidth = 1.5;
      for (var sl = 0; sl < 8; sl++) { var ly = (sl * 53 + 20) % (H - 60); var lx = W - ((time * speed * 1.6 + sl * 137) % (W + 200)); ctx.beginPath(); ctx.moveTo(lx, ly); ctx.lineTo(lx + 60, ly); ctx.stroke(); }
    }
    if (p && game.state !== 'menu') {
      ctx.fillStyle = 'rgba(238,240,255,0.85)'; ctx.font = '800 18px system-ui, sans-serif'; ctx.textAlign = 'left';
      ctx.fillText(Math.floor(dist / 10) + ' m', 16, 30);
      ctx.fillStyle = 'rgba(34,211,238,0.25)'; ctx.fillRect(16, 38, 120, 4);
      ctx.fillStyle = '#22d3ee'; ctx.fillRect(16, 38, 120 * (speed - MIN_V) / (MAX_V - MIN_V), 4);
    }
    if (game.state === 'playing' && dist < 200) {
      ctx.globalAlpha = 0.6 + Math.sin(time * 5) * 0.3; ctx.fillStyle = '#eef0ff'; ctx.font = '700 18px system-ui, sans-serif'; ctx.textAlign = 'center';
      ctx.fillText(TXT.tap, W / 2, 140); ctx.globalAlpha = 1;
    }
    ctx.restore();
  }

  // ---------------------------------------------------------------- input
  function isJump(e) { return e.key === ' ' || e.code === 'Space' || e.key === 'ArrowUp' || e.key === 'w' || e.key === 'W'; }
  game.on('keydown', function (e) { if (isJump(e)) pressJump(); });
  game.on('keyup', function (e) { if (isJump(e)) releaseJump(); });
  game.stage.addEventListener('pointerdown', function (e) { if (e.target.closest('.ng-overlay')) return; game.audio.ensure(); pressJump(); });
  window.addEventListener('pointerup', releaseJump);
  window.addEventListener('pointercancel', releaseJump);

  game.on('start', reset);
  game.loop(update, render);

  game.rng = NebuloGame.mulberry32(4242); // menu backdrop only; start() reseeds
  reset();
  game.showMenu();
  game.ready();
})();
