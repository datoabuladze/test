/* Star Defender – original vertical space shooter for Nebulo.
 * 15 waves of drones, swoopers and gunners with a boss every fifth wave.
 * Auto-fire, power levels, shields, extra lives. Beat the third boss to win.
 */
(function () {
  'use strict';
  var L = { en: 0, ka: 1, tr: 2, ru: 3 }[NebuloGame.lang] || 0;
  var TXT = {
    tagline: ['Hold the line against 15 waves of invaders and three dreadnought bosses.', 'გაუძელი დამპყრობთა 15 ტალღას და სამ უზარმაზარ ბოსს.', 'İstilacıların 15 dalgasına ve üç dev ana gemiye karşı hattı koru.', 'Удержите рубеж против 15 волн захватчиков и трёх флагманов-боссов.'][L],
    how: [
      ['Your ship fires automatically.', 'Move with ← → ↑ ↓ / WASD, or drag anywhere on the screen.', 'Dodge enemy bullets – your hitbox is the small glowing core.', 'Capsules: P more firepower, S shield, ♥ extra life.', 'A boss attacks every 5 waves – defeat the third one to win.'],
      ['შენი ხომალდი ავტომატურად ისვრის.', 'იმოძრავე ← → ↑ ↓ / WASD ღილაკებით ან გაასრიალე თითი ეკრანზე.', 'აარიდე თავი მტრის ტყვიებს — დაზიანების ზონა მხოლოდ პატარა მანათობელი ბირთვია.', 'კაფსულები: P მეტი ცეცხლი, S ფარი, ♥ დამატებითი სიცოცხლე.', 'ყოველ 5 ტალღაზე ბოსი გესხმის თავს — მოსაგებად დაამარცხე მესამე.'],
      ['Gemin otomatik ateş eder.', '← → ↑ ↓ / WASD ile ya da ekranda herhangi bir yerde sürükleyerek hareket et.', 'Düşman mermilerinden kaç – vurulma alanın yalnızca küçük parlayan çekirdektir.', 'Kapsüller: P daha fazla ateş gücü, S kalkan, ♥ ekstra can.', 'Her 5 dalgada bir ana gemi saldırır – kazanmak için üçüncüsünü yen.'],
      ['Ваш корабль стреляет автоматически.', 'Двигайтесь клавишами ← → ↑ ↓ / WASD или ведите пальцем по экрану.', 'Уклоняйтесь от вражеских пуль — уязвимо только маленькое светящееся ядро.', 'Капсулы: P — мощнее огонь, S — щит, ♥ — дополнительная жизнь.', 'Каждые 5 волн нападает босс — победите третьего, чтобы выиграть.'],
    ][L],
    wave: ['WAVE', 'ტალღა', 'DALGA', 'ВОЛНА'][L],
    waveStat: ['Wave', 'ტალღა', 'Dalga', 'Волна'][L],
    boss: ['WARNING · DREADNOUGHT', 'ყურადღება · ბოსი', 'DİKKAT · ANA GEMİ', 'ВНИМАНИЕ · ФЛАГМАН'][L],
    cleared: ['Wave cleared', 'ტალღა გავლილია', 'Dalga temizlendi', 'Волна пройдена'][L],
    reached: ['Reached wave', 'მიღწეული ტალღა', 'Ulaşılan dalga', 'Достигнута волна'][L],
    victory: ['The sector is safe. All three dreadnoughts destroyed!', 'სექტორი დაცულია. სამივე ბოსი განადგურებულია!', 'Sektör güvende. Üç ana geminin hepsi yok edildi!', 'Сектор в безопасности. Все три флагмана уничтожены!'][L],
  };

  var game = NebuloGame.create({ id: 'star-defender', title: 'Star Defender', tagline: TXT.tagline, howTo: TXT.how });
  var W = 480, H = 720, MAX_WAVE = 15;
  var view = game.canvas(W, H);
  var ctx = view.ctx;

  var player, shots, enemies, ebullets, caps, particles = [], rings = [], floats = [], stars = [], schedule, waveT, wave,
    score, lives, banner, shake = 0, fireT, between, time = 0, ending = 0, flash = 0;

  for (var s = 0; s < 120; s++) stars.push({ x: Math.random() * W, y: Math.random() * H, z: 0.2 + Math.random() * 0.8 });

  function stats() { game.setStat('score', score); game.setStat('wave', wave, TXT.waveStat); game.setStat('lives', lives); }

  // ---------------------------------------------------------------- waves
  function rnd(a, b) { return a + game.rng() * (b - a); }
  function addEnemy(e) {
    e.hp = e.max = e.hp || 1; e.t = 0; e.fire = rnd(0.8, 2.6); e.flash = 0;
    enemies.push(e);
  }
  function groupDrones(t0, n) {
    var cx = rnd(80, W - 80);
    for (var i = 0; i < n; i++) schedule.push({ at: t0 + i * 0.35, make: (function (i) { return function () {
      addEnemy({ type: 'drone', x: Math.max(30, Math.min(W - 30, cx + (i % 2 ? 1 : -1) * Math.ceil(i / 2) * 36)), y: -30, vy: rnd(90, 130) + wave * 4, amp: rnd(20, 50), phase: rnd(0, 6), r: 15, hp: 1 + (wave > 8 ? 1 : 0), pts: 100 });
    }; })(i) });
  }
  function groupSwoop(t0, n) {
    var fromLeft = game.rng() < 0.5, depth = rnd(200, 420);
    for (var i = 0; i < n; i++) schedule.push({ at: t0 + i * 0.28, make: function () {
      addEnemy({ type: 'swoop', dir: fromLeft ? 1 : -1, depth: depth, r: 15, hp: 2 + (wave > 9 ? 1 : 0), pts: 150, x: fromLeft ? -30 : W + 30, y: 60 });
    } });
  }
  function groupGunners(t0, n) {
    for (var i = 0; i < n; i++) schedule.push({ at: t0 + i * 0.6, make: function () {
      addEnemy({ type: 'gunner', x: rnd(60, W - 60), y: -40, stopY: rnd(90, 240), r: 20, hp: 5 + Math.floor(wave / 3), pts: 300 });
    } });
  }
  function startWave(n) {
    wave = n; schedule = []; waveT = 0; between = 0;
    if (n % 5 === 0) {
      var k = n / 5;
      banner = { text: TXT.boss, life: 2.4, color: '#f472b6' };
      game.audio.tone(110, 0.6, 'sawtooth', 0.15, 80);
      schedule.push({ at: 1.6, make: function () {
        addEnemy({ type: 'boss', k: k, x: W / 2, y: -90, r: 52, hp: 100 + 90 * k, pts: 5000 * k, pattern: 0, patT: 0, shotT: 1 });
      } });
      if (k > 1) { groupDrones(6, 4); groupDrones(14, 4); }
    } else {
      banner = { text: TXT.wave + ' ' + n, life: 1.6, color: '#22d3ee' };
      var groups = 3 + Math.floor(n / 2), t = 1.2;
      for (var g = 0; g < groups; g++) {
        var r = game.rng();
        if (n >= 2 && r < 0.3) groupSwoop(t, 4 + Math.floor(n / 4));
        else if (n >= 3 && r < 0.48) groupGunners(t, 1 + Math.floor(n / 6));
        else groupDrones(t, 4 + Math.floor(n / 3));
        t += Math.max(1.6, 3 - n * 0.1);
      }
    }
    stats();
  }

  function reset() {
    player = { x: W / 2, y: H - 110, inv: 1.5, power: 1, shield: false };
    shots = []; enemies = []; ebullets = []; caps = []; particles = []; rings = []; floats = [];
    score = 0; lives = 3; fireT = 0; shake = 0; ending = 0; flash = 0;
    startWave(1);
  }

  // ---------------------------------------------------------------- fx
  function boom(x, y, color, n, big) {
    for (var i = 0; i < n; i++) {
      var a = Math.random() * Math.PI * 2, v = 40 + Math.random() * (big ? 360 : 200);
      particles.push({ x: x, y: y, vx: Math.cos(a) * v, vy: Math.sin(a) * v, life: 0.4 + Math.random() * (big ? 0.9 : 0.5), color: Math.random() < 0.3 ? '#fff' : color, r: 1.5 + Math.random() * (big ? 3.5 : 2) });
    }
    rings.push({ x: x, y: y, r: 4, max: big ? 120 : 40, life: big ? 0.6 : 0.35, color: color });
  }
  function fireAt(e, speed, spread, count) {
    var base = Math.atan2(player.y - e.y, player.x - e.x);
    for (var i = 0; i < count; i++) {
      var a = base + (count > 1 ? (i - (count - 1) / 2) * spread : 0);
      ebullets.push({ x: e.x, y: e.y + 10, vx: Math.cos(a) * speed, vy: Math.sin(a) * speed, r: 5, color: '#f472b6' });
    }
  }
  function ring(e, n, speed, offset, color) {
    for (var i = 0; i < n; i++) {
      var a = offset + i * Math.PI * 2 / n;
      ebullets.push({ x: e.x, y: e.y + 20, vx: Math.cos(a) * speed, vy: Math.sin(a) * speed, r: 5, color: color || '#fbbf24' });
    }
  }

  function hitPlayer() {
    if (player.inv > 0 || ending) return;
    if (player.shield) {
      player.shield = false; player.inv = 1; game.audio.sfx('hit');
      rings.push({ x: player.x, y: player.y, r: 20, max: 70, life: 0.4, color: '#34d399' });
      return;
    }
    lives--; stats();
    boom(player.x, player.y, '#22d3ee', 40, true);
    game.audio.sfx('explode'); shake = 14; flash = 0.3;
    player.power = Math.max(1, player.power - 1);
    player.inv = 2.2;
    ebullets = [];
    if (lives <= 0) { ending = 1.2; }
  }

  function killEnemy(e) {
    e.dead = true;
    score += e.pts;
    floats.push({ x: e.x, y: e.y, text: '+' + e.pts, life: 0.8, color: '#fde68a' });
    if (e.type === 'boss') {
      boom(e.x, e.y, '#f472b6', 120, true); boom(e.x - 40, e.y + 10, '#fbbf24', 60, true); boom(e.x + 40, e.y - 10, '#22d3ee', 60, true);
      shake = 22; flash = 0.6; game.audio.sfx('explode'); setTimeout(function () { game.audio.sfx('bonus'); }, 300);
      ebullets = [];
      caps.push({ x: e.x - 30, y: e.y, k: 'P' }, { x: e.x + 30, y: e.y, k: 'P' }, { x: e.x, y: e.y + 20, k: 'H' });
    } else {
      boom(e.x, e.y, e.type === 'drone' ? '#f472b6' : e.type === 'swoop' ? '#a78bfa' : '#fbbf24', e.type === 'gunner' ? 30 : 18, e.type === 'gunner');
      game.audio.sfx(e.type === 'gunner' ? 'explode' : 'hit');
      var chance = e.type === 'gunner' ? 0.35 : 0.07;
      if (game.rng() < chance) {
        var r = game.rng();
        caps.push({ x: e.x, y: e.y, k: r < 0.6 ? 'P' : r < 0.9 ? 'S' : 'H' });
      }
    }
    stats();
  }

  // ---------------------------------------------------------------- update
  function update(dt) {
    time += dt;
    if (ending) {
      ending -= dt;
      updateWorld(dt, true);
      if (ending <= 0) { ending = 0; game.over({ score: score, lines: [TXT.reached + ': ' + wave + ' / ' + MAX_WAVE] }); }
      return;
    }
    // movement
    var kx = (game.keys.ArrowRight || game.keys.d || game.keys.D ? 1 : 0) - (game.keys.ArrowLeft || game.keys.a || game.keys.A ? 1 : 0);
    var ky = (game.keys.ArrowDown || game.keys.s || game.keys.S ? 1 : 0) - (game.keys.ArrowUp || game.keys.w || game.keys.W ? 1 : 0);
    if (kx || ky) { var m = Math.hypot(kx, ky); player.x += kx / m * 380 * dt; player.y += ky / m * 380 * dt; drag = null; }
    player.x = Math.max(18, Math.min(W - 18, player.x)); player.y = Math.max(60, Math.min(H - 30, player.y));
    if (player.inv > 0) player.inv -= dt;
    // auto-fire
    fireT -= dt;
    if (fireT <= 0) {
      fireT = 0.13;
      var p = player.power, angles = p === 1 ? [0] : p === 2 ? [-0.02, 0.02] : p === 3 ? [-0.14, 0, 0.14] : [-0.26, -0.11, 0, 0.11, 0.26];
      angles.forEach(function (a, i) {
        var off = p === 2 ? (i ? 7 : -7) : 0;
        shots.push({ x: player.x + off + Math.sin(a) * 6, y: player.y - 18, vx: Math.sin(a) * 760, vy: -Math.cos(a) * 760 });
      });
      game.audio.tone(980, 0.03, 'square', 0.025, 700);
    }
    updateWorld(dt, false);
    // schedule
    waveT += dt;
    for (var i = schedule.length - 1; i >= 0; i--) if (schedule[i].at <= waveT) { schedule[i].make(); schedule.splice(i, 1); }
    if (!schedule.length && !enemies.length) {
      between += dt;
      if (between > 0.1 && between - dt <= 0.1) {
        var bonus = 500 * wave; score += bonus;
        floats.push({ x: W / 2, y: H / 2, text: TXT.cleared + '  +' + bonus, life: 1.4, color: '#22d3ee' });
        stats();
        if (wave >= MAX_WAVE) game.audio.sfx('win');
      }
      if (between > 1.8) {
        if (wave >= MAX_WAVE) { score += lives * 5000; stats(); game.over({ score: score, win: true, lines: [TXT.victory] }); return; }
        startWave(wave + 1);
      }
    }
  }

  function updateWorld(dt, frozenPlayer) {
    // shots
    for (var i = shots.length - 1; i >= 0; i--) {
      var sh = shots[i]; sh.x += sh.vx * dt; sh.y += sh.vy * dt;
      if (sh.y < -20 || sh.x < -20 || sh.x > W + 20) { shots.splice(i, 1); continue; }
      for (var j = 0; j < enemies.length; j++) {
        var e = enemies[j];
        if (e.dead || e.y < -20) continue;
        var dx = sh.x - e.x, dy = sh.y - e.y;
        var hit = e.type === 'boss' ? (Math.abs(dx) < 70 && Math.abs(dy) < 36) : dx * dx + dy * dy < (e.r + 3) * (e.r + 3);
        if (hit) {
          e.hp--; e.flash = 0.06; shots.splice(i, 1);
          particles.push({ x: sh.x, y: sh.y, vx: (Math.random() - 0.5) * 100, vy: 60 + Math.random() * 60, life: 0.2, color: '#a5f3fc', r: 1.5 });
          if (e.hp <= 0) killEnemy(e);
          break;
        }
      }
    }
    // enemies
    enemies.forEach(function (e) {
      e.t += dt; if (e.flash > 0) e.flash -= dt;
      if (e.type === 'drone') {
        e.y += e.vy * dt; e.x += Math.cos(e.t * 2.4 + e.phase) * e.amp * dt;
        e.fire -= dt;
        if (e.fire <= 0 && e.y > 40 && e.y < H * 0.6) { e.fire = rnd(2.2, 4) - wave * 0.08; if (wave >= 2 && !frozenPlayer) fireAt(e, 170 + wave * 7, 0, 1); }
        if (e.y > H + 40) e.dead = true;
      } else if (e.type === 'swoop') {
        var u = e.t * 0.55;
        e.x = (e.dir > 0 ? -30 : W + 30) + e.dir * u * (W + 60);
        e.y = 60 + Math.sin(Math.min(1, u) * Math.PI) * e.depth;
        e.fire -= dt;
        if (e.fire <= 0 && u > 0.2 && u < 0.8) { e.fire = rnd(1.4, 2.4); if (!frozenPlayer) fireAt(e, 190 + wave * 6, 0, 1); }
        if (u > 1.05) e.dead = true;
      } else if (e.type === 'gunner') {
        if (e.t < 8) e.y += (e.stopY - e.y) * Math.min(1, dt * 1.5);
        else e.y += 120 * dt;
        e.x += Math.sin(e.t * 0.9) * 30 * dt;
        e.fire -= dt;
        if (e.fire <= 0 && e.y > 40) { e.fire = Math.max(0.9, 1.8 - wave * 0.05); if (!frozenPlayer) { fireAt(e, 210 + wave * 6, 0.22, 3); game.audio.tone(240, 0.08, 'sawtooth', 0.05, 160); } }
        if (e.y > H + 40) e.dead = true;
      } else if (e.type === 'boss') {
        e.y += (130 - e.y) * Math.min(1, dt * 0.9);
        e.x = W / 2 + Math.sin(e.t * 0.55) * (150 + 10 * e.k);
        if (e.y < 110) return;
        e.patT += dt; e.shotT -= dt;
        if (e.patT > 5) { e.patT = 0; e.pattern = (e.pattern + 1) % (e.k >= 2 ? 3 : 2); }
        if (e.shotT <= 0 && !frozenPlayer) {
          if (e.pattern === 0) { ring(e, 12 + e.k * 4, 140 + e.k * 20, e.t, '#fbbf24'); e.shotT = 1.5 - e.k * 0.15; }
          else if (e.pattern === 1) { fireAt(e, 230 + e.k * 20, 0.18, 3 + e.k * 2 - 2); e.shotT = 0.75 - e.k * 0.08; }
          else { ring(e, 3 + e.k, 170, e.t * 3, '#f472b6'); e.shotT = 0.14; }
        }
      }
      // body collision
      if (!frozenPlayer && !e.dead) {
        var bx = player.x - e.x, by = player.y - e.y;
        var touch = e.type === 'boss' ? (Math.abs(bx) < 70 && Math.abs(by) < 40) : bx * bx + by * by < (e.r + 8) * (e.r + 8);
        if (touch && player.inv <= 0) { hitPlayer(); if (e.type !== 'boss') { e.hp -= 3; if (e.hp <= 0) killEnemy(e); } }
      }
    });
    enemies = enemies.filter(function (e) { return !e.dead; });
    // enemy bullets
    for (var b = ebullets.length - 1; b >= 0; b--) {
      var eb = ebullets[b]; eb.x += eb.vx * dt; eb.y += eb.vy * dt;
      if (eb.y > H + 20 || eb.y < -30 || eb.x < -20 || eb.x > W + 20) { ebullets.splice(b, 1); continue; }
      if (!frozenPlayer) {
        var ddx = eb.x - player.x, ddy = eb.y - player.y;
        if (ddx * ddx + ddy * ddy < (eb.r + 5) * (eb.r + 5)) { ebullets.splice(b, 1); hitPlayer(); }
      }
    }
    // capsules
    for (var c = caps.length - 1; c >= 0; c--) {
      var cp = caps[c]; cp.y += 110 * dt; cp.x += Math.sin(time * 3 + c) * 20 * dt;
      if (!frozenPlayer && Math.abs(cp.x - player.x) < 24 && Math.abs(cp.y - player.y) < 26) {
        caps.splice(c, 1); game.audio.sfx('bonus');
        if (cp.k === 'P') { if (player.power < 4) player.power++; else { score += 1000; stats(); } }
        else if (cp.k === 'S') player.shield = true;
        else { lives = Math.min(lives + 1, 6); stats(); }
        floats.push({ x: player.x, y: player.y - 30, text: cp.k === 'P' ? (player.power >= 4 ? 'MAX' : 'POWER ' + player.power) : cp.k === 'S' ? 'SHIELD' : '+1 ♥', life: 0.9, color: '#a5f3fc' });
        continue;
      }
      if (cp.y > H + 20) caps.splice(c, 1);
    }
  }

  // ---------------------------------------------------------------- render
  function drawShip(x, y) {
    ctx.save(); ctx.translate(x, y);
    // engine flame
    var fl = 10 + Math.random() * 8;
    var fg = ctx.createLinearGradient(0, 10, 0, 14 + fl);
    fg.addColorStop(0, '#a5f3fc'); fg.addColorStop(1, 'rgba(124,92,255,0)');
    ctx.fillStyle = fg; ctx.beginPath(); ctx.moveTo(-6, 10); ctx.lineTo(0, 14 + fl); ctx.lineTo(6, 10); ctx.closePath(); ctx.fill();
    ctx.shadowColor = '#22d3ee'; ctx.shadowBlur = 18;
    var g = ctx.createLinearGradient(0, -22, 0, 14);
    g.addColorStop(0, '#e0fbff'); g.addColorStop(1, '#0e7490');
    ctx.fillStyle = g;
    ctx.beginPath(); ctx.moveTo(0, -24); ctx.lineTo(8, -4); ctx.lineTo(20, 8); ctx.lineTo(18, 14); ctx.lineTo(6, 10); ctx.lineTo(0, 13); ctx.lineTo(-6, 10); ctx.lineTo(-18, 14); ctx.lineTo(-20, 8); ctx.lineTo(-8, -4); ctx.closePath(); ctx.fill();
    ctx.shadowBlur = 0;
    ctx.fillStyle = '#7c5cff'; ctx.beginPath(); ctx.moveTo(0, -12); ctx.lineTo(4, 0); ctx.lineTo(-4, 0); ctx.closePath(); ctx.fill();
    ctx.fillStyle = '#fff'; ctx.beginPath(); ctx.arc(0, 2, 3, 0, Math.PI * 2); ctx.fill();
    ctx.restore();
  }
  function drawEnemy(e) {
    ctx.save(); ctx.translate(e.x, e.y);
    var white = e.flash > 0;
    if (e.type === 'drone') {
      ctx.rotate(Math.sin(e.t * 3) * 0.2);
      ctx.shadowColor = '#f472b6'; ctx.shadowBlur = 14; ctx.fillStyle = white ? '#fff' : '#be185d';
      ctx.beginPath(); ctx.moveTo(0, 16); ctx.lineTo(15, 0); ctx.lineTo(0, -14); ctx.lineTo(-15, 0); ctx.closePath(); ctx.fill();
      ctx.shadowBlur = 0; ctx.fillStyle = '#fde68a'; ctx.beginPath(); ctx.arc(0, 2, 4, 0, Math.PI * 2); ctx.fill();
    } else if (e.type === 'swoop') {
      ctx.shadowColor = '#a78bfa'; ctx.shadowBlur = 14; ctx.fillStyle = white ? '#fff' : '#6d28d9';
      ctx.beginPath(); ctx.moveTo(0, 14); ctx.lineTo(18, -10); ctx.lineTo(8, -6); ctx.lineTo(0, -14); ctx.lineTo(-8, -6); ctx.lineTo(-18, -10); ctx.closePath(); ctx.fill();
      ctx.shadowBlur = 0; ctx.fillStyle = '#c4b5fd'; ctx.fillRect(-3, -4, 6, 8);
    } else if (e.type === 'gunner') {
      ctx.shadowColor = '#fbbf24'; ctx.shadowBlur = 16; ctx.fillStyle = white ? '#fff' : '#b45309';
      ctx.beginPath(); for (var i = 0; i < 6; i++) { var a = i * Math.PI / 3 + Math.PI / 6; ctx.lineTo(Math.cos(a) * 21, Math.sin(a) * 21); } ctx.closePath(); ctx.fill();
      ctx.shadowBlur = 0; ctx.fillStyle = '#0b0d17'; ctx.beginPath(); ctx.arc(0, 0, 9, 0, Math.PI * 2); ctx.fill();
      var aim = Math.atan2(player.y - e.y, player.x - e.x);
      ctx.fillStyle = '#fde68a'; ctx.beginPath(); ctx.arc(Math.cos(aim) * 4, Math.sin(aim) * 4, 4, 0, Math.PI * 2); ctx.fill();
    } else if (e.type === 'boss') {
      ctx.shadowColor = '#f472b6'; ctx.shadowBlur = 30;
      var bg = ctx.createLinearGradient(0, -40, 0, 40); bg.addColorStop(0, white ? '#fff' : '#4c1d95'); bg.addColorStop(1, white ? '#fff' : '#9d174d');
      ctx.fillStyle = bg;
      ctx.beginPath(); ctx.moveTo(-80, -20); ctx.lineTo(-50, -38); ctx.lineTo(50, -38); ctx.lineTo(80, -20); ctx.lineTo(70, 14); ctx.lineTo(30, 38); ctx.lineTo(-30, 38); ctx.lineTo(-70, 14); ctx.closePath(); ctx.fill();
      ctx.shadowBlur = 0;
      ctx.fillStyle = '#1e1b4b'; ctx.fillRect(-56, -8, 112, 14);
      for (var k = 0; k < 5; k++) { ctx.fillStyle = (Math.floor(e.t * 6) + k) % 5 === 0 ? '#f472b6' : '#7c5cff'; ctx.fillRect(-50 + k * 22, -5, 14, 8); }
      var pulse = 10 + Math.sin(e.t * 6) * 2;
      ctx.fillStyle = '#fbbf24'; ctx.shadowColor = '#fbbf24'; ctx.shadowBlur = 20;
      ctx.beginPath(); ctx.arc(0, 22, pulse, 0, Math.PI * 2); ctx.fill();
    }
    ctx.restore();
  }
  function render(dt) {
    var live = game.state !== 'paused';
    if (live) {
      stars.forEach(function (st) { st.y += st.z * st.z * 220 * dt; if (st.y > H) { st.y = 0; st.x = Math.random() * W; } });
      for (var i = particles.length - 1; i >= 0; i--) { var p = particles[i]; p.life -= dt; p.x += p.vx * dt; p.y += p.vy * dt; p.vx *= 0.96; p.vy *= 0.96; if (p.life <= 0) particles.splice(i, 1); }
      for (var r = rings.length - 1; r >= 0; r--) { var rg = rings[r]; rg.life -= dt; rg.r += (rg.max - rg.r) * Math.min(1, dt * 8); if (rg.life <= 0) rings.splice(r, 1); }
      for (var f = floats.length - 1; f >= 0; f--) { floats[f].life -= dt; floats[f].y -= 30 * dt; if (floats[f].life <= 0) floats.splice(f, 1); }
      if (banner) { banner.life -= dt; if (banner.life <= 0) banner = null; }
      shake = Math.max(0, shake - dt * 30); flash = Math.max(0, flash - dt);
    }
    ctx.save();
    if (shake) ctx.translate((Math.random() - 0.5) * shake, (Math.random() - 0.5) * shake);
    var g = ctx.createLinearGradient(0, 0, 0, H); g.addColorStop(0, '#0b0d17'); g.addColorStop(0.6, '#130f2e'); g.addColorStop(1, '#1b0f2a');
    ctx.fillStyle = g; ctx.fillRect(-20, -20, W + 40, H + 40);
    var neb = ctx.createRadialGradient(W * 0.75, H * 0.3, 10, W * 0.75, H * 0.3, 260); neb.addColorStop(0, 'rgba(124,92,255,0.18)'); neb.addColorStop(1, 'rgba(124,92,255,0)');
    ctx.fillStyle = neb; ctx.fillRect(0, 0, W, H);
    stars.forEach(function (st) { ctx.globalAlpha = st.z; ctx.fillStyle = st.z > 0.8 ? '#a5f3fc' : '#fff'; ctx.fillRect(st.x, st.y, st.z * 2, st.z * 2 + (st.z > 0.7 ? 3 : 0)); });
    ctx.globalAlpha = 1;
    if (!player) { ctx.restore(); return; }

    // capsules
    caps.forEach(function (cp) {
      var col = cp.k === 'P' ? '#22d3ee' : cp.k === 'S' ? '#34d399' : '#f472b6';
      ctx.save(); ctx.shadowColor = col; ctx.shadowBlur = 16; ctx.fillStyle = col;
      ctx.beginPath(); ctx.arc(cp.x, cp.y, 12, 0, Math.PI * 2); ctx.fill(); ctx.restore();
      ctx.fillStyle = '#0b0d17'; ctx.font = '900 13px system-ui, sans-serif'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
      ctx.fillText(cp.k === 'H' ? '♥' : cp.k, cp.x, cp.y + 1); ctx.textBaseline = 'alphabetic';
    });
    enemies.forEach(drawEnemy);
    // player shots
    ctx.save(); ctx.shadowColor = '#22d3ee'; ctx.shadowBlur = 10; ctx.fillStyle = '#cffafe';
    shots.forEach(function (sh) { ctx.fillRect(sh.x - 1.5, sh.y - 9, 3, 16); });
    ctx.restore();
    // player
    if (!(ending && lives <= 0) && !(player.inv > 0 && Math.floor(player.inv * 12) % 2 === 0)) {
      drawShip(player.x, player.y);
      if (player.shield) { ctx.strokeStyle = 'rgba(52,211,153,' + (0.5 + Math.sin(time * 6) * 0.2) + ')'; ctx.lineWidth = 2.5; ctx.beginPath(); ctx.arc(player.x, player.y, 28, 0, Math.PI * 2); ctx.stroke(); }
    }
    // enemy bullets
    ebullets.forEach(function (eb) {
      ctx.save(); ctx.shadowColor = eb.color; ctx.shadowBlur = 12; ctx.fillStyle = eb.color;
      ctx.beginPath(); ctx.arc(eb.x, eb.y, eb.r, 0, Math.PI * 2); ctx.fill(); ctx.restore();
      ctx.fillStyle = '#fff'; ctx.beginPath(); ctx.arc(eb.x, eb.y, eb.r * 0.45, 0, Math.PI * 2); ctx.fill();
    });
    // fx
    rings.forEach(function (rg) { ctx.globalAlpha = Math.max(0, rg.life * 2); ctx.strokeStyle = rg.color; ctx.lineWidth = 3; ctx.beginPath(); ctx.arc(rg.x, rg.y, rg.r, 0, Math.PI * 2); ctx.stroke(); });
    particles.forEach(function (p) { ctx.globalAlpha = Math.max(0, Math.min(1, p.life * 2)); ctx.fillStyle = p.color; ctx.beginPath(); ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2); ctx.fill(); });
    ctx.globalAlpha = 1;
    ctx.textAlign = 'center';
    floats.forEach(function (fl) { ctx.globalAlpha = Math.max(0, Math.min(1, fl.life * 2)); ctx.fillStyle = fl.color; ctx.font = '800 14px system-ui, sans-serif'; ctx.fillText(fl.text, fl.x, fl.y); });
    ctx.globalAlpha = 1;
    // boss hp bar
    var boss = enemies.filter(function (e) { return e.type === 'boss'; })[0];
    if (boss) {
      ctx.fillStyle = 'rgba(255,255,255,0.1)'; ctx.fillRect(40, 16, W - 80, 8);
      var bg2 = ctx.createLinearGradient(40, 0, W - 40, 0); bg2.addColorStop(0, '#f472b6'); bg2.addColorStop(1, '#7c5cff');
      ctx.fillStyle = bg2; ctx.fillRect(40, 16, (W - 80) * Math.max(0, boss.hp / boss.max), 8);
    }
    // power pips
    for (var pp = 0; pp < 4; pp++) { ctx.fillStyle = pp < player.power ? '#22d3ee' : 'rgba(255,255,255,0.15)'; ctx.fillRect(12 + pp * 14, H - 16, 10, 6); }
    if (banner) {
      ctx.globalAlpha = Math.min(1, banner.life * 2);
      ctx.save(); ctx.shadowColor = banner.color; ctx.shadowBlur = 20; ctx.fillStyle = banner.color;
      ctx.font = '900 30px system-ui, sans-serif'; ctx.textAlign = 'center'; ctx.fillText(banner.text, W / 2, H * 0.4); ctx.restore();
      ctx.globalAlpha = 1;
    }
    if (flash > 0) { ctx.fillStyle = 'rgba(255,255,255,' + flash * 0.6 + ')'; ctx.fillRect(0, 0, W, H); }
    ctx.restore();
  }

  // ---------------------------------------------------------------- input: relative drag
  var drag = null;
  game.stage.addEventListener('pointerdown', function (e) {
    if (game.state !== 'playing' || !player || e.target.closest('.ng-overlay')) return;
    var p = view.toLogical(e.clientX, e.clientY);
    drag = { id: e.pointerId, sx: p.x, sy: p.y, px: player.x, py: player.y };
    game.audio.ensure();
  });
  window.addEventListener('pointermove', function (e) {
    if (!drag || drag.id !== e.pointerId || game.state !== 'playing') return;
    var p = view.toLogical(e.clientX, e.clientY);
    player.x = drag.px + (p.x - drag.sx) * 1.25; player.y = drag.py + (p.y - drag.sy) * 1.25;
  });
  function endDrag(e) { if (drag && drag.id === e.pointerId) drag = null; }
  window.addEventListener('pointerup', endDrag); window.addEventListener('pointercancel', endDrag);

  game.on('start', reset);
  game.on('pause', function () { drag = null; });
  game.loop(update, render);

  game.showMenu();
  game.ready();
})();
