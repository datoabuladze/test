/* Math Sprint – original 60-second mental arithmetic race for Nebulo.
 * Questions get harder as you answer correctly; streaks build a combo multiplier (up to ×5).
 * Wrong answers cost 3 seconds and reset the combo. Answers are entered with the on-screen pad
 * or the keyboard and are checked automatically once enough digits are typed.
 */
(function () {
  'use strict';
  var L = { en: 0, ka: 1, tr: 2, ru: 3 }[NebuloGame.lang] || 0;
  var TXT = {
    title: ['Math Sprint', 'მათემატიკური სპრინტი', 'Matematik Sprinti', 'Математический спринт'][L],
    tagline: ['60 seconds. As many sums as your brain can handle.', '60 წამი. იმდენი მაგალითი, რამდენსაც შენი ტვინი გაუძლებს.', '60 saniye. Beyninin kaldırabildiği kadar işlem.', '60 секунд. Столько примеров, сколько выдержит ваш мозг.'][L],
    how: [
      ['Solve each problem before the 60-second clock runs out.', 'Type with the number keys or tap the on-screen pad – the answer is checked automatically.', 'Backspace / ⌫ deletes, Enter / OK submits early.', 'Every 3 correct in a row raise the combo multiplier (up to ×5).', 'A wrong answer costs 3 seconds and resets the combo. Problems get harder as you go.'],
      ['ამოხსენი მაგალითები, სანამ 60-წამიანი საათი არ ამოიწურება.', 'აკრიფე ციფრებით ან ეკრანულ კლავიატურაზე – პასუხი ავტომატურად მოწმდება.', 'Backspace / ⌫ შლის, Enter / OK ადრე აგზავნის.', 'ზედიზედ ყოველი 3 სწორი პასუხი კომბო-მამრავლს ზრდის (×5-მდე).', 'არასწორი პასუხი 3 წამი ჯდება და კომბოს ანულებს. მაგალითები თანდათან რთულდება.'],
      ['60 saniyelik süre bitmeden her işlemi çöz.', 'Rakam tuşlarıyla yaz ya da ekrandaki tuş takımına dokun – cevap otomatik kontrol edilir.', 'Backspace / ⌫ siler, Enter / OK erken gönderir.', 'Art arda her 3 doğru cevap kombo çarpanını artırır (en fazla ×5).', 'Yanlış cevap 3 saniyeye mal olur ve komboyu sıfırlar. İşlemler giderek zorlaşır.'],
      ['Решайте примеры, пока не истекли 60 секунд.', 'Вводите цифры с клавиатуры или на экранной панели – ответ проверяется автоматически.', 'Backspace / ⌫ стирает, Enter / OK отправляет досрочно.', 'Каждые 3 верных ответа подряд повышают множитель комбо (до ×5).', 'Ошибка стоит 3 секунды и сбрасывает комбо. Примеры постепенно усложняются.'],
    ][L],
    timeUp: ["Time's up!", 'დრო ამოიწურა!', 'Süre doldu!', 'Время вышло!'][L],
    correct: ['Correct answers', 'სწორი პასუხები', 'Doğru cevap', 'Верных ответов'][L],
    accuracy: ['Accuracy', 'სიზუსტე', 'İsabet', 'Точность'][L],
    bestCombo: ['Best streak', 'საუკეთესო სერია', 'En uzun seri', 'Лучшая серия'][L],
    level: ['Level', 'დონე', 'Seviye', 'Уровень'][L],
    combo: ['Combo', 'კომბო', 'Kombo', 'Комбо'][L],
    streak: ['Streak', 'სერია', 'Seri', 'Серия'][L],
    go: ['Go!', 'დაიწყე!', 'Başla!', 'Вперёд!'][L],
  };

  var game = NebuloGame.create({ id: 'math-sprint', title: TXT.title, tagline: TXT.tagline, howTo: TXT.how });
  var W = 480, H = 780, ROUND = 60, PENALTY = 3;
  var view = game.canvas(W, H), ctx = view.ctx;
  var KEYS = ['1', '2', '3', '4', '5', '6', '7', '8', '9', 'del', '0', 'ok'];
  var PAD_X = 30, PAD_Y = 392, KW = 132, KH = 82, GAP = 12;

  var q, input, timeLeft, score, level, correct, wrong, streak, bestStreak, phase, phaseT, floaters = [], particles = [], shake = 0, flashOk = 0, flashBad = 0, pressed = {}, lastTick = 0, lastText = '';

  function ri(a, b) { return a + Math.floor(game.rng() * (b - a + 1)); }
  function pickOp(list) { return list[Math.floor(game.rng() * list.length)]; }
  function makeQ(lvl) {
    var a, b, c, op, t, ans;
    var ops = lvl <= 1 ? ['+'] : lvl === 2 ? ['+', '-'] : lvl === 3 ? ['+', '-', '×'] : lvl <= 5 ? ['+', '-', '×', '÷'] : ['+', '-', '×', '÷', 'mix'];
    op = pickOp(ops);
    var big = lvl >= 8 ? 2 : lvl >= 6 ? 1.5 : 1;
    if (op === '+') {
      if (lvl <= 1) { a = ri(1, 9); b = ri(1, 9); }
      else if (lvl <= 3) { a = ri(5, 40); b = ri(2, 19); }
      else { a = ri(12, Math.round(80 * big)); b = ri(11, Math.round(60 * big)); }
      t = a + ' + ' + b; ans = a + b;
    } else if (op === '-') {
      if (lvl <= 2) { a = ri(5, 20); b = ri(1, a); }
      else if (lvl <= 4) { a = ri(20, 70); b = ri(3, a - 5); }
      else { a = ri(40, Math.round(120 * big)); b = ri(11, a - 6); }
      t = a + ' − ' + b; ans = a - b;
    } else if (op === '×') {
      if (lvl <= 3) { a = ri(2, 5); b = ri(2, 9); }
      else if (lvl <= 5) { a = ri(3, 9); b = ri(3, 9); }
      else { a = ri(11, Math.round(18 * big)); b = ri(3, 9); }
      if (game.rng() < 0.5) { c = a; a = b; b = c; }
      t = a + ' × ' + b; ans = a * b;
    } else if (op === '÷') {
      b = ri(2, lvl <= 5 ? 9 : 12); ans = ri(2, lvl <= 5 ? 9 : Math.round(12 * big));
      a = b * ans; t = a + ' ÷ ' + b;
    } else {
      var kind = ri(0, 2);
      if (kind === 0) { a = ri(2, 9); b = ri(2, 9); c = ri(1, 20 * big); t = a + ' × ' + b + ' + ' + c; ans = a * b + c; }
      else if (kind === 1) { a = ri(10, 50 * big); b = ri(5, 40); c = ri(1, a + b - 1); t = a + ' + ' + b + ' − ' + c; ans = a + b - c; }
      else { a = ri(3, 9); b = ri(2, 9); c = ri(1, a * b - 1); t = a + ' × ' + b + ' − ' + c; ans = a * b - c; }
    }
    return { text: t, ans: ans };
  }
  function nextQ() {
    var n, tries = 0;
    do { n = makeQ(level); tries++; } while (n.text === lastText && tries < 10);
    lastText = n.text; q = n; input = ''; phase = 'ask'; phaseT = 0;
  }
  function mult() { return Math.min(5, 1 + Math.floor(streak / 3)); }
  function syncStats() {
    game.setStat('score', score);
    game.setStat('time', Math.max(0, Math.ceil(timeLeft)));
    game.setStat('level', level);
  }

  function newRound() {
    score = 0; level = 1; correct = 0; wrong = 0; streak = 0; bestStreak = 0; timeLeft = ROUND; lastTick = 0;
    floaters = []; particles = []; shake = 0; flashOk = 0; flashBad = 0; lastText = '';
    nextQ(); syncStats();
    floaters.push({ text: TXT.go, x: W / 2, y: 250, t: 0, c: '#22d3ee', size: 54 });
  }
  function burst(x, y, color, n) {
    for (var i = 0; i < n; i++) {
      var a = Math.random() * Math.PI * 2, s = 80 + Math.random() * 240;
      particles.push({ x: x, y: y, vx: Math.cos(a) * s, vy: Math.sin(a) * s, life: 0.5 + Math.random() * 0.5, max: 1, c: color, r: 2 + Math.random() * 3 });
    }
  }
  function submit() {
    if (phase !== 'ask' || !input.length) return;
    if (Number(input) === q.ans) {
      streak++; bestStreak = Math.max(bestStreak, streak); correct++;
      var m = mult(), pts = 10 * level * m;
      score += pts;
      floaters.push({ text: '+' + pts + (m > 1 ? '  ×' + m : ''), x: W / 2, y: 300, t: 0, c: m > 1 ? '#f472b6' : '#22d3ee', size: 30 });
      burst(W / 2, 315, m >= 3 ? '#f472b6' : '#22d3ee', 18 + m * 6);
      flashOk = 1;
      if (streak % 3 === 0 && m < 5) { game.audio.sfx('bonus'); } else game.audio.sfx('point');
      var newLevel = Math.min(10, 1 + Math.floor(correct / 5));
      if (newLevel > level) { level = newLevel; floaters.push({ text: TXT.level + ' ' + level, x: W / 2, y: 140, t: 0, c: '#fde047', size: 32 }); }
      syncStats(); nextQ();
    } else {
      wrong++; streak = 0; timeLeft = Math.max(0, timeLeft - PENALTY);
      flashBad = 1; shake = 1; game.audio.sfx('hit');
      floaters.push({ text: '−' + PENALTY + 's', x: 400, y: 36, t: 0, c: '#f87171', size: 26 });
      phase = 'reveal'; phaseT = 0;
      syncStats();
    }
  }
  function typeKey(k) {
    if (game.state !== 'playing' || phase !== 'ask') return;
    pressed[k] = 0.15;
    if (k === 'del') { input = input.slice(0, -1); game.audio.sfx('move'); return; }
    if (k === 'ok') { submit(); return; }
    if (input.length >= 5) return;
    if (input === '0') input = '';
    input += k; game.audio.sfx('tick');
    if (input.length >= String(q.ans).length) submit();
  }
  function finish() {
    var total = correct + wrong;
    game.over({ score: score, win: score > 0, title: TXT.timeUp,
      lines: [TXT.correct + ': ' + correct, TXT.accuracy + ': ' + (total ? Math.round(correct * 100 / total) : 0) + '%', TXT.bestCombo + ': ' + bestStreak, TXT.level + ': ' + level],
      evidence: { correct: correct, wrong: wrong, level: level } });
  }

  function update(dt) {
    timeLeft -= dt;
    if (timeLeft <= 10 && Math.ceil(timeLeft) !== lastTick && timeLeft > 0) { lastTick = Math.ceil(timeLeft); game.audio.sfx('tick'); }
    game.setStat('time', Math.max(0, Math.ceil(timeLeft)));
    if (phase === 'reveal') { phaseT += dt; if (phaseT >= 0.7) nextQ(); }
    if (timeLeft <= 0) { timeLeft = 0; finish(); }
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
  function keyRect(i) { var c = i % 3, r = Math.floor(i / 3); return { x: PAD_X + c * (KW + GAP), y: PAD_Y + r * (KH + GAP), w: KW, h: KH }; }
  function render(dt) {
    var now = performance.now() / 1000, live = game.state !== 'paused';
    ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    var g = ctx.createLinearGradient(0, 0, 0, H); g.addColorStop(0, '#13163a'); g.addColorStop(1, '#0b0d17');
    ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);
    // drifting background digits
    ctx.save(); ctx.globalAlpha = 0.05; ctx.fillStyle = '#7c5cff'; ctx.font = '900 54px' + FONT;
    for (var d = 0; d < 9; d++) { var yy = ((now * 14 + d * 97) % (H + 80)) - 40; ctx.fillText(String((d * 7) % 10), 40 + (d * 53) % 400, H - yy); }
    ctx.restore();

    if (live) {
      if (flashOk > 0) flashOk = Math.max(0, flashOk - dt * 3);
      if (flashBad > 0) flashBad = Math.max(0, flashBad - dt * 2.5);
      if (shake > 0) shake = Math.max(0, shake - dt * 3);
      for (var k in pressed) { pressed[k] -= dt; if (pressed[k] <= 0) delete pressed[k]; }
    }
    var playing = q && game.state !== 'menu';

    // timer bar
    var tl = playing ? Math.max(0, timeLeft) / ROUND : 1;
    rr(30, 22, 420, 16, 8); ctx.fillStyle = 'rgba(255,255,255,.08)'; ctx.fill();
    if (tl > 0) {
      rr(30, 22, Math.max(16, 420 * tl), 16, 8);
      var tg = ctx.createLinearGradient(30, 0, 450, 0);
      if (tl < 0.17) { tg.addColorStop(0, '#f87171'); tg.addColorStop(1, '#f472b6'); } else { tg.addColorStop(0, '#22d3ee'); tg.addColorStop(1, '#7c5cff'); }
      ctx.fillStyle = tg; ctx.fill();
    }
    text((playing ? Math.ceil(Math.max(0, timeLeft)) : ROUND) + 's', W / 2, 58, 18, tl < 0.17 ? '#f87171' : '#b4b9d6', 800);

    // chips
    var chips = [[TXT.level, playing ? level : 1, '#fde047'], [TXT.combo, '×' + (playing ? mult() : 1), '#f472b6'], [TXT.streak, playing ? streak : 0, '#22d3ee']];
    for (var ci = 0; ci < 3; ci++) {
      var cx = 30 + ci * 144; rr(cx, 80, 132, 56, 14); ctx.fillStyle = 'rgba(255,255,255,.05)'; ctx.fill();
      if (ci === 1 && playing && mult() > 1) { ctx.strokeStyle = 'rgba(244,114,182,' + (0.5 + 0.4 * Math.sin(now * 8)) + ')'; ctx.lineWidth = 2; ctx.stroke(); }
      text(chips[ci][0], cx + 66, 97, 13, '#b4b9d6', 700, 120);
      text(String(chips[ci][1]), cx + 66, 119, 22, chips[ci][2], 900);
    }

    var sx = shake ? (Math.random() - 0.5) * 18 * shake : 0;
    // question
    if (playing) {
      text(q.text + ' = ?', W / 2 + sx, 210, 54, '#fff', 900, W - 40);
    } else {
      text('7 × 8 = ?', W / 2, 210, 54, 'rgba(255,255,255,.25)', 900);
    }
    // answer box
    rr(70 + sx, 262, 340, 92, 20);
    ctx.fillStyle = flashBad > 0 ? 'rgba(248,113,113,' + (0.12 + flashBad * 0.25) + ')' : flashOk > 0 ? 'rgba(34,211,238,' + (0.08 + flashOk * 0.2) + ')' : 'rgba(255,255,255,.05)';
    ctx.fill();
    ctx.strokeStyle = flashBad > 0 ? '#f87171' : 'rgba(124,92,255,.7)'; ctx.lineWidth = 2.5; ctx.stroke();
    if (playing) {
      if (phase === 'reveal') {
        text(input, W / 2 - 50 + sx, 310, 42, '#f87171', 900);
        ctx.strokeStyle = '#f87171'; ctx.lineWidth = 3; ctx.beginPath(); ctx.moveTo(W / 2 - 90, 310); ctx.lineTo(W / 2 - 10, 310); ctx.stroke();
        text('→ ' + q.ans, W / 2 + 60, 310, 40, '#4ade80', 900);
      } else {
        text(input || '', W / 2 + sx, 310, 50, '#fff', 900);
        if (Math.floor(now * 2) % 2 === 0) { ctx.font = '900 50px' + FONT; var iw = ctx.measureText(input).width; ctx.fillStyle = '#22d3ee'; ctx.fillRect(W / 2 + iw / 2 + 4, 288, 4, 44); }
      }
    }

    // keypad
    for (var i = 0; i < KEYS.length; i++) {
      var r = keyRect(i), key = KEYS[i], pr = pressed[key] ? 1 : 0;
      rr(r.x, r.y + pr * 3, r.w, r.h, 18);
      if (key === 'ok') { var og = ctx.createLinearGradient(r.x, r.y, r.x + r.w, r.y + r.h); og.addColorStop(0, '#7c5cff'); og.addColorStop(1, '#c062f5'); ctx.fillStyle = og; }
      else if (key === 'del') ctx.fillStyle = pr ? 'rgba(244,114,182,.4)' : 'rgba(244,114,182,.14)';
      else ctx.fillStyle = pr ? 'rgba(124,92,255,.55)' : 'rgba(255,255,255,.07)';
      ctx.fill(); ctx.strokeStyle = 'rgba(255,255,255,.1)'; ctx.lineWidth = 1.5; ctx.stroke();
      text(key === 'del' ? '⌫' : key === 'ok' ? 'OK' : key, r.x + r.w / 2, r.y + r.h / 2 + pr * 3 + 1, key === 'ok' ? 26 : 34, '#fff', 800);
    }

    // particles & floaters
    if (live) {
      for (var p = particles.length - 1; p >= 0; p--) { var pt = particles[p]; pt.life -= dt; if (pt.life <= 0) { particles.splice(p, 1); continue; } pt.x += pt.vx * dt; pt.y += pt.vy * dt; pt.vx *= 0.95; pt.vy *= 0.95; }
      for (var f = floaters.length - 1; f >= 0; f--) { floaters[f].t += dt; if (floaters[f].t > 1) floaters.splice(f, 1); }
    }
    particles.forEach(function (o) { ctx.globalAlpha = Math.max(0, o.life / o.max); ctx.fillStyle = o.c; ctx.beginPath(); ctx.arc(o.x, o.y, o.r, 0, Math.PI * 2); ctx.fill(); });
    floaters.forEach(function (o) { ctx.globalAlpha = Math.max(0, 1 - o.t); text(o.text, o.x, o.y - o.t * 50, o.size, o.c, 900); });
    ctx.globalAlpha = 1;
  }

  // ---------------------------------------------------------------- input
  view.canvas.addEventListener('pointerdown', function (e) {
    e.preventDefault(); game.audio.ensure();
    var p = view.toLogical(e.clientX, e.clientY);
    for (var i = 0; i < KEYS.length; i++) { var r = keyRect(i); if (p.x >= r.x && p.x <= r.x + r.w && p.y >= r.y && p.y <= r.y + r.h) { typeKey(KEYS[i]); return; } }
  });
  game.on('keydown', function (e) {
    var k = e.key;
    if (k.length === 1 && k >= '0' && k <= '9') typeKey(k);
    else if (k === 'Backspace' || k === 'Delete') typeKey('del');
    else if (k === 'Enter' || k === ' ') typeKey('ok');
  });
  game.on('start', newRound);
  game.loop(update, render);
  game.showMenu();
  game.ready();
})();
