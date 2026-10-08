/* Pulse Reflex – original reaction-time test for Nebulo.
 * Five rounds: wait for the core to flash cyan, then tap as fast as possible.
 * Tapping early (or faster than humanly possible, < 100 ms) is a false start: +200 ms penalty and the round repeats.
 * Leaderboard score = max(0, 1000 − average ms), so higher is better; the card shows the milliseconds.
 */
(function () {
  'use strict';
  var L = { en: 0, ka: 1, tr: 2, ru: 3 }[NebuloGame.lang] || 0;
  var TXT = {
    title: ['Pulse Reflex', 'პულსის რეფლექსი', 'Nabız Refleksi', 'Пульс-рефлекс'][L],
    tagline: ['How fast are you really? Five flashes, one average.', 'რამდენად სწრაფი ხარ სინამდვილეში? ხუთი აფეთქება, ერთი საშუალო.', 'Gerçekte ne kadar hızlısın? Beş parlama, tek ortalama.', 'Насколько вы быстры на самом деле? Пять вспышек, одно среднее.'][L],
    how: [
      ['Wait while the core glows purple.', 'The moment it flashes cyan – tap anywhere, or press Space.', 'Tapping too early is a false start: +200 ms and the round repeats.', 'After 5 rounds you get your average reaction time.', 'Score = 1000 − average ms, so faster means more points.'],
      ['დაელოდე, სანამ ბირთვი იისფრად ანათებს.', 'როგორც კი ცისფრად აინთება – დააჭირე ნებისმიერ ადგილას ან Space-ს.', 'ნაადრევი დაჭერა ფალსტარტია: +200 მწ და რაუნდი მეორდება.', '5 რაუნდის შემდეგ მიიღებ რეაქციის საშუალო დროს.', 'ქულა = 1000 − საშუალო მწ, ანუ რაც უფრო სწრაფი ხარ, მით მეტი ქულა.'],
      ['Çekirdek mor parlarken bekle.', 'Camgöbeği yanıp söndüğü anda ekranın herhangi bir yerine dokun ya da Boşluk’a bas.', 'Erken dokunmak hatalı çıkıştır: +200 ms ve tur tekrarlanır.', '5 turun sonunda ortalama tepki süreni görürsün.', 'Puan = 1000 − ortalama ms; ne kadar hızlıysan o kadar çok puan.'],
      ['Ждите, пока ядро светится фиолетовым.', 'Как только оно вспыхнет голубым – нажмите в любом месте или Пробел.', 'Слишком раннее нажатие – фальстарт: +200 мс, и раунд повторяется.', 'После 5 раундов вы узнаете среднее время реакции.', 'Очки = 1000 − среднее время в мс: чем быстрее, тем больше очков.'],
    ][L],
    wait: ['Wait…', 'მოიცადე…', 'Bekle…', 'Ждите…'][L],
    tap: ['TAP!', 'ახლა!', 'BAS!', 'ЖМИ!'][L],
    early: ['Too soon!', 'ზედმეტად ადრე!', 'Çok erken!', 'Слишком рано!'][L],
    slow: ['Too slow', 'ძალიან ნელა', 'Çok yavaş', 'Слишком медленно'][L],
    penalty: ['+200 ms penalty – try again', '+200 მწ ჯარიმა – კიდევ სცადე', '+200 ms ceza – tekrar dene', 'Штраф +200 мс – ещё раз'][L],
    hint: ['Tap anywhere or press Space', 'დააჭირე ნებისმიერ ადგილას ან Space-ს', 'Herhangi bir yere dokun ya da Boşluk’a bas', 'Нажмите в любом месте или Пробел'][L],
    round: ['Round', 'რაუნდი', 'Tur', 'Раунд'][L],
    avg: ['Avg', 'საშ.', 'Ort.', 'Сред.'][L],
    result: ['Your average', 'შენი საშუალო', 'Ortalaman', 'Ваше среднее'][L],
    fastest: ['Fastest', 'საუკეთესო', 'En hızlı', 'Лучшее'][L],
    falseStarts: ['False starts', 'ფალსტარტები', 'Hatalı çıkış', 'Фальстарты'][L],
    points: ['Points', 'ქულა', 'Puan', 'Очки'][L],
    ranks: [['Lightning!', 'Sharp', 'Solid', 'Keep practising'], ['ელვა!', 'მახვილი', 'კარგი', 'ივარჯიშე კიდევ'], ['Şimşek gibi!', 'Keskin', 'İyi', 'Pratik yapmaya devam'], ['Молния!', 'Отлично', 'Неплохо', 'Нужна практика']][L],
  };

  var game = NebuloGame.create({ id: 'reflex-test', title: TXT.title, tagline: TXT.tagline, howTo: TXT.how });
  var W = 600, H = 720, ROUNDS = 5, PENALTY = 200, MIN_MS = 100, SLOW_MS = 1500, CX = 300, CY = 330;
  var view = game.canvas(W, H), ctx = view.ctx;
  var phase = 'idle', t = 0, delay = 0, goAt = null, times = [], penalty = 0, falseStarts = 0, lastMs = 0, lastSlow = false, rings = [], particles = [], shake = 0;

  function sum(a) { return a.reduce(function (s, v) { return s + v; }, 0); }
  function avgSoFar() { return times.length ? Math.round((sum(times) + penalty) / times.length) : 0; }
  function rank(ms) { return ms < 230 ? 0 : ms < 290 ? 1 : ms < 370 ? 2 : 3; }
  function syncStats() {
    var a = avgSoFar();
    game.setStat('score', times.length ? Math.max(0, 1000 - a) : 0);
    game.setStat('round', Math.min(ROUNDS, times.length + 1) + '/' + ROUNDS, TXT.round);
  }
  function startWait() { phase = 'wait'; t = 0; delay = 1.3 + game.rng() * 2.5; goAt = null; }
  function newGame() {
    times = []; penalty = 0; falseStarts = 0; rings = []; particles = []; shake = 0;
    syncStats(); startWait();
  }
  function falseStart() {
    falseStarts++; penalty += PENALTY; phase = 'early'; t = 0; shake = 1;
    game.audio.sfx('hit'); syncStats();
  }
  function record(ms, slow) {
    times.push(ms); lastMs = ms; lastSlow = slow; phase = 'result'; t = 0;
    if (slow) game.audio.sfx('lose');
    else { game.audio.tone(rank(ms) === 0 ? 1320 : 880, 0.12, 'square', 0.12, 1760); }
    for (var i = 0; i < 40; i++) {
      var a = Math.random() * Math.PI * 2, s = 120 + Math.random() * 320;
      particles.push({ x: CX, y: CY, vx: Math.cos(a) * s, vy: Math.sin(a) * s, life: 0.5 + Math.random() * 0.6, max: 1.1, r: 2 + Math.random() * 3 });
    }
    rings.push({ t: 0 });
    syncStats();
  }
  function finish() {
    var avg = Math.round((sum(times) + penalty) / ROUNDS);
    var score = Math.max(0, 1000 - avg);
    var fastest = Math.round(Math.min.apply(null, times));
    game.over({ score: score, win: true, title: TXT.ranks[rank(avg)],
      formatScore: function (v) { return v > 0 ? (1000 - v) + ' ms' : '≥ 1000 ms'; },
      lines: [TXT.points + ': ' + score + ' (1000 − ' + avg + ' ms)', TXT.fastest + ': ' + fastest + ' ms', TXT.falseStarts + ': ' + falseStarts + (falseStarts ? ' (+' + falseStarts * PENALTY + ' ms)' : '')],
      evidence: { times: times.map(Math.round), falseStarts: falseStarts } });
  }
  function tap() {
    if (game.state !== 'playing') return;
    game.audio.ensure();
    if (phase === 'wait') { falseStart(); return; }
    if (phase === 'go') {
      if (goAt === null) { falseStart(); return; }
      var ms = performance.now() - goAt;
      if (ms < MIN_MS) { falseStart(); return; }
      record(Math.round(ms), false);
    }
  }

  function update(dt) {
    t += dt;
    if (phase === 'wait' && t >= delay) { phase = 'go'; t = 0; goAt = null; }
    else if (phase === 'go' && goAt !== null && performance.now() - goAt > SLOW_MS) record(SLOW_MS, true);
    else if (phase === 'result' && t >= 1.1) { if (times.length >= ROUNDS) { phase = 'done'; finish(); } else startWait(); }
    else if (phase === 'early' && t >= 1.3) startWait();
  }
  // A pause during waiting/flash would invalidate the measurement – restart the round's countdown.
  game.on('resume', function () { if (phase === 'wait' || phase === 'go') startWait(); });

  // ---------------------------------------------------------------- render
  var FONT = ' system-ui, -apple-system, Segoe UI, Roboto, sans-serif';
  function text(s, x, y, size, color, weight, maxW) {
    ctx.font = (weight || 800) + ' ' + size + 'px' + FONT;
    if (maxW) { var w = ctx.measureText(s).width; if (w > maxW) ctx.font = (weight || 800) + ' ' + Math.floor(size * maxW / w) + 'px' + FONT; }
    ctx.fillStyle = color; ctx.fillText(s, x, y);
  }
  function render(dt) {
    var now = performance.now() / 1000, live = game.state !== 'paused';
    ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    var inGame = game.state === 'playing' || game.state === 'paused' || game.state === 'over';
    var ph = inGame ? phase : 'idle';
    var bgA = '#1b1033', bgB = '#0b0d17';
    if (ph === 'go') { bgA = '#0e7490'; bgB = '#06303d'; }
    else if (ph === 'early') { bgA = '#5b1220'; bgB = '#1a0710'; }
    var g = ctx.createRadialGradient(CX, CY, 20, CX, CY, 520); g.addColorStop(0, bgA); g.addColorStop(1, bgB);
    ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);

    if (live && shake > 0) shake = Math.max(0, shake - dt * 3);
    var sx = shake ? (Math.random() - 0.5) * 20 * shake : 0;

    // round markers
    for (var i = 0; i < ROUNDS; i++) {
      var mx = 140 + i * 80, done = i < times.length && inGame, cur = i === times.length && inGame && ph !== 'done';
      ctx.beginPath(); ctx.arc(mx, 52, 16, 0, Math.PI * 2);
      ctx.fillStyle = done ? (times[i] >= SLOW_MS ? '#f87171' : '#22d3ee') : 'rgba(255,255,255,.08)'; ctx.fill();
      if (cur) { ctx.strokeStyle = '#f472b6'; ctx.lineWidth = 3; ctx.stroke(); }
      text(done ? String(times[i]) : String(i + 1), mx, done ? 84 : 52, done ? 13 : 14, done ? '#b4b9d6' : '#eef0ff', 800);
    }

    if (inGame && times.length) text(TXT.avg + ' ' + avgSoFar() + ' ms', CX, 122, 18, '#f472b6', 800);

    // core
    var baseR = 150, rad = baseR, col = '#7c5cff', glow = 30;
    if (ph === 'wait' || ph === 'idle') { rad = baseR + Math.sin(now * 2.2) * 6; col = '#7c5cff'; }
    else if (ph === 'go') { rad = baseR + 14; col = '#22d3ee'; glow = 70; }
    else if (ph === 'early') { col = '#f43f5e'; glow = 40; }
    else if (ph === 'result' || ph === 'done') { col = lastSlow ? '#f87171' : '#22d3ee'; rad = baseR + Math.max(0, 14 - t * 40); glow = 40; }
    ctx.save(); ctx.shadowColor = col; ctx.shadowBlur = glow;
    var cg = ctx.createRadialGradient(CX - 40 + sx, CY - 50, 10, CX + sx, CY, rad);
    cg.addColorStop(0, ph === 'go' ? '#e0fbff' : ph === 'early' ? '#fecdd3' : (ph === 'result' || ph === 'done') ? (lastSlow ? '#fecaca' : '#cffafe') : '#c4b5fd'); cg.addColorStop(0.35, col); cg.addColorStop(1, 'rgba(11,13,23,.6)');
    ctx.fillStyle = cg; ctx.beginPath(); ctx.arc(CX + sx, CY, rad, 0, Math.PI * 2); ctx.fill(); ctx.restore();
    // orbit ring
    ctx.save(); ctx.strokeStyle = 'rgba(255,255,255,.18)'; ctx.lineWidth = 2; ctx.setLineDash([6, 12]);
    ctx.lineDashOffset = -now * 30; ctx.beginPath(); ctx.arc(CX, CY, baseR + 36, 0, Math.PI * 2); ctx.stroke(); ctx.restore();

    if (live) rings.forEach(function (r) { r.t += dt; });
    rings = rings.filter(function (r) { return r.t < 0.8; });
    rings.forEach(function (r) { ctx.strokeStyle = 'rgba(34,211,238,' + (1 - r.t / 0.8) + ')'; ctx.lineWidth = 6; ctx.beginPath(); ctx.arc(CX, CY, baseR + r.t * 260, 0, Math.PI * 2); ctx.stroke(); });

    // centre label
    if (ph === 'idle') text(TXT.wait, CX, CY, 46, '#fff', 900);
    else if (ph === 'wait') { text(TXT.wait, CX + sx, CY, 52, '#fff', 900, 260); }
    else if (ph === 'go') text(TXT.tap, CX, CY, 76, '#03222b', 900, 270);
    else if (ph === 'early') { text(TXT.early, CX + sx, CY - 14, 40, '#fff', 900, 270); text('+' + PENALTY + ' ms', CX + sx, CY + 34, 26, '#fecdd3', 800); }
    else if (ph === 'result' || ph === 'done') {
      text(lastSlow ? TXT.slow : lastMs + ' ms', CX, CY - 8, lastSlow ? 40 : 64, '#fff', 900, 270);
      if (!lastSlow) text(TXT.ranks[rank(lastMs)], CX, CY + 46, 22, '#cffafe', 800, 260);
    }

    // particles
    if (live) for (var p = particles.length - 1; p >= 0; p--) { var pt = particles[p]; pt.life -= dt; if (pt.life <= 0) { particles.splice(p, 1); continue; } pt.x += pt.vx * dt; pt.y += pt.vy * dt; pt.vx *= 0.96; pt.vy *= 0.96; }
    particles.forEach(function (o) { ctx.globalAlpha = Math.max(0, o.life / o.max); ctx.fillStyle = '#a5f3fc'; ctx.beginPath(); ctx.arc(o.x, o.y, o.r, 0, Math.PI * 2); ctx.fill(); });
    ctx.globalAlpha = 1;

    // bottom info
    text(ph === 'early' ? TXT.penalty : TXT.hint, CX, 552, 18, ph === 'early' ? '#fecdd3' : '#b4b9d6', 700, W - 40);
    // bar chart of reaction times
    var bx = 120, bw = 360, by = 600, bh = 90;
    ctx.fillStyle = 'rgba(255,255,255,.04)'; ctx.fillRect(bx - 10, by - 10, bw + 20, bh + 20);
    [200, 300, 400].forEach(function (ms) {
      var y = by + bh - (ms / 600) * bh; ctx.fillStyle = 'rgba(255,255,255,.08)'; ctx.fillRect(bx, y, bw, 1);
      ctx.textAlign = 'right'; text(String(ms), bx - 16, y, 11, 'rgba(255,255,255,.35)', 700); ctx.textAlign = 'center';
    });
    if (inGame) for (var j = 0; j < times.length; j++) {
      var hh = Math.min(1, times[j] / 600) * bh, x0 = bx + j * (bw / ROUNDS) + 14;
      var bgc = ctx.createLinearGradient(0, by + bh - hh, 0, by + bh); bgc.addColorStop(0, times[j] >= SLOW_MS ? '#f87171' : '#22d3ee'); bgc.addColorStop(1, '#7c5cff');
      ctx.fillStyle = bgc; ctx.fillRect(x0, by + bh - hh, bw / ROUNDS - 28, hh);
    }
  }

  // ---------------------------------------------------------------- input
  game.stage.addEventListener('pointerdown', function (e) {
    if (e.target.closest && e.target.closest('.ng-overlay, .ng-touch')) return;
    e.preventDefault(); tap();
  });
  game.on('keydown', function (e) {
    if (['Shift', 'Control', 'Alt', 'Meta', 'Tab', 'CapsLock'].indexOf(e.key) >= 0) return;
    tap();
  });

  game.on('start', newGame);
  game.loop(update, function (dt) { render(dt); if (game.state === 'playing' && phase === 'go' && goAt === null) goAt = performance.now(); });
  game.showMenu();
  game.ready();
})();
