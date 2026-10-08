/* Chroma Clash – original colour-word reflex game for Nebulo (Stroop-style).
 * A colour word appears in a different ink. Pick the INK colour from four swatches – or, when the
 * prompt flips to WORD, pick the colour the word names. Each question has a shrinking timer bar.
 * Wrong answers and timeouts cost a life; streaks raise a score multiplier.
 */
(function () {
  'use strict';
  var L = { en: 0, ka: 1, tr: 2, ru: 3 }[NebuloGame.lang] || 0;
  var TXT = {
    title: ['Chroma Clash', 'ფერების შეჯახება', 'Renk Çatışması', 'Цветовой конфликт'][L],
    tagline: ['Your eyes say one colour, the word says another. Trust the right one – fast.', 'თვალი ერთ ფერს ხედავს, სიტყვა სხვას ამბობს. სწრაფად ენდე სწორს.', 'Gözlerin bir renk görüyor, kelime başka bir renk söylüyor. Doğru olana güven – hızla.', 'Глаза видят один цвет, слово называет другой. Доверьтесь правильному – и быстро.'][L],
    how: [
      ['A colour word appears, painted in a different ink.', 'INK prompt: tap the swatch matching the ink colour.', 'WORD prompt (from level 3): tap the colour the word names.', 'Keys 1–4 (or Q W / A S) pick the swatches – top row, then bottom row.', 'Wrong answer or timeout = −1 life. 3 lives. Streaks raise your multiplier.'],
      ['ჩნდება ფერის სახელი, რომელიც სხვა ფერითაა დაწერილი.', 'მოთხოვნა „მელანი“: აირჩიე ასოების ფერის შესაბამისი ნიმუში.', 'მოთხოვნა „სიტყვა“ (მე-3 დონიდან): აირჩიე ის ფერი, რომელსაც სიტყვა ასახელებს.', '1–4 ღილაკები (ან Q W / A S) ირჩევს ნიმუშს – ჯერ ზედა, მერე ქვედა რიგი.', 'არასწორი პასუხი ან დროის ამოწურვა = −1 სიცოცხლე. სულ 3. სერიები მამრავლს ზრდის.'],
      ['Başka bir mürekkeple yazılmış bir renk adı belirir.', 'MÜREKKEP komutu: mürekkep rengine uyan kutuya dokun.', 'KELİME komutu (3. seviyeden itibaren): kelimenin söylediği renge dokun.', '1–4 tuşları (veya Q W / A S) kutuları seçer – önce üst sıra, sonra alt sıra.', 'Yanlış cevap veya süre bitimi = −1 can. 3 can var. Seriler çarpanını artırır.'],
      ['Появляется название цвета, написанное другим цветом.', 'Подсказка ЦВЕТ: выберите образец, совпадающий с цветом букв.', 'Подсказка СЛОВО (с 3-го уровня): выберите цвет, который назван словом.', 'Клавиши 1–4 (или Q W / A S) выбирают образцы – верхний ряд, затем нижний.', 'Ошибка или истёкшее время = −1 жизнь. Всего 3. Серии повышают множитель.'],
    ][L],
    names: [['RED', 'BLUE', 'GREEN', 'YELLOW', 'PURPLE', 'ORANGE'], ['წითელი', 'ლურჯი', 'მწვანე', 'ყვითელი', 'იისფერი', 'ნარინჯისფერი'],
      ['KIRMIZI', 'MAVİ', 'YEŞİL', 'SARI', 'MOR', 'TURUNCU'], ['КРАСНЫЙ', 'СИНИЙ', 'ЗЕЛЁНЫЙ', 'ЖЁЛТЫЙ', 'ФИОЛЕТОВЫЙ', 'ОРАНЖЕВЫЙ']][L],
    inkPrompt: ['Tap the INK colour', 'აირჩიე მელნის ფერი', 'MÜREKKEP rengine dokun', 'Выберите ЦВЕТ букв'][L],
    wordPrompt: ['Tap what the WORD says', 'აირჩიე, რას ამბობს სიტყვა', 'KELİMENİN söylediğine dokun', 'Выберите цвет из СЛОВА'][L],
    ruleFlip: ['Rule flip!', 'წესი შეიცვალა!', 'Kural değişti!', 'Смена правила!'][L],
    level: ['Level', 'დონე', 'Seviye', 'Уровень'][L],
    streak: ['Streak', 'სერია', 'Seri', 'Серия'][L],
    timeout: ['Too slow!', 'დაგაგვიანდა!', 'Çok yavaş!', 'Не успели!'][L],
    answered: ['Correct answers', 'სწორი პასუხები', 'Doğru cevap', 'Верных ответов'][L],
    bestStreak: ['Best streak', 'საუკეთესო სერია', 'En uzun seri', 'Лучшая серия'][L],
    outOfLives: ['Out of lives', 'სიცოცხლე ამოიწურა', 'Canın kalmadı', 'Жизни закончились'][L],
  };

  var game = NebuloGame.create({ id: 'color-rush', title: TXT.title, tagline: TXT.tagline, howTo: TXT.how });
  var W = 520, H = 780, LIVES = 3;
  var view = game.canvas(W, H), ctx = view.ctx;
  var COLORS = ['#ef4444', '#3b82f6', '#22c55e', '#facc15', '#a855f7', '#f97316'];
  var DARK = ['#7f1d1d', '#1e3a8a', '#14532d', '#854d0e', '#581c87', '#7c2d12'];
  var BTN = [{ x: 30, y: 440, w: 220, h: 140 }, { x: 270, y: 440, w: 220, h: 140 }, { x: 30, y: 600, w: 220, h: 140 }, { x: 270, y: 600, w: 220, h: 140 }];

  var q, score, lives, level, correct, streak, bestStreak, phase, phaseT, tLeft, tMax, lastRule, picked, particles = [], floaters = [], shake = 0, pop = 0, flipT = 0, hoverBtn = -1, pressBtn = -1, pressT = 0;

  function ri(n) { return Math.floor(game.rng() * n); }
  function mult() { return Math.min(4, 1 + Math.floor(streak / 5)); }
  function syncStats() {
    game.setStat('score', score);
    game.setStat('lives', lives);
    game.setStat('level', level);
  }
  function nextQ() {
    level = 1 + Math.floor(correct / 6);
    var word = ri(6), ink;
    if (game.rng() < 0.15) ink = word; else { ink = ri(5); if (ink >= word) ink++; }
    var rule = level >= 3 && game.rng() < Math.min(0.4, 0.15 + level * 0.03) ? 'word' : 'ink';
    var opts = [ink]; if (word !== ink) opts.push(word);
    while (opts.length < 4) { var c = ri(6); if (opts.indexOf(c) < 0) opts.push(c); }
    for (var i = opts.length - 1; i > 0; i--) { var j = ri(i + 1); var tmp = opts[i]; opts[i] = opts[j]; opts[j] = tmp; }
    if (q && rule !== lastRule && rule === 'word') flipT = 1;
    lastRule = rule;
    q = { word: word, ink: ink, rule: rule, opts: opts, answer: rule === 'ink' ? ink : word, tilt: level >= 4 ? (game.rng() - 0.5) * 0.25 : 0 };
    tMax = Math.max(1.25, 3.2 - (level - 1) * 0.22) + (rule === 'word' ? 0.4 : 0);
    tLeft = tMax; phase = 'ask'; phaseT = 0; picked = -1; pop = 1;
  }
  function newGame() {
    score = 0; lives = LIVES; correct = 0; streak = 0; bestStreak = 0; q = null; lastRule = 'ink';
    particles = []; floaters = []; shake = 0; flipT = 0;
    nextQ(); syncStats();
  }
  function burst(x, y, color, n) {
    for (var i = 0; i < n; i++) {
      var a = Math.random() * Math.PI * 2, s = 80 + Math.random() * 280;
      particles.push({ x: x, y: y, vx: Math.cos(a) * s, vy: Math.sin(a) * s, life: 0.5 + Math.random() * 0.6, max: 1.1, c: color, r: 2 + Math.random() * 4 });
    }
  }
  function choose(slot) {
    if (game.state !== 'playing' || phase !== 'ask') return;
    pressBtn = slot; pressT = 0.15;
    var c = q.opts[slot]; picked = slot;
    if (c === q.answer) {
      streak++; bestStreak = Math.max(bestStreak, streak); correct++;
      var frac = tLeft / tMax, m = mult(), pts = (10 + Math.round(10 * frac)) * m;
      score += pts;
      var b = BTN[slot];
      burst(b.x + b.w / 2, b.y + b.h / 2, COLORS[c], 22 + m * 6);
      floaters.push({ text: '+' + pts + (m > 1 ? ' ×' + m : ''), x: W / 2, y: 392, t: 0, c: m > 1 ? '#f472b6' : '#22d3ee' });
      if (streak % 5 === 0 && m < 4) game.audio.sfx('bonus'); else game.audio.tone(600 + Math.min(streak, 20) * 30, 0.08, 'square', 0.1, 1200 + Math.min(streak, 20) * 40);
      var prevLevel = level;
      syncStats(); nextQ();
      if (level > prevLevel) floaters.push({ text: TXT.level + ' ' + level, x: W / 2, y: 120, t: 0, c: '#fde047' });
      syncStats();
    } else miss(false);
  }
  function miss(timeout) {
    lives--; streak = 0; shake = 1; phase = 'reveal'; phaseT = 0;
    game.audio.sfx('hit');
    if (timeout) floaters.push({ text: TXT.timeout, x: W / 2, y: 392, t: 0, c: '#f87171' });
    syncStats();
  }
  function finish() {
    game.over({ score: score, win: false, title: TXT.outOfLives,
      lines: [TXT.answered + ': ' + correct, TXT.bestStreak + ': ' + bestStreak, TXT.level + ': ' + level],
      evidence: { correct: correct, level: level } });
  }

  function update(dt) {
    if (phase === 'ask') {
      tLeft -= dt;
      if (tLeft <= 0) { tLeft = 0; miss(true); }
    } else if (phase === 'reveal') {
      phaseT += dt;
      if (phaseT >= 0.85) { if (lives <= 0) { phase = 'done'; finish(); } else nextQ(); }
    }
  }

  // ---------------------------------------------------------------- render
  var FONT = ' system-ui, -apple-system, Segoe UI, Roboto, sans-serif';
  function rr(x, y, w, h, r) {
    ctx.beginPath(); ctx.moveTo(x + r, y); ctx.arcTo(x + w, y, x + w, y + h, r); ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r); ctx.arcTo(x, y, x + w, y, r); ctx.closePath();
  }
  function text(s, x, y, size, color, weight, maxW) {
    ctx.font = (weight || 800) + ' ' + size + 'px' + FONT;
    if (maxW) { var w = ctx.measureText(s).width; if (w > maxW) ctx.font = (weight || 800) + ' ' + Math.floor(size * maxW / w) + 'px' + FONT; }
    ctx.fillStyle = color; ctx.fillText(s, x, y);
  }
  function heart(x, y, s, on) {
    ctx.save(); ctx.translate(x, y); ctx.scale(s, s);
    ctx.beginPath(); ctx.moveTo(0, 6); ctx.bezierCurveTo(-14, -4, -8, -16, 0, -8); ctx.bezierCurveTo(8, -16, 14, -4, 0, 6); ctx.closePath();
    ctx.fillStyle = on ? '#f472b6' : 'rgba(255,255,255,.1)'; if (on) { ctx.shadowColor = '#f472b6'; ctx.shadowBlur = 12; } ctx.fill(); ctx.restore();
  }
  function render(dt) {
    var now = performance.now() / 1000, live = game.state !== 'paused', active = q && game.state !== 'menu';
    ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    var g = ctx.createLinearGradient(0, 0, W, H); g.addColorStop(0, '#171236'); g.addColorStop(1, '#0b0d17');
    ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);
    // soft colour blobs
    for (var b = 0; b < 6; b++) {
      var bx = W / 2 + Math.cos(now * 0.3 + b) * 220, by = 300 + Math.sin(now * 0.4 + b * 1.7) * 260;
      var bg = ctx.createRadialGradient(bx, by, 0, bx, by, 160); bg.addColorStop(0, COLORS[b] + '22'); bg.addColorStop(1, COLORS[b] + '00');
      ctx.fillStyle = bg; ctx.fillRect(bx - 160, by - 160, 320, 320);
    }
    if (live) {
      if (shake > 0) shake = Math.max(0, shake - dt * 3);
      if (pop > 0) pop = Math.max(0, pop - dt * 5);
      if (flipT > 0) flipT = Math.max(0, flipT - dt * 0.9);
      if (pressT > 0) pressT -= dt;
    }
    var sx = shake ? (Math.random() - 0.5) * 22 * shake : 0;

    // lives + streak
    for (var h = 0; h < LIVES; h++) heart(48 + h * 40, 46, 1.4, active ? h < lives : true);
    rr(330, 24, 160, 44, 14); ctx.fillStyle = 'rgba(255,255,255,.06)'; ctx.fill();
    text(TXT.streak + ' ' + (active ? streak : 0) + '  ×' + (active ? mult() : 1), 410, 47, 17, active && mult() > 1 ? '#f472b6' : '#b4b9d6', 800, 148);

    // prompt pill
    var rule = active ? q.rule : 'ink';
    var pc = rule === 'ink' ? '#22d3ee' : '#f472b6';
    rr(70, 150, 380, 52, 26); ctx.fillStyle = rule === 'ink' ? 'rgba(34,211,238,.12)' : 'rgba(244,114,182,.16)'; ctx.fill();
    ctx.strokeStyle = pc; ctx.lineWidth = 2; ctx.stroke();
    text(rule === 'ink' ? TXT.inkPrompt : TXT.wordPrompt, W / 2, 177, 22, pc, 900, 350);
    if (flipT > 0) { ctx.globalAlpha = Math.min(1, flipT * 2); text(TXT.ruleFlip, W / 2, 120, 28, '#f472b6', 900); ctx.globalAlpha = 1; }

    // the word
    if (active) {
      ctx.save(); ctx.translate(W / 2 + sx, 290); ctx.rotate(q.tilt + Math.sin(now * 3) * (level >= 6 ? 0.04 : 0)); ctx.scale(1 + pop * 0.15, 1 + pop * 0.15);
      ctx.shadowColor = COLORS[q.ink]; ctx.shadowBlur = 24;
      text(TXT.names[q.word], 0, 0, 78, COLORS[q.ink], 900, 450);
      ctx.restore();
      // timer bar
      var frac = phase === 'ask' ? tLeft / tMax : 0;
      rr(60, 372, 400, 12, 6); ctx.fillStyle = 'rgba(255,255,255,.08)'; ctx.fill();
      if (frac > 0) { rr(60 + 200 * (1 - frac), 372, Math.max(12, 400 * frac), 12, 6); ctx.fillStyle = frac < 0.3 ? '#f87171' : pc; ctx.fill(); }
    } else {
      ctx.save(); ctx.shadowColor = COLORS[2]; ctx.shadowBlur = 24; text(TXT.names[0], W / 2, 290, 78, COLORS[2], 900, 450); ctx.restore();
    }

    // swatches
    for (var i = 0; i < 4; i++) {
      var r = BTN[i], c = active ? q.opts[i] : i, pressed = pressBtn === i && pressT > 0 ? 4 : 0;
      ctx.save(); ctx.translate(0, pressed);
      rr(r.x, r.y, r.w, r.h, 22);
      var sg = ctx.createLinearGradient(r.x, r.y, r.x, r.y + r.h); sg.addColorStop(0, COLORS[c]); sg.addColorStop(1, DARK[c]);
      ctx.fillStyle = sg; ctx.shadowColor = COLORS[c]; ctx.shadowBlur = hoverBtn === i ? 26 : 10; ctx.fill(); ctx.shadowBlur = 0;
      var stroke = 'rgba(255,255,255,.18)', lw = 2;
      if (active && phase === 'reveal') {
        if (c === q.answer) { stroke = '#fff'; lw = 5 + Math.sin(now * 14) * 2; }
        else if (i === picked) { stroke = '#111'; lw = 4; }
      }
      ctx.strokeStyle = stroke; ctx.lineWidth = lw; ctx.stroke();
      if (active && phase === 'reveal' && i === picked && c !== q.answer) {
        ctx.strokeStyle = 'rgba(0,0,0,.6)'; ctx.lineWidth = 8; ctx.lineCap = 'round';
        ctx.beginPath(); ctx.moveTo(r.x + 80, r.y + 40); ctx.lineTo(r.x + r.w - 80, r.y + r.h - 40); ctx.moveTo(r.x + r.w - 80, r.y + 40); ctx.lineTo(r.x + 80, r.y + r.h - 40); ctx.stroke();
      }
      rr(r.x + 12, r.y + 12, 30, 30, 9); ctx.fillStyle = 'rgba(0,0,0,.28)'; ctx.fill();
      text(String(i + 1), r.x + 27, r.y + 28, 16, 'rgba(255,255,255,.85)', 800);
      ctx.restore();
    }

    if (live) {
      for (var p = particles.length - 1; p >= 0; p--) { var pt = particles[p]; pt.life -= dt; if (pt.life <= 0) { particles.splice(p, 1); continue; } pt.x += pt.vx * dt; pt.y += pt.vy * dt; pt.vx *= 0.95; pt.vy = pt.vy * 0.95 + 200 * dt; }
      for (var f = floaters.length - 1; f >= 0; f--) { floaters[f].t += dt; if (floaters[f].t > 0.9) floaters.splice(f, 1); }
    }
    particles.forEach(function (o) { ctx.globalAlpha = Math.max(0, o.life / o.max); ctx.fillStyle = o.c; ctx.beginPath(); ctx.arc(o.x, o.y, o.r, 0, Math.PI * 2); ctx.fill(); });
    floaters.forEach(function (o) { ctx.globalAlpha = Math.max(0, 1 - o.t / 0.9); text(o.text, o.x, o.y - o.t * 40, 26, o.c, 900); });
    ctx.globalAlpha = 1;
  }

  // ---------------------------------------------------------------- input
  function btnAt(p) { for (var i = 0; i < 4; i++) { var r = BTN[i]; if (p.x >= r.x && p.x <= r.x + r.w && p.y >= r.y && p.y <= r.y + r.h) return i; } return -1; }
  view.canvas.addEventListener('pointerdown', function (e) {
    e.preventDefault(); game.audio.ensure();
    var i = btnAt(view.toLogical(e.clientX, e.clientY)); if (i >= 0) choose(i);
  });
  view.canvas.addEventListener('pointermove', function (e) { if (e.pointerType === 'mouse') hoverBtn = btnAt(view.toLogical(e.clientX, e.clientY)); });
  view.canvas.addEventListener('pointerleave', function () { hoverBtn = -1; });
  var KEYMAP = { '1': 0, '2': 1, '3': 2, '4': 3, q: 0, w: 1, a: 2, s: 3, Q: 0, W: 1, A: 2, S: 3 };
  game.on('keydown', function (e) { if (KEYMAP[e.key] !== undefined) choose(KEYMAP[e.key]); });

  game.on('start', newGame);
  game.loop(update, render);
  game.showMenu();
  game.ready();
})();
