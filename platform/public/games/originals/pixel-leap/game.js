/* Pixel Leap – original neon platformer for Nebulo.
 * Six hand-built levels with moving platforms, spikes, bounce pads, coins and a goal flag.
 * Run + jump with coyote time, jump buffering and variable jump height.
 */
(function () {
  'use strict';
  var L = { en: 0, ka: 1, tr: 2, ru: 3 }[NebuloGame.lang] || 0;
  var TXT = {
    tagline: ['Run, jump and dash across six neon worlds to raise the final flag.',
      'ირბინე, იხტუნე და გადაკვეთე ექვსი ნეონის სამყარო ბოლო დროშამდე.',
      'Koş, zıpla ve son bayrağa ulaşmak için altı neon dünyayı geç.',
      'Бегите, прыгайте и пройдите шесть неоновых миров до финального флага.'][L],
    how: [
      ['◀ ▶ / A D to run, Space / ▲ / W to jump (hold for a higher jump).', 'Reach the glowing flag to finish each of the 6 levels.', 'Spikes and pits cost a life – you have 3.', 'Purple platforms move: ride them across gaps. Green pads launch you high.', 'Coins +10, level clear +100 plus a time bonus.'],
      ['◀ ▶ / A D — სირბილი, Space / ▲ / W — ხტომა (დაიჭირე მაღლა ხტომისთვის).', 'მიაღწიე მანათობელ დროშას თითოეულ 6 დონეზე.', 'ეკლები და უფსკრული სიცოცხლეს გართმევს — გაქვს 3.', 'იისფერი პლატფორმები მოძრაობს: გადაიარე მათით. მწვანე ბალიში მაღლა გისვრის.', 'მონეტა +10, დონის გავლა +100 და დროის ბონუსი.'],
      ['◀ ▶ / A D ile koş, Space / ▲ / W ile zıpla (yüksek zıplamak için basılı tut).', '6 seviyenin her birini bitirmek için parlayan bayrağa ulaş.', 'Dikenler ve çukurlar can götürür – 3 canın var.', 'Mor platformlar hareket eder: boşlukları onlarla geç. Yeşil yaylar seni yükseğe fırlatır.', 'Altın +10, seviye bitirme +100 ve süre bonusu.'],
      ['◀ ▶ / A D — бег, Пробел / ▲ / W — прыжок (удерживайте для высокого прыжка).', 'Доберитесь до светящегося флага на каждом из 6 уровней.', 'Шипы и пропасти отнимают жизнь — у вас их 3.', 'Фиолетовые платформы движутся: перебирайтесь на них через пропасти. Зелёные пружины подбрасывают высоко.', 'Монета +10, уровень +100 и бонус за время.'],
    ][L],
    names: [
      ['First Steps', 'Spike Garden', 'Drifting Bridges', 'Sky Lift', 'Spring Fever', 'Neon Summit'],
      ['პირველი ნაბიჯები', 'ეკლების ბაღი', 'მოცურავე ხიდები', 'ციური ლიფტი', 'ზამბარების ციებ-ცხელება', 'ნეონის მწვერვალი'],
      ['İlk Adımlar', 'Diken Bahçesi', 'Süzülen Köprüler', 'Gökyüzü Asansörü', 'Yay Çılgınlığı', 'Neon Zirve'],
      ['Первые шаги', 'Сад шипов', 'Плывущие мосты', 'Небесный лифт', 'Пружинная лихорадка', 'Неоновая вершина'],
    ][L],
    clear: ['Level clear!', 'დონე გავლილია!', 'Seviye tamam!', 'Уровень пройден!'][L],
    allClear: ['All six worlds conquered!', 'ექვსივე სამყარო დაპყრობილია!', 'Altı dünyanın hepsi fethedildi!', 'Все шесть миров покорены!'][L],
    outOfLives: ['Out of lives.', 'სიცოცხლე აღარ დაგრჩა.', 'Canın kalmadı.', 'Жизни закончились.'][L],
    reached: ['Reached level', 'მიღწეული დონე', 'Ulaşılan seviye', 'Достигнут уровень'][L],
    coins: ['Coins', 'მონეტები', 'Altın', 'Монеты'][L],
  };

  var game = NebuloGame.create({ id: 'pixel-leap', title: 'Pixel Leap', tagline: TXT.tagline, howTo: TXT.how });
  var W = 800, H = 450, T = 30, ROWS = 15;
  var view = game.canvas(W, H), ctx = view.ctx;
  var G = 2100, JUMP = 720, RUN = 235, ACC = 2200, FRICTION = 2600, PAD_V = 1110;
  var PW = 20, PH = 26;

  // ---------------------------------------------------------------- level definitions (builder DSL)
  var LEVELS = [
    { w: 60, build: function (b) {
      b.start(2, 12); b.ground(0, 20); b.coins(6, 11, 3);
      b.block(11, 11, 5, 2); b.coins(12, 10, 3);
      b.ground(24, 31); b.coins(21, 10, 3);
      b.ground(34, 44); b.block(37, 11, 4, 2); b.coins(37, 10, 4);
      b.plat(41, 9, 3); b.coins(41, 8, 3);
      b.ground(48, 59); b.coins(45, 10, 3); b.flag(56, 12);
    } },
    { w: 72, build: function (b) {
      b.start(2, 12); b.ground(0, 35);
      b.spikes(9, 2); b.coins(9, 10, 2);
      b.spikes(16, 3); b.coins(16, 9, 3);
      b.block(22, 10, 3, 3); b.coins(22, 9, 3);
      b.spikes(25, 3); b.block(28, 10, 3, 3);
      b.coins(36, 10, 4); b.ground(40, 71);
      b.block(44, 11, 9, 2); b.spikes(47, 3, 10); b.coins(47, 8, 3);
      b.plat(56, 10, 3); b.plat(60, 7, 3); b.coins(60, 6, 3);
      b.spikes(58, 5); b.flag(68, 12);
    } },
    { w: 72, build: function (b) {
      b.start(2, 12); b.ground(0, 12); b.coins(5, 11, 4);
      b.mover('H', 14, 11, 4); b.coins(16, 9, 3);
      b.ground(23, 26); b.coins(23, 11, 4);
      b.mover('H', 28, 11, 5); b.coins(31, 9, 3);
      b.ground(38, 69); b.spikes(42, 2);
      b.mover('V', 47, 12, 5); b.block(51, 6, 4, 7); b.coins(51, 5, 4);
      b.spikes(60, 2); b.coins(59, 10, 4); b.flag(66, 12);
    } },
    { w: 60, build: function (b) {
      b.start(2, 12); b.ground(0, 9); b.coins(4, 11, 3);
      b.mover('V', 11, 12, 6); b.coins(12, 5, 1);
      b.plat(16, 6, 4); b.coins(16, 5, 4);
      b.mover('H', 21, 6, 4); b.coins(23, 4, 3);
      b.plat(30, 6, 3); b.coins(30, 5, 3);
      b.ground(34, 59); b.spikes(36, 2);
      b.pad(40, 12); b.block(43, 5, 3, 8); b.coins(43, 4, 3); b.coins(40, 6, 1); b.coins(40, 8, 1);
      b.spikes(48, 2); b.spikes(53, 2); b.coins(48, 10, 2); b.flag(57, 12);
    } },
    { w: 74, build: function (b) {
      b.start(2, 12); b.ground(0, 8); b.pad(6, 12);
      b.plat(10, 4, 5); b.coins(10, 3, 5);
      b.ground(9, 20); b.spikes(9, 12);
      b.plat(18, 5, 3); b.coins(18, 4, 3);
      b.ground(22, 45); b.spikes(25, 2); b.coins(25, 10, 2);
      b.pad(29, 12); b.block(32, 4, 3, 9); b.coins(32, 3, 3);
      b.spikes(40, 2); b.spikes(43, 1); b.coins(40, 10, 4);
      b.mover('H', 47, 10, 5); b.coins(49, 8, 4);
      b.ground(56, 73); b.spikes(60, 1); b.spikes(64, 1); b.coins(59, 10, 7); b.flag(70, 12);
    } },
    { w: 92, build: function (b) {
      b.start(2, 12); b.ground(0, 10); b.spikes(5, 2);
      b.mover('H', 12, 11, 6); b.coins(14, 9, 5);
      b.ground(23, 30); b.block(26, 10, 2, 3); b.coins(26, 9, 2);
      b.mover('V', 31, 12, 5); b.coins(32, 6, 1);
      b.plat(35, 7, 4); b.coins(35, 6, 4); b.plat(41, 7, 3);
      b.ground(44, 70); b.spikes(49, 3); b.spikes(54, 2); b.coins(49, 10, 3); b.coins(54, 10, 2);
      b.pad(58, 12); b.block(61, 4, 3, 9); b.coins(61, 3, 3); b.coins(58, 7, 1);
      b.mover('H', 71, 10, 6); b.coins(73, 8, 6);
      b.ground(83, 91); b.coins(83, 11, 3); b.flag(88, 12);
    } },
  ];

  function buildLevel(i) {
    var def = LEVELS[i], grid = [], movers = [], start = { x: 1, y: 12 }, flag = { x: def.w - 3, y: 12 };
    for (var r = 0; r < ROWS; r++) { var row = []; for (var c = 0; c < def.w; c++) row.push('.'); grid.push(row); }
    function set(x, y, ch) { if (x >= 0 && x < def.w && y >= 0 && y < ROWS) grid[y][x] = ch; }
    var b = {
      ground: function (a, z) { for (var x = a; x <= z; x++) { set(x, 13, '#'); set(x, 14, '#'); } },
      block: function (x, y, w, h) { for (var i2 = 0; i2 < w; i2++) for (var j = 0; j < h; j++) set(x + i2, y + j, '#'); },
      plat: function (x, y, w) { for (var i2 = 0; i2 < w; i2++) set(x + i2, y, '-'); },
      coins: function (x, y, n) { for (var i2 = 0; i2 < n; i2++) set(x + i2, y, 'o'); },
      spikes: function (x, n, row) { for (var i2 = 0; i2 < n; i2++) set(x + i2, row === undefined ? 12 : row, '^'); },
      pad: function (x, row) { set(x, row, 'B'); },
      mover: function (type, x, y, range) { movers.push({ type: type, x0: x * T, y0: y * T, x: x * T, y: y * T, w: 3 * T, range: range * T, period: 2.6 + range * 0.35, dx: 0, dy: 0 }); },
      start: function (x, y) { start = { x: x, y: y }; },
      flag: function (x, y) { flag = { x: x, y: y }; },
    };
    def.build(b);
    var coinCount = 0;
    grid.forEach(function (row) { row.forEach(function (ch) { if (ch === 'o') coinCount++; }); });
    return { w: def.w, pw: def.w * T, grid: grid, movers: movers, start: start, flag: flag, coins: coinCount };
  }

  var s, lv, p, parts = [], clock = 0, stars = [];
  (function () { var r = NebuloGame.mulberry32(99); for (var i = 0; i < 70; i++) stars.push({ x: r() * 1600, y: r() * 300, z: 0.1 + r() * 0.3, s: r() < 0.2 ? 2 : 1 }); })();

  function newGame() {
    s = { level: 0, lives: 3, score: 0, coinsTotal: 0, t: 0, levelT: 0, dead: 0, clear: 0, banner: 2.2, ended: false, log: [] };
    loadLevel(0);
    pushStats();
  }
  function loadLevel(i) {
    s.level = i; lv = buildLevel(i); s.levelT = 0; s.banner = 2.0; s.clear = 0;
    spawnPlayer();
  }
  function spawnPlayer() {
    p = { x: lv.start.x * T + (T - PW) / 2, y: (lv.start.y + 1) * T - PH, vx: 0, vy: 0, ground: false, coyote: 0, buffer: 0, face: 1,
      mover: null, squash: 1, cut: false, camX: 0 };
    lv.movers.forEach(function (m) { m.t = 0; });
    s.camX = Math.max(0, Math.min(lv.pw - W, p.x - W / 2));
    s.dead = 0;
  }
  function pushStats() {
    game.setStat('score', s.score);
    game.setStat('level', (s.level + 1) + '/6');
    game.setStat('lives', s.lives);
  }

  function cell(cx, cy) { if (cx < 0 || cx >= lv.w) return '#'; if (cy < 0 || cy >= ROWS) return '.'; return lv.grid[cy][cx]; }
  function isSolid(ch) { return ch === '#' || ch === 'B'; }

  function burst(x, y, n, cols, spd, life, grav) {
    for (var i = 0; i < n; i++) {
      var a = Math.random() * Math.PI * 2, v = spd * (0.3 + Math.random() * 0.7);
      parts.push({ x: x, y: y, vx: Math.cos(a) * v, vy: Math.sin(a) * v - (grav ? 120 : 0), g: grav ? 900 : 0, life: life * (0.6 + Math.random() * 0.4), max: life, c: cols[i % cols.length], size: 2 + Math.random() * 3 });
    }
  }

  function jumpHeld() { var k = game.keys; return k[' '] || k.Space || k.ArrowUp || k.w || k.W || k.z || k.Z; }

  function die() {
    if (s.dead > 0) return;
    s.dead = 1.0; s.lives--; pushStats();
    burst(p.x + PW / 2, p.y + PH / 2, 40, ['#22d3ee', '#f472b6', '#ffffff'], 320, 0.9, true);
    game.audio.sfx('hit');
  }

  function completeLevel() {
    s.clear = 1.6;
    var bonus = 100 + Math.max(0, Math.floor(45 - s.levelT)) * 5;
    s.score += bonus; s.lastBonus = bonus; s.log.push({ l: s.level, t: Math.round(s.levelT * 10) / 10 });
    pushStats();
    burst(lv.flag.x * T + 15, lv.flag.y * T - 20, 50, ['#f472b6', '#22d3ee', '#7c5cff', '#fde047'], 300, 1.2, true);
    game.audio.sfx('win');
  }

  function update(dt) {
    clock += dt; s.t += dt;
    stepParts(dt);
    if (s.banner > 0) s.banner -= dt;
    // movers
    lv.movers.forEach(function (m) {
      m.t += dt;
      var f = (1 - Math.cos(m.t * Math.PI * 2 / m.period)) / 2;
      var nx = m.type === 'H' ? m.x0 + f * m.range : m.x0;
      var ny = m.type === 'V' ? m.y0 - f * m.range : m.y0;
      m.dx = nx - m.x; m.dy = ny - m.y; m.py = m.y; m.x = nx; m.y = ny;
    });
    if (s.clear > 0) {
      s.clear -= dt;
      if (s.clear <= 0) {
        if (s.level >= LEVELS.length - 1) { finish(true); return; }
        loadLevel(s.level + 1); pushStats();
      }
      return;
    }
    if (s.dead > 0) {
      s.dead -= dt;
      if (s.dead <= 0) { if (s.lives <= 0) { finish(false); return; } spawnPlayer(); }
      return;
    }
    s.levelT += dt;
    var k = game.keys;
    var dir = ((k.ArrowRight || k.d || k.D) ? 1 : 0) - ((k.ArrowLeft || k.a || k.A) ? 1 : 0);
    if (dir) { p.vx += dir * ACC * dt; p.face = dir; } else {
      var fr = FRICTION * dt * (p.ground ? 1 : 0.35);
      p.vx = Math.abs(p.vx) <= fr ? 0 : p.vx - Math.sign(p.vx) * fr;
    }
    p.vx = Math.max(-RUN, Math.min(RUN, p.vx));

    // carried by mover
    if (p.mover) {
      var m = p.mover;
      p.x += m.dx;
      if (p.x + PW < m.x || p.x > m.x + m.w) p.mover = null; else p.y = m.y - PH;
    }

    if (p.ground) p.coyote = 0.1; else p.coyote -= dt;
    p.buffer -= dt;
    if (p.buffer > 0 && p.coyote > 0) {
      p.vy = -JUMP; p.ground = false; p.mover = null; p.coyote = 0; p.buffer = 0; p.cut = false; p.squash = 1.35;
      game.audio.sfx('jump');
      burst(p.x + PW / 2, p.y + PH, 8, ['#b4b9d6'], 90, 0.35, false);
    }
    if (!jumpHeld() && p.vy < -260 && !p.cut && !p.padBoost) { p.vy *= 0.5; p.cut = true; }
    if (p.vy >= 0) p.padBoost = false;

    p.vy = Math.min(1100, p.vy + G * dt);
    // horizontal
    p.x += p.vx * dt;
    var top = Math.floor(p.y / T), bot = Math.floor((p.y + PH - 1) / T), c, y;
    if (p.vx > 0) { c = Math.floor((p.x + PW) / T); for (y = top; y <= bot; y++) if (isSolid(cell(c, y))) { p.x = c * T - PW - 0.01; p.vx = 0; break; } }
    else if (p.vx < 0) { c = Math.floor(p.x / T); for (y = top; y <= bot; y++) if (isSolid(cell(c, y))) { p.x = (c + 1) * T + 0.01; p.vx = 0; break; } }
    // vertical
    var wasGround = p.ground;
    var prevBottom = p.y + PH;
    if (!p.mover) {
      p.y += p.vy * dt;
      p.ground = false;
      var l = Math.floor(p.x / T), r = Math.floor((p.x + PW - 1) / T), x;
      if (p.vy > 0) {
        var ry = Math.floor((p.y + PH) / T);
        for (x = l; x <= r; x++) {
          var ch = cell(x, ry);
          if (isSolid(ch) || (ch === '-' && prevBottom <= ry * T + 1)) {
            p.y = ry * T - PH; p.vy = 0; p.ground = true;
            if (ch === 'B') { p.vy = -PAD_V; p.ground = false; p.padBoost = true; p.padHit = { x: x, t: 0.25 }; game.audio.tone(220, 0.25, 'square', 0.12, 880); burst(x * T + 15, ry * T, 14, ['#34d399', '#a7f3d0'], 200, 0.5, false); }
            break;
          }
        }
        // movers (one-way from above)
        if (!p.ground && p.vy > 0) {
          for (var mi = 0; mi < lv.movers.length; mi++) {
            var mv = lv.movers[mi];
            if (p.x + PW > mv.x + 2 && p.x < mv.x + mv.w - 2 && prevBottom <= mv.py + 2 + Math.max(0, -mv.dy) && p.y + PH >= mv.y) {
              p.y = mv.y - PH; p.vy = 0; p.ground = true; p.mover = mv; break;
            }
          }
        }
      } else if (p.vy < 0) {
        var ty = Math.floor(p.y / T);
        for (x = l; x <= r; x++) if (isSolid(cell(x, ty))) { p.y = (ty + 1) * T; p.vy = 0; break; }
      }
    } else { p.ground = true; p.vy = 0; }
    if (p.ground && !wasGround) { p.squash = 0.7; burst(p.x + PW / 2, p.y + PH, 6, ['#7c5cff', '#b4b9d6'], 80, 0.3, false); }
    p.squash += (1 - p.squash) * Math.min(1, dt * 12);
    if (p.ground && Math.abs(p.vx) > 120 && Math.random() < 0.3) parts.push({ x: p.x + PW / 2, y: p.y + PH, vx: -p.vx * 0.2, vy: -30, g: 0, life: 0.3, max: 0.3, c: 'rgba(124,92,255,0.7)', size: 3 });
    if (p.padHit) { p.padHit.t -= dt; if (p.padHit.t <= 0) p.padHit = null; }

    // hazards & pickups
    var cl = Math.floor(p.x / T), cr = Math.floor((p.x + PW) / T), ct = Math.floor(p.y / T), cb = Math.floor((p.y + PH) / T);
    for (var yy = ct; yy <= cb; yy++) for (var xx = cl; xx <= cr; xx++) {
      var cc = cell(xx, yy);
      if (cc === 'o') {
        var dx = xx * T + 15 - (p.x + PW / 2), dy = yy * T + 15 - (p.y + PH / 2);
        if (dx * dx + dy * dy < 22 * 22) { lv.grid[yy][xx] = '.'; s.score += 10; s.coinsTotal++; pushStats(); game.audio.sfx('point'); burst(xx * T + 15, yy * T + 15, 10, ['#fde047', '#facc15', '#fff'], 150, 0.4, false); }
      } else if (cc === '^') {
        if (p.x + PW > xx * T + 5 && p.x < xx * T + 25 && p.y + PH > yy * T + 13) { die(); return; }
      }
    }
    var fx = lv.flag.x * T, fy = lv.flag.y * T;
    if (p.x + PW > fx + 6 && p.x < fx + 26 && p.y + PH > fy - 60 && p.y < fy + T) { completeLevel(); return; }
    if (p.y > H + 60) { die(); return; }

    var target = Math.max(0, Math.min(lv.pw - W, p.x + PW / 2 - W / 2 + p.face * 60));
    s.camX += (target - s.camX) * Math.min(1, dt * 5);
  }

  function finish(win) {
    if (s.ended) return; s.ended = true;
    game.over({
      score: s.score, win: win,
      title: win ? TXT.allClear : undefined,
      lines: win ? [TXT.coins + ': ' + s.coinsTotal] : [TXT.outOfLives, TXT.reached + ': ' + (s.level + 1) + '/6', TXT.coins + ': ' + s.coinsTotal],
      evidence: { levels: s.log, coins: s.coinsTotal, win: win },
    });
  }

  function stepParts(dt) {
    for (var i = parts.length - 1; i >= 0; i--) {
      var q = parts[i]; q.life -= dt; q.vy += (q.g || 0) * dt; q.x += q.vx * dt; q.y += q.vy * dt;
      if (q.life <= 0) parts.splice(i, 1);
    }
  }

  // ---------------------------------------------------------------- rendering
  function rr(x, y, w, h, r) {
    ctx.beginPath(); ctx.moveTo(x + r, y); ctx.arcTo(x + w, y, x + w, y + h, r); ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r); ctx.arcTo(x, y, x + w, y, r); ctx.closePath();
  }

  function drawBackground(cam) {
    var g = ctx.createLinearGradient(0, 0, 0, H);
    var hues = [['#120f2e', '#2a1550'], ['#0b1530', '#14324a'], ['#1a0f2a', '#3b1240'], ['#0c1a2a', '#16294f'], ['#12102a', '#123a36'], ['#1c0f30', '#401a4a']][s ? s.level : 0];
    g.addColorStop(0, '#0b0d17'); g.addColorStop(0.45, hues[0]); g.addColorStop(1, hues[1]);
    ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);
    stars.forEach(function (st) {
      var x = ((st.x - cam * st.z) % 1600 + 1600) % 1600; if (x > W) return;
      ctx.globalAlpha = 0.4 + 0.4 * Math.sin(clock * 2 + st.x); ctx.fillStyle = '#fff'; ctx.fillRect(x, st.y, st.s, st.s);
    });
    ctx.globalAlpha = 1;
    // moon
    ctx.fillStyle = 'rgba(244,114,182,0.15)'; ctx.beginPath(); ctx.arc(640 - cam * 0.05, 90, 46, 0, 7); ctx.fill();
    ctx.fillStyle = 'rgba(244,114,182,0.35)'; ctx.beginPath(); ctx.arc(640 - cam * 0.05, 90, 30, 0, 7); ctx.fill();
    // far ridges
    [[0.18, 290, 60, 'rgba(124,92,255,0.18)'], [0.4, 340, 40, 'rgba(34,211,238,0.10)']].forEach(function (lay) {
      ctx.fillStyle = lay[3]; ctx.beginPath(); ctx.moveTo(0, H);
      for (var x = 0; x <= W; x += 16) {
        var wx = x + cam * lay[0];
        ctx.lineTo(x, lay[1] - Math.abs(Math.sin(wx * 0.006)) * lay[2] - Math.sin(wx * 0.017) * lay[2] * 0.3);
      }
      ctx.lineTo(W, H); ctx.closePath(); ctx.fill();
    });
  }

  function drawTiles(cam) {
    var c0 = Math.max(0, Math.floor(cam / T)), c1 = Math.min(lv.w - 1, Math.ceil((cam + W) / T));
    for (var y = 0; y < ROWS; y++) for (var x = c0; x <= c1; x++) {
      var ch = lv.grid[y][x], px = x * T - cam, py = y * T;
      if (ch === '#') {
        ctx.fillStyle = (x + y) % 2 ? '#1a1f3a' : '#181c35'; ctx.fillRect(px, py, T, T);
        ctx.fillStyle = 'rgba(255,255,255,0.035)'; ctx.fillRect(px + 4, py + 4, 8, 8); ctx.fillRect(px + 17, py + 16, 6, 6);
        if (cell(x, y - 1) !== '#') {
          ctx.fillStyle = '#22d3ee'; ctx.shadowColor = '#22d3ee'; ctx.shadowBlur = 10; ctx.fillRect(px, py, T, 4); ctx.shadowBlur = 0;
          ctx.fillStyle = 'rgba(34,211,238,0.18)'; ctx.fillRect(px, py + 4, T, 4);
        }
        if (cell(x - 1, y) !== '#' && x > 0) { ctx.fillStyle = 'rgba(34,211,238,0.25)'; ctx.fillRect(px, py, 2, T); }
        if (cell(x + 1, y) !== '#' && x < lv.w - 1) { ctx.fillStyle = 'rgba(34,211,238,0.25)'; ctx.fillRect(px + T - 2, py, 2, T); }
      } else if (ch === '-') {
        ctx.fillStyle = '#c084fc'; ctx.shadowColor = '#c084fc'; ctx.shadowBlur = 8; ctx.fillRect(px, py, T, 6); ctx.shadowBlur = 0;
        ctx.fillStyle = 'rgba(192,132,252,0.25)'; ctx.fillRect(px + 3, py + 6, 3, 8); ctx.fillRect(px + T - 6, py + 6, 3, 8);
      } else if (ch === '^') {
        ctx.fillStyle = '#f472b6'; ctx.shadowColor = '#f472b6'; ctx.shadowBlur = 8;
        ctx.beginPath(); ctx.moveTo(px + 1, py + T); ctx.lineTo(px + 8, py + 12); ctx.lineTo(px + 15, py + T); ctx.lineTo(px + 22, py + 12); ctx.lineTo(px + 29, py + T); ctx.closePath(); ctx.fill();
        ctx.shadowBlur = 0;
      } else if (ch === 'o') {
        var sc = Math.abs(Math.cos(clock * 3 + x * 0.7));
        ctx.fillStyle = '#facc15'; ctx.shadowColor = '#fde047'; ctx.shadowBlur = 12;
        ctx.beginPath(); ctx.ellipse(px + 15, py + 15 + Math.sin(clock * 4 + x) * 2, 8 * sc + 1.5, 9, 0, 0, 7); ctx.fill();
        ctx.shadowBlur = 0; ctx.fillStyle = '#fff7c2'; ctx.fillRect(px + 14, py + 10, 2 * sc + 0.5, 6);
      } else if (ch === 'B') {
        var hit = p && p.padHit && p.padHit.x === x;
        ctx.fillStyle = '#1a1f3a'; ctx.fillRect(px, py + 12, T, 18);
        ctx.fillStyle = '#34d399'; ctx.shadowColor = '#34d399'; ctx.shadowBlur = 12;
        rr(px + 2, py + (hit ? 8 : 2), T - 4, 8, 3); ctx.fill(); ctx.shadowBlur = 0;
        ctx.strokeStyle = '#a7f3d0'; ctx.lineWidth = 2; ctx.beginPath();
        var top = py + (hit ? 16 : 10);
        ctx.moveTo(px + 8, top); ctx.lineTo(px + 22, top + 3); ctx.lineTo(px + 8, top + 6); ctx.lineTo(px + 22, top + 9); ctx.stroke();
      }
    }
  }

  function drawFlag(cam) {
    var fx = lv.flag.x * T - cam + 8, base = (lv.flag.y + 1) * T;
    ctx.fillStyle = '#eef0ff'; ctx.fillRect(fx, base - 90, 4, 90);
    ctx.fillStyle = '#f472b6'; ctx.shadowColor = '#f472b6'; ctx.shadowBlur = 16;
    ctx.beginPath(); ctx.moveTo(fx + 4, base - 88);
    for (var i = 0; i <= 10; i++) ctx.lineTo(fx + 4 + i * 4, base - 88 + Math.sin(clock * 6 + i * 0.6) * 3);
    for (var j = 10; j >= 0; j--) ctx.lineTo(fx + 4 + j * 4, base - 62 + Math.sin(clock * 6 + j * 0.6) * 3);
    ctx.closePath(); ctx.fill(); ctx.shadowBlur = 0;
    ctx.fillStyle = '#fde047'; ctx.beginPath(); ctx.arc(fx + 2, base - 92, 5, 0, 7); ctx.fill();
    ctx.globalAlpha = 0.25 + 0.15 * Math.sin(clock * 3); ctx.fillStyle = '#f472b6'; ctx.beginPath(); ctx.ellipse(fx + 2, base, 26, 6, 0, 0, 7); ctx.fill(); ctx.globalAlpha = 1;
  }

  function drawPlayer(cam) {
    if (s.dead > 0 && game.state !== 'menu') return;
    var x = p.x - cam + PW / 2, y = p.y + PH;
    var sx = 1 / Math.sqrt(p.squash), sy = p.squash;
    if (!p.ground) { sy = Math.max(0.85, Math.min(1.25, 1 - p.vy / 2600)); sx = 1 / sy; }
    ctx.save(); ctx.translate(x, y); ctx.scale(sx, sy);
    ctx.fillStyle = 'rgba(0,0,0,0.3)'; ctx.beginPath(); ctx.ellipse(0, 0, 12, 3, 0, 0, 7); ctx.fill();
    ctx.shadowColor = '#22d3ee'; ctx.shadowBlur = 14; ctx.fillStyle = '#22d3ee'; rr(-PW / 2, -PH, PW, PH, 6); ctx.fill(); ctx.shadowBlur = 0;
    ctx.fillStyle = '#7c5cff'; rr(-PW / 2, -PH, PW, 8, 5); ctx.fill();
    ctx.fillStyle = '#0b0d17'; rr(-7 + p.face * 3, -PH + 9, 14, 7, 3); ctx.fill();
    ctx.fillStyle = '#fff'; ctx.fillRect(-4 + p.face * 4, -PH + 11, 3, 3); ctx.fillRect(2 + p.face * 4, -PH + 11, 3, 3);
    var step = p.ground && Math.abs(p.vx) > 30 ? Math.sin(clock * 22) * 3 : 0;
    ctx.fillStyle = '#0e7490'; ctx.fillRect(-8, -3 + step * 0.3, 6, 3); ctx.fillRect(2, -3 - step * 0.3, 6, 3);
    ctx.restore();
  }

  function render(dt) {
    if (!lv) return;
    if (game.state === 'menu') { clock += dt; s.camX = (Math.sin(clock * 0.15) * 0.5 + 0.5) * (lv.pw - W); }
    var cam = Math.round(s.camX);
    drawBackground(cam);
    drawTiles(cam);
    lv.movers.forEach(function (m) {
      var mx = m.x - cam; if (mx > W || mx + m.w < 0) return;
      ctx.fillStyle = '#7c5cff'; ctx.shadowColor = '#7c5cff'; ctx.shadowBlur = 14; rr(mx, m.y, m.w, 12, 5); ctx.fill(); ctx.shadowBlur = 0;
      ctx.fillStyle = 'rgba(255,255,255,0.3)'; for (var i = 0; i < 4; i++) ctx.fillRect(mx + 10 + i * 20, m.y + 4, 10, 3);
      ctx.strokeStyle = 'rgba(124,92,255,0.25)'; ctx.setLineDash([4, 6]); ctx.beginPath();
      if (m.type === 'H') { ctx.moveTo(m.x0 - cam + m.w / 2, m.y + 6); ctx.lineTo(m.x0 + m.range - cam + m.w / 2, m.y + 6); }
      else { ctx.moveTo(mx + m.w / 2, m.y0 + 6); ctx.lineTo(mx + m.w / 2, m.y0 - m.range + 6); }
      ctx.stroke(); ctx.setLineDash([]);
    });
    drawFlag(cam);
    if (game.state !== 'menu') drawPlayer(cam);
    parts.forEach(function (q) { ctx.globalAlpha = Math.max(0, q.life / q.max); ctx.fillStyle = q.c; ctx.fillRect(q.x - cam - q.size / 2, q.y - q.size / 2, q.size, q.size); });
    ctx.globalAlpha = 1;
    if (game.state === 'menu') return;
    // banners
    ctx.textAlign = 'center';
    if (s.banner > 0) {
      ctx.globalAlpha = Math.min(1, s.banner * 2);
      ctx.fillStyle = 'rgba(11,13,23,0.6)'; rr(W / 2 - 170, 56, 340, 70, 16); ctx.fill();
      ctx.fillStyle = '#22d3ee'; ctx.font = '800 14px system-ui, sans-serif'; ctx.fillText(game.t('level') + ' ' + (s.level + 1) + ' / 6', W / 2, 82);
      ctx.fillStyle = '#fff'; ctx.font = '900 26px system-ui, sans-serif'; ctx.fillText(TXT.names[s.level], W / 2, 112);
      ctx.globalAlpha = 1;
    }
    if (s.clear > 0) {
      ctx.fillStyle = 'rgba(11,13,23,0.55)'; rr(W / 2 - 160, 150, 320, 90, 18); ctx.fill();
      ctx.fillStyle = '#f472b6'; ctx.font = '900 30px system-ui, sans-serif'; ctx.fillText(TXT.clear, W / 2, 192);
      ctx.fillStyle = '#fde047'; ctx.font = '800 18px system-ui, sans-serif'; ctx.fillText('+' + s.lastBonus, W / 2, 222);
    }
    // lives hearts
    for (var i = 0; i < 3; i++) {
      ctx.fillStyle = i < s.lives ? '#f472b6' : 'rgba(255,255,255,0.15)';
      var hx = 20 + i * 24, hy = 22;
      ctx.beginPath(); ctx.moveTo(hx, hy + 6); ctx.bezierCurveTo(hx - 10, hy - 2, hx - 4, hy - 10, hx, hy - 3); ctx.bezierCurveTo(hx + 4, hy - 10, hx + 10, hy - 2, hx, hy + 6); ctx.fill();
    }
  }

  // ---------------------------------------------------------------- input
  var JUMP_KEYS = { ' ': 1, Space: 1, ArrowUp: 1, w: 1, W: 1, z: 1, Z: 1 };
  game.on('keydown', function (e) { if (game.state === 'playing' && (JUMP_KEYS[e.key] || JUMP_KEYS[e.code])) p.buffer = 0.13; });
  game.touchButtons([{ key: 'ArrowLeft', label: '◀' }, { key: 'ArrowRight', label: '▶' }], 'left');
  game.touchButtons([{ key: 'ArrowUp', label: '▲' }], 'right');

  game.on('start', function () { parts = []; newGame(); });
  game.loop(update, render);

  newGame();
  game.showMenu();
  game.ready();
})();
