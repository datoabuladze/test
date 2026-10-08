/* Stack Tower – original block stacking game for Nebulo.
 * A slab slides back and forth; drop it on the tower. Overhang is sliced off, perfect drops build a combo.
 */
(function () {
  'use strict';
  var L = { en: 0, ka: 1, tr: 2, ru: 3 }[NebuloGame.lang] || 0;
  var TXT = {
    tagline: ['Drop sliding slabs with perfect timing and build a tower to the stars.',
      'ზუსტ დროს ჩამოაგდე მოცურავე ფილები და ააშენე კოშკი ვარსკვლავებამდე.',
      'Kayan blokları tam zamanında bırak ve yıldızlara uzanan bir kule inşa et.',
      'Сбрасывайте скользящие плиты точно вовремя и постройте башню до звёзд.'][L],
    how: [
      ['Tap, click or press Space to drop the sliding slab.', 'Any part hanging over the edge is sliced off – the tower gets narrower.', 'Land almost exactly on top for a PERFECT: the combo grows and gives bonus points.', 'Three perfects in a row make the slab grow back a little.', 'Miss the tower completely and the game is over.'],
      ['შეეხე, დააწკაპუნე ან დააჭირე Space-ს მოცურავე ფილის ჩამოსაგდებად.', 'კიდეს გადაცილებული ნაწილი იჭრება — კოშკი ვიწროვდება.', 'თითქმის ზუსტად დაადე ზემოდან — ეს არის „იდეალური“: კომბო იზრდება და ბონუს ქულებს იძლევა.', 'ზედიზედ სამი იდეალური დადება ფილას ოდნავ ადიდებს.', 'თუ კოშკს საერთოდ ააცდენ, თამაში დასრულდება.'],
      ['Kayan bloğu bırakmak için dokun, tıkla ya da Space tuşuna bas.', 'Kenardan taşan kısım kesilir – kule daralır.', 'Neredeyse tam üstüne bırakırsan MÜKEMMEL: kombo artar ve bonus puan verir.', 'Üst üste üç mükemmel bırakış bloğu biraz büyütür.', 'Kuleyi tamamen ıskalarsan oyun biter.'],
      ['Коснитесь, щёлкните или нажмите Пробел, чтобы сбросить плиту.', 'Всё, что свисает за край, отрезается — башня становится уже.', 'Попадите почти точно — это ИДЕАЛЬНО: комбо растёт и даёт бонусные очки.', 'Три идеальных броска подряд немного расширяют плиту.', 'Промахнулись мимо башни — игра окончена.'],
    ][L],
    perfect: ['PERFECT', 'იდეალური', 'MÜKEMMEL', 'ИДЕАЛЬНО'][L],
    combo: ['Combo', 'კომბო', 'Kombo', 'Комбо'][L],
    floors: ['Floors', 'სართულები', 'Kat', 'Этажи'][L],
    height: ['Height', 'სიმაღლე', 'Yükseklik', 'Высота'][L],
    missed: ['The slab missed the tower.', 'ფილამ კოშკს ააცდინა.', 'Blok kuleyi ıskaladı.', 'Плита пролетела мимо башни.'][L],
    perfects: ['Perfect drops', 'იდეალური დადებები', 'Mükemmel bırakış', 'Идеальные броски'][L],
    tap: ['Tap to drop', 'შეეხე ჩამოსაგდებად', 'Bırakmak için dokun', 'Коснитесь, чтобы сбросить'][L],
  };

  var game = NebuloGame.create({ id: 'stack-tower', title: 'Stack Tower', tagline: TXT.tagline, howTo: TXT.how });
  var W = 480, H = 800, BH = 32, BASEW = 230, DEPTH = 16, BASE_Y = 640, PERFECT = 5;
  var view = game.canvas(W, H), ctx = view.ctx;
  var s, clock = 0, stars = [];
  (function () { var r = NebuloGame.mulberry32(11); for (var i = 0; i < 160; i++) stars.push({ x: r() * W, y: r() * 4000, s: r() < 0.2 ? 2 : 1, ph: r() * 6 }); })();

  function hueFor(i) { return (190 + i * 9) % 360; }
  function newGame() {
    s = { blocks: [{ x: W / 2 - BASEW / 2, w: BASEW, i: 0, land: 0 }], cur: null, debris: [], parts: [], rings: [], pops: [],
      score: 0, combo: 0, maxCombo: 0, perfects: 0, cam: 0, camT: 0, ended: false, over: 0, dropLog: [], shake: 0 };
    spawn();
    stats();
  }
  function top() { return s.blocks[s.blocks.length - 1]; }
  function floors() { return s.blocks.length - 1; }
  function stats() {
    game.setStat('score', s.score);
    game.setStat('floors', floors(), TXT.floors);
    game.setStat('combo', s.combo, TXT.combo);
  }
  function speedFor(n) { return Math.min(560, 190 + n * 11); }
  function spawn() {
    var t = top(), n = s.blocks.length, fromLeft = n % 2 === 1;
    s.cur = { x: fromLeft ? -t.w * 0.6 : W - t.w * 0.4, w: t.w, i: n, dir: fromLeft ? 1 : -1, v: speedFor(n) };
  }
  function yOf(i) { return BASE_Y - i * BH; } // top surface of block i (screen, before camera)

  function drop() {
    if (game.state !== 'playing' || !s.cur || s.over > 0) return;
    var c = s.cur, t = top();
    var off = c.x - t.x;
    var left = Math.max(c.x, t.x), right = Math.min(c.x + c.w, t.x + t.w);
    var y = yOf(c.i);
    s.dropLog.push(Math.round(off));
    if (right - left <= 0) {
      s.debris.push({ x: c.x, y: y, w: c.w, i: c.i, vx: c.dir * c.v * 0.3, vy: 0, rot: 0, vr: c.dir * 1.5 });
      s.cur = null; s.over = 1.2; s.combo = 0; stats();
      game.audio.sfx('hit');
      return;
    }
    var nb;
    if (Math.abs(off) <= PERFECT) {
      s.combo++; s.perfects++; s.maxCombo = Math.max(s.maxCombo, s.combo);
      var w = t.w;
      if (s.combo >= 3 && w < BASEW) { w = Math.min(BASEW, w + 10); }
      nb = { x: t.x - (w - t.w) / 2, w: w, i: c.i, land: 1 };
      s.score += 1 + Math.min(s.combo, 8);
      s.rings.push({ x: nb.x, y: y, w: nb.w, life: 0.6 });
      s.pops.push({ x: W / 2, y: y - 40, text: TXT.perfect + (s.combo > 1 ? ' x' + s.combo : ''), life: 1 });
      game.audio.tone(440 * Math.pow(1.0595, Math.min(s.combo, 16) * 2), 0.14, 'triangle', 0.16);
      for (var k = 0; k < 18; k++) s.parts.push({ x: nb.x + Math.random() * nb.w, y: y, vx: (Math.random() - 0.5) * 160, vy: -Math.random() * 200, life: 0.7, c: 'hsl(' + hueFor(c.i) + ',90%,70%)' });
    } else {
      s.combo = 0;
      nb = { x: left, w: right - left, i: c.i, land: 1 };
      // overhang piece
      if (c.x < t.x) s.debris.push({ x: c.x, y: y, w: t.x - c.x, i: c.i, vx: -60, vy: 0, rot: 0, vr: -1.8 });
      else s.debris.push({ x: t.x + t.w, y: y, w: c.x + c.w - (t.x + t.w), i: c.i, vx: 60, vy: 0, rot: 0, vr: 1.8 });
      s.score += 1;
      s.shake = 4;
      game.audio.sfx('click'); game.audio.tone(260, 0.08, 'square', 0.08);
    }
    s.blocks.push(nb);
    stats();
    spawn();
  }

  function update(dt) {
    clock += dt;
    if (s.cur) {
      var c = s.cur;
      c.x += c.dir * c.v * dt;
      var minX = -c.w * 0.6, maxX = W - c.w * 0.4;
      if (c.x > maxX) { c.x = maxX; c.dir = -1; } else if (c.x < minX) { c.x = minX; c.dir = 1; }
    }
    s.debris.forEach(function (d) { d.vy += 1500 * dt; d.x += d.vx * dt; d.y += d.vy * dt; d.rot += d.vr * dt; });
    s.debris = s.debris.filter(function (d) { return d.y - s.cam < H + 200; });
    s.parts.forEach(function (p) { p.life -= dt; p.vy += 600 * dt; p.x += p.vx * dt; p.y += p.vy * dt; });
    s.parts = s.parts.filter(function (p) { return p.life > 0; });
    s.rings.forEach(function (r) { r.life -= dt; }); s.rings = s.rings.filter(function (r) { return r.life > 0; });
    s.pops.forEach(function (p) { p.life -= dt; p.y -= 40 * dt; }); s.pops = s.pops.filter(function (p) { return p.life > 0; });
    s.blocks.forEach(function (b) { if (b.land > 0) b.land = Math.max(0, b.land - dt * 4); });
    if (s.shake > 0) s.shake = Math.max(0, s.shake - dt * 20);
    // camera: keep the active row around y=330
    s.camT = Math.min(0, yOf(s.blocks.length) - 330);
    s.cam += (s.camT - s.cam) * Math.min(1, dt * 4);
    if (s.over > 0) {
      s.over -= dt;
      if (s.over <= 0) finish();
    }
  }

  function finish() {
    if (s.ended) return; s.ended = true;
    game.over({
      score: s.score,
      lines: [TXT.missed, TXT.floors + ': ' + floors() + '  ·  ' + TXT.perfects + ': ' + s.perfects],
      evidence: { floors: floors(), perfects: s.perfects, maxCombo: s.maxCombo, offs: s.dropLog },
    });
  }

  function drawSlab(x, y, w, i, alpha) {
    var hue = hueFor(i);
    ctx.globalAlpha = alpha === undefined ? 1 : alpha;
    // top face
    ctx.fillStyle = 'hsl(' + hue + ',85%,68%)';
    ctx.beginPath(); ctx.moveTo(x, y); ctx.lineTo(x + DEPTH, y - DEPTH * 0.6); ctx.lineTo(x + w + DEPTH, y - DEPTH * 0.6); ctx.lineTo(x + w, y); ctx.closePath(); ctx.fill();
    // front face
    var g = ctx.createLinearGradient(0, y, 0, y + BH);
    g.addColorStop(0, 'hsl(' + hue + ',80%,55%)'); g.addColorStop(1, 'hsl(' + hue + ',75%,38%)');
    ctx.fillStyle = g; ctx.fillRect(x, y, w, BH);
    // side face
    ctx.fillStyle = 'hsl(' + hue + ',70%,30%)';
    ctx.beginPath(); ctx.moveTo(x + w, y); ctx.lineTo(x + w + DEPTH, y - DEPTH * 0.6); ctx.lineTo(x + w + DEPTH, y + BH - DEPTH * 0.6); ctx.lineTo(x + w, y + BH); ctx.closePath(); ctx.fill();
    // windows
    ctx.fillStyle = 'rgba(255,255,255,0.18)';
    for (var wx = x + 8; wx < x + w - 10; wx += 18) ctx.fillRect(wx, y + 10, 8, 10);
    ctx.globalAlpha = 1;
  }

  function render(dt) {
    if (!s) return;
    if (game.state === 'menu') { clock += dt; }
    var cam = s.cam;
    var height = -cam;
    // sky: darkens to space as you climb
    var tt = Math.min(1, height / 3000);
    var g = ctx.createLinearGradient(0, 0, 0, H);
    g.addColorStop(0, 'hsl(' + (250 - tt * 20) + ',60%,' + (14 - tt * 8) + '%)');
    g.addColorStop(1, 'hsl(' + (290 - tt * 40) + ',55%,' + (22 - tt * 12) + '%)');
    ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);
    stars.forEach(function (st) {
      var y = ((st.y - cam * 0.3) % 4000 + 4000) % 4000 - 3200; if (y < -4 || y > H) return;
      ctx.globalAlpha = (0.25 + tt * 0.6) * (0.6 + 0.4 * Math.sin(clock * 2 + st.ph)); ctx.fillStyle = '#fff'; ctx.fillRect(st.x, y, st.s, st.s);
    });
    ctx.globalAlpha = 1;
    // distant skyline
    var sky = BASE_Y + 40 - cam * 0.5;
    if (sky < H + 200) {
      ctx.fillStyle = 'rgba(124,92,255,0.18)';
      for (var bx = 0; bx < W; bx += 34) { var bh = 60 + Math.abs(Math.sin(bx * 1.7)) * 120; ctx.fillRect(bx, sky - bh, 28, bh + 300); }
      ctx.fillStyle = 'rgba(34,211,238,0.12)';
      for (var bx2 = 14; bx2 < W; bx2 += 52) { var bh2 = 30 + Math.abs(Math.cos(bx2)) * 80; ctx.fillRect(bx2, sky - bh2 + 40, 36, bh2 + 300); }
    }
    ctx.save();
    ctx.translate(s.shake ? (Math.random() - 0.5) * s.shake : 0, -cam);
    // ground pedestal
    ctx.fillStyle = '#161a2b'; ctx.fillRect(-10, BASE_Y + BH, W + 20, 400);
    ctx.strokeStyle = '#22d3ee'; ctx.shadowColor = '#22d3ee'; ctx.shadowBlur = 14; ctx.lineWidth = 3;
    ctx.beginPath(); ctx.moveTo(0, BASE_Y + BH); ctx.lineTo(W, BASE_Y + BH); ctx.stroke(); ctx.shadowBlur = 0;
    // tower
    for (var i = 0; i < s.blocks.length; i++) {
      var b = s.blocks[i], y = yOf(b.i) - b.land * 6;
      if (y - cam > H + 60 || y - cam < -80) continue;
      drawSlab(b.x, y, b.w, b.i);
    }
    // rings
    s.rings.forEach(function (r) {
      var k = 1 - r.life / 0.6;
      ctx.strokeStyle = 'rgba(255,255,255,' + (r.life / 0.6) + ')'; ctx.lineWidth = 3;
      ctx.strokeRect(r.x - k * 22, r.y - k * 10, r.w + k * 44, BH + k * 20);
    });
    // current slab
    if (s.cur) {
      var c = s.cur, cy = yOf(c.i);
      var tp = top();
      ctx.fillStyle = 'rgba(255,255,255,0.06)'; ctx.fillRect(tp.x, cy, tp.w, BH);
      drawSlab(c.x, cy, c.w, c.i);
      ctx.strokeStyle = 'rgba(255,255,255,0.5)'; ctx.lineWidth = 1; ctx.strokeRect(c.x + 0.5, cy + 0.5, c.w - 1, BH - 1);
    }
    s.debris.forEach(function (d) {
      ctx.save(); ctx.translate(d.x + d.w / 2, d.y + BH / 2); ctx.rotate(d.rot); drawSlab(-d.w / 2, -BH / 2, d.w, d.i, 0.85); ctx.restore();
    });
    s.parts.forEach(function (p) { ctx.globalAlpha = Math.max(0, p.life / 0.7); ctx.fillStyle = p.c; ctx.fillRect(p.x - 2, p.y - 2, 4, 4); });
    ctx.globalAlpha = 1;
    s.pops.forEach(function (p) {
      ctx.globalAlpha = Math.min(1, p.life * 1.5); ctx.fillStyle = '#fde047'; ctx.shadowColor = '#f472b6'; ctx.shadowBlur = 14;
      ctx.textAlign = 'center'; ctx.font = '900 26px system-ui, sans-serif'; ctx.fillText(p.text, p.x, p.y);
    });
    ctx.shadowBlur = 0; ctx.globalAlpha = 1;
    ctx.restore();
    // big floor counter
    ctx.textAlign = 'center'; ctx.fillStyle = 'rgba(238,240,255,0.92)'; ctx.font = '900 64px system-ui, sans-serif';
    ctx.shadowColor = '#7c5cff'; ctx.shadowBlur = 20; ctx.fillText(String(s.score), W / 2, 110); ctx.shadowBlur = 0;
    ctx.fillStyle = '#b4b9d6'; ctx.font = '700 14px system-ui, sans-serif';
    ctx.fillText(TXT.floors + ' ' + floors() + (s.combo > 1 ? '  ·  ' + TXT.combo + ' x' + s.combo : ''), W / 2, 138);
    if (game.state === 'playing' && floors() === 0 && s.cur) {
      ctx.globalAlpha = 0.6 + 0.4 * Math.sin(clock * 5); ctx.fillStyle = '#22d3ee'; ctx.font = '800 18px system-ui, sans-serif';
      ctx.fillText(TXT.tap, W / 2, 470); ctx.globalAlpha = 1;
    }
  }

  view.canvas.addEventListener('pointerdown', function (e) { e.preventDefault(); drop(); });
  game.on('keydown', function (e) { if (e.key === ' ' || e.code === 'Space' || e.key === 'ArrowDown' || e.key === 'Enter') drop(); });
  game.on('start', newGame);
  game.loop(update, render);

  newGame();
  game.showMenu();
  game.ready();
})();
