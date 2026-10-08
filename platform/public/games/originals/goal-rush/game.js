/* Goal Rush – original penalty shoot-out for Nebulo.
 * Take a penalty (aim + power), then keep one (dive left / centre / right). Five rounds, then sudden death.
 */
(function () {
  'use strict';
  var L = { en: 0, ka: 1, tr: 2, ru: 3 }[NebuloGame.lang] || 0;
  var TXT = {
    tagline: ['Win the penalty shoot-out: score past the keeper, then dive to save.',
      'მოიგე პენალტების სერია: გაიტანე მეკარის მიღმა, მერე კი გადაარჩინე კარი.',
      'Penaltı atışlarını kazan: kaleciyi geç, sonra da uçup kurtar.',
      'Выиграйте серию пенальти: забейте вратарю, а затем отбейте удар в прыжке.'][L],
    how: [
      ['Shooting: move the aim (mouse, finger or arrows) and tap / click / Space to lock it.', 'Then tap again when the power bar is in the green zone. Too much power and the ball can fly over.', 'Keeping: dive with ◀ / ▼ / ▶ (A S D) or tap the left, middle or right of the goal.', 'Watch the kicker – dive too late and you will not reach the corner.', '5 kicks each, then sudden death. Score = your goals.'],
      ['დარტყმა: გადაადგილე სამიზნე (მაუსი, თითი ან ისრები) და დააფიქსირე შეხებით / დაწკაპუნებით / Space-ით.', 'შემდეგ კვლავ შეეხე, როცა ძალის ზოლი მწვანე ზონაშია. ზედმეტი ძალით ბურთი შეიძლება ძელს გადასცდეს.', 'კარში დგომა: გადაეშვი ◀ / ▼ / ▶ (A S D) ღილაკებით ან შეეხე კარის მარცხენა, შუა ან მარჯვენა ნაწილს.', 'უყურე დამრტყმელს — დაგვიანებული ნახტომით კუთხეს ვერ მიწვდები.', 'თითოეულს 5 დარტყმა, შემდეგ — ერთ დარტყმამდე. ქულა = შენი გოლები.'],
      ['Şut: nişanı (fare, parmak ya da ok tuşları) hareket ettir, dokun / tıkla / Space ile sabitle.', 'Güç çubuğu yeşil bölgedeyken tekrar dokun. Fazla güçte top üstten auta gidebilir.', 'Kalecilik: ◀ / ▼ / ▶ (A S D) ile uç ya da kalenin soluna, ortasına veya sağına dokun.', 'Atıcıyı izle – geç uçarsan köşeye yetişemezsin.', 'Her takıma 5 atış, sonra seri penaltılar. Puan = attığın goller.'],
      ['Удар: двигайте прицел (мышь, палец или стрелки) и зафиксируйте касанием / щелчком / Пробелом.', 'Коснитесь ещё раз, когда шкала силы в зелёной зоне. Слишком сильный удар может уйти выше ворот.', 'Вратарь: прыгайте ◀ / ▼ / ▶ (A S D) или коснитесь левой, средней или правой части ворот.', 'Следите за бьющим — поздний прыжок не дотянется до угла.', 'По 5 ударов, затем до первого промаха. Очки = ваши голы.'],
    ][L],
    you: ['YOU', 'შენ', 'SEN', 'ВЫ'][L],
    cpu: ['CPU', 'CPU', 'CPU', 'CPU'][L],
    round: ['Round', 'რაუნდი', 'Tur', 'Раунд'][L],
    aim: ['Aim and tap to lock', 'დაუმიზნე და შეეხე დასაფიქსირებლად', 'Nişan al ve sabitlemek için dokun', 'Прицельтесь и коснитесь'][L],
    power: ['Tap in the green zone!', 'შეეხე მწვანე ზონაში!', 'Yeşil bölgede dokun!', 'Коснитесь в зелёной зоне!'][L],
    dive: ['Dive: ◀  ▼  ▶  or tap a side', 'გადაეშვი: ◀  ▼  ▶  ან შეეხე მხარეს', 'Uç: ◀  ▼  ▶  ya da bir tarafa dokun', 'Прыжок: ◀  ▼  ▶  или коснитесь стороны'][L],
    goal: ['GOAL!', 'გოლი!', 'GOL!', 'ГОЛ!'][L],
    saved: ['SAVED!', 'მოიგერია!', 'KURTARDI!', 'ОТБИТ!'][L],
    yourSave: ['GREAT SAVE!', 'შესანიშნავი სეივი!', 'MÜTHİŞ KURTARIŞ!', 'ОТЛИЧНЫЙ СЕЙВ!'][L],
    miss: ['MISSED!', 'აცდა!', 'AUT!', 'МИМО!'][L],
    post: ['OFF THE POST!', 'ძელს მოხვდა!', 'DİREKTEN DÖNDÜ!', 'В ШТАНГУ!'][L],
    conceded: ['They scored', 'მათ გაიტანეს', 'Gol yedin', 'Пропущен гол'][L],
    sudden: ['SUDDEN DEATH', 'ერთ დარტყმამდე', 'SERİ PENALTILAR', 'ДО ПЕРВОГО ПРОМАХА'][L],
    won: ['You won the shoot-out!', 'პენალტების სერია მოიგე!', 'Penaltıları kazandın!', 'Вы выиграли серию пенальти!'][L],
    lost: ['You lost the shoot-out.', 'პენალტების სერია წააგე.', 'Penaltıları kaybettin.', 'Вы проиграли серию пенальти.'][L],
    yourKick: ['Your kick', 'შენი დარტყმა', 'Senin atışın', 'Ваш удар'][L],
    theirKick: ['Their kick – get ready', 'მათი დარტყმა — მოემზადე', 'Rakip atıyor – hazırlan', 'Бьёт соперник — приготовьтесь'][L],
    final: ['Final', 'საბოლოო', 'Sonuç', 'Итог'][L],
  };

  var game = NebuloGame.create({ id: 'goal-rush', title: 'Goal Rush', tagline: TXT.tagline, howTo: TXT.how });
  var W = 720, H = 600;
  var GL = 130, GR = 590, GT = 150, GB = 330, GC = (GL + GR) / 2; // goal mouth (screen coords)
  var SPOT = { x: 360, y: 525 };
  var view = game.canvas(W, H), ctx = view.ctx;
  var s, clock = 0, crowd = [];
  (function () { var r = NebuloGame.mulberry32(21); for (var i = 0; i < 260; i++) crowd.push({ x: r() * W, y: 20 + r() * 95, c: ['#7c5cff', '#22d3ee', '#f472b6', '#fde047', '#334155', '#475569', '#1e293b'][Math.floor(r() * 7)], ph: r() * 6 }); })();
  var pointer = { x: GC, y: (GT + GB) / 2, has: false };

  function newGame() {
    s = { pk: [], ak: [], round: 1, phase: 'intro', t: 0, aim: { x: GC, y: (GT + GB) / 2 }, power: 0, msg: '', msgC: '#fff', ended: false,
      ball: null, keeper: { x: GC, lean: 0, dive: 0, dir: 0, t0: -1 }, kicker: null, shooter: 'p', banner: TXT.yourKick, netShake: 0, parts: [] };
    stats();
    toIntro('p');
  }
  function goals(arr) { return arr.filter(function (v) { return v; }).length; }
  function stats() {
    game.setStat('score', goals(s.pk));
    game.setStat('cpu', goals(s.ak), TXT.cpu);
    game.setStat('round', s.round, TXT.round);
  }
  function toIntro(who) {
    s.shooter = who; s.phase = 'intro'; s.t = 0;
    s.banner = (s.round > 5 && who === 'p' ? TXT.sudden + ' · ' : '') + (who === 'p' ? TXT.yourKick : TXT.theirKick);
    s.ball = { x: SPOT.x, y: SPOT.y, z: 0, r: 15, vis: true, spin: 0 };
    s.keeper = { x: GC, lean: 0, dive: 0, dir: 0, t0: -1, jump: 0, tx: GC };
    s.kicker = { x: SPOT.x - 90, y: SPOT.y + 40, phase: 0 };
    s.msg = '';
  }

  // decide whether the shoot-out is over
  function decided() {
    var p = goals(s.pk), a = goals(s.ak), np = s.pk.length, na = s.ak.length;
    if (np <= 5 && na <= 5 && !(np === 5 && na === 5)) {
      var pLeft = 5 - np, aLeft = 5 - na;
      if (p + pLeft < a) return 'lose';
      if (a + aLeft < p) return 'win';
      return null;
    }
    if (np === na) { if (p > a) return 'win'; if (a > p) return 'lose'; }
    return null;
  }

  function lockAim() { s.phase = 'power'; s.t = 0; game.audio.sfx('click'); }
  function strike() {
    var r = game.rng, p = s.power;
    var jit = 10 + Math.max(0, p - 0.86) * 150 + Math.max(0, 0.45 - p) * 40;
    var tx = s.aim.x + (r() - 0.5) * 2 * jit, ty = s.aim.y + (r() - 0.5) * 2 * jit * 0.7;
    if (p > 0.9) ty -= (p - 0.9) * 700;
    var dur = 0.95 - 0.5 * p;
    s.ball.from = { x: SPOT.x, y: SPOT.y }; s.ball.to = { x: tx, y: ty }; s.ball.dur = dur; s.ball.ft = 0; s.ball.arc = 40 + 60 * (1 - p);
    // CPU keeper decision
    var k = s.keeper, roll = r();
    var guessX;
    k.read = roll < 0.25;
    if (k.read) guessX = tx + (r() - 0.5) * 70; // reads the shot
    else { var z = Math.floor(r() * 3); guessX = z === 0 ? GL + 70 : z === 1 ? GC : GR - 70; }
    if (p < 0.5) guessX += (tx - guessX) * 0.5; // a soft shot gives the keeper time to adjust
    k.tx = Math.max(GL + 40, Math.min(GR - 40, guessX));
    k.t0 = 0.14 + r() * 0.1; k.dur = 0.5; k.dir = k.tx < GC - 40 ? -1 : k.tx > GC + 40 ? 1 : 0;
    s.phase = 'flight'; s.t = 0;
    game.audio.noise(0.08, 0.3); game.audio.tone(120, 0.1, 'sine', 0.3, 60);
  }

  function planAiKick() {
    var r = game.rng;
    var tx, ty;
    if (r() < 0.12) { // miss chance
      var side = r() < 0.5 ? -1 : 1;
      if (r() < 0.5) { tx = side < 0 ? GL - 15 - r() * 30 : GR + 15 + r() * 30; ty = GT + 30 + r() * 120; }
      else { tx = GL + 40 + r() * (GR - GL - 80); ty = GT - 20 - r() * 30; }
    } else {
      tx = GL + 22 + r() * (GR - GL - 44); ty = GT + 18 + r() * (GB - GT - 30);
    }
    s.aiShot = { x: tx, y: ty, dur: 0.55 + r() * 0.15, arc: 30 + r() * 40 };
    // body language: most of the time the run-up gives away the side, sometimes it is a bluff
    var side = tx < GC - 60 ? -1 : tx > GC + 60 ? 1 : 0;
    s.tellDir = r() < 0.65 ? side : [-1, 0, 1][Math.floor(r() * 3)];
  }
  function aiKick() {
    var tx = s.aiShot.x, ty = s.aiShot.y;
    s.ball.from = { x: SPOT.x, y: SPOT.y }; s.ball.to = { x: tx, y: ty }; s.ball.dur = s.aiShot.dur; s.ball.ft = 0; s.ball.arc = s.aiShot.arc;
    s.phase = 'flight'; s.t = 0;
    game.audio.noise(0.08, 0.3); game.audio.tone(120, 0.1, 'sine', 0.3, 60);
  }

  function playerDive(dir) {
    if (s.shooter !== 'a' || (s.phase !== 'runup' && s.phase !== 'flight') || s.keeper.chosen) return;
    var k = s.keeper;
    k.chosen = true; k.read = false;
    k.dir = dir; k.tx = dir < 0 ? GL + 75 : dir > 0 ? GR - 75 : GC;
    k.t0 = s.phase === 'flight' ? s.t : -(0.001); // diving before the kick = early start
    k.early = s.phase === 'runup';
    // a keeper who commits too early gets punished: the kicker may switch sides
    if (k.early && s.runDur - s.t > 0.35 && dir !== 0 && game.rng() < 0.6) {
      var sh = s.aiShot;
      if ((dir < 0 && sh.x < GC) || (dir > 0 && sh.x > GC)) sh.x = GC + (GC - sh.x);
    }
    game.audio.sfx('jump');
  }

  // keeper dive progress 0..1 at time t (seconds since kick)
  function keeperProgress(k, t) {
    var dur = k.dur || 0.42;
    if (k.early) return Math.min(1, (t + 0.08) / dur);
    if (k.t0 < 0) return 0;
    return Math.max(0, Math.min(1, (t - k.t0) / dur));
  }
  function keeperReach(k, prog, bx, by) {
    var kx = GC + (k.tx - GC) * prog;
    if (k.dir === 0) { // stays central and jumps
      return Math.abs(bx - kx) < 52 && by > GT - 5;
    }
    var half = 28 + 38 * prog;
    var reachTop = k.read ? GT + 25 : GB - 120 - 20 * prog;
    // a diving keeper stretches: corners near the crossbar are hard to reach
    return Math.abs(bx - kx) < half && by > reachTop;
  }

  function resolve() {
    var b = s.ball, to = b.to, k = s.keeper;
    var inX = to.x > GL + 6 && to.x < GR - 6, inY = to.y > GT + 6 && to.y < GB;
    var post = !inX && to.y < GB && to.y > GT - 20 && (Math.abs(to.x - GL) <= 10 || Math.abs(to.x - GR) <= 10) || (inX && Math.abs(to.y - GT) <= 8);
    var prog = keeperProgress(k, b.dur);
    var result;
    if (post) result = 'post';
    else if (!(inX && inY)) result = 'miss';
    else if (keeperReach(k, prog, to.x, to.y)) result = 'save';
    else result = 'goal';
    var isGoal = result === 'goal';
    if (s.shooter === 'p') {
      s.pk.push(isGoal);
      s.msg = isGoal ? TXT.goal : result === 'save' ? TXT.saved : result === 'post' ? TXT.post : TXT.miss;
      s.msgC = isGoal ? '#34d399' : '#f472b6';
      game.audio.sfx(isGoal ? 'bonus' : 'hit');
    } else {
      s.ak.push(isGoal);
      s.msg = isGoal ? TXT.conceded : result === 'save' ? TXT.yourSave : result === 'post' ? TXT.post : TXT.miss;
      s.msgC = isGoal ? '#f472b6' : '#34d399';
      game.audio.sfx(isGoal ? 'hit' : 'bonus');
    }
    s.result = result;
    if (isGoal) { s.netShake = 1; burst(to.x, to.y, 30, s.shooter === 'p' ? ['#34d399', '#22d3ee', '#fff'] : ['#f472b6', '#fb7185'], 260); }
    if (result === 'save') { b.bounce = { vx: (to.x - GC) * 0.8 + (game.rng() - 0.5) * 200, vy: 220 }; burst(to.x, to.y, 16, ['#fde047', '#fff'], 200); }
    if (result === 'post') { b.bounce = { vx: (to.x < GC ? 1 : -1) * 260, vy: 300 }; game.audio.tone(900, 0.2, 'square', 0.12, 600); }
    stats();
    s.phase = 'result'; s.t = 0;
  }

  function burst(x, y, n, cols, spd) {
    for (var i = 0; i < n; i++) { var a = Math.random() * 6.283, v = spd * (0.3 + Math.random() * 0.7); s.parts.push({ x: x, y: y, vx: Math.cos(a) * v, vy: Math.sin(a) * v, life: 0.8, c: cols[i % cols.length] }); }
  }

  function next() {
    var d = decided();
    if (d) { finish(d === 'win'); return; }
    if (s.shooter === 'p') toIntro('a');
    else { s.round++; stats(); toIntro('p'); }
  }

  function finish(win) {
    if (s.ended) return; s.ended = true;
    var p = goals(s.pk), a = goals(s.ak);
    game.over({
      score: p, win: win, title: win ? TXT.won : TXT.lost,
      lines: [TXT.final + ': ' + TXT.you + ' ' + p + ' – ' + a + ' ' + TXT.cpu],
      evidence: { p: s.pk.map(Number), a: s.ak.map(Number) },
    });
  }

  function update(dt) {
    clock += dt; s.t += dt;
    if (s.netShake > 0) s.netShake = Math.max(0, s.netShake - dt * 1.5);
    s.parts.forEach(function (p) { p.life -= dt; p.vy += 400 * dt; p.x += p.vx * dt; p.y += p.vy * dt; });
    s.parts = s.parts.filter(function (p) { return p.life > 0; });
    var k = game.keys, b = s.ball;
    if (s.phase === 'intro') {
      if (s.t > 1.0) { s.phase = s.shooter === 'p' ? 'aim' : 'runup'; s.t = 0; if (s.shooter === 'a') { s.runDur = 1.0 + game.rng() * 0.5; planAiKick(); } }
    } else if (s.phase === 'aim') {
      var mv = 330 * dt;
      if (k.ArrowLeft || k.a || k.A) s.aim.x -= mv;
      if (k.ArrowRight || k.d || k.D) s.aim.x += mv;
      if (k.ArrowUp || k.w || k.W) s.aim.y -= mv;
      if (k.ArrowDown || k.s || k.S) s.aim.y += mv;
      if (pointer.has) { s.aim.x += (pointer.x - s.aim.x) * Math.min(1, dt * 18); s.aim.y += (pointer.y - s.aim.y) * Math.min(1, dt * 18); }
      s.aim.x = Math.max(GL - 30, Math.min(GR + 30, s.aim.x));
      s.aim.y = Math.max(GT - 30, Math.min(GB - 8, s.aim.y));
      s.keeper.lean = Math.sin(clock * 2.2) * 0.12;
      s.keeper.x = GC + Math.sin(clock * 1.6) * 14;
    } else if (s.phase === 'power') {
      s.power = 0.5 - 0.5 * Math.cos(s.t * Math.PI * 2 / 1.25);
      s.keeper.x = GC + Math.sin(clock * 1.6) * 14;
    } else if (s.phase === 'runup') {
      var kk = s.kicker; kk.phase = Math.min(1, s.t / s.runDur);
      kk.x = SPOT.x - 90 + 70 * kk.phase; kk.y = SPOT.y + 40 - 20 * kk.phase;
      if (s.t >= s.runDur) aiKick();
    } else if (s.phase === 'flight') {
      b.ft = Math.min(1, s.t / b.dur);
      var e = b.ft;
      b.x = b.from.x + (b.to.x - b.from.x) * e;
      b.y = b.from.y + (b.to.y - b.from.y) * e - Math.sin(e * Math.PI) * b.arc;
      b.r = 15 - 8 * e; b.spin += dt * 20;
      if (b.ft >= 1) resolve();
    } else if (s.phase === 'result') {
      if (b.bounce) { b.x += b.bounce.vx * dt; b.y += b.bounce.vy * dt; b.bounce.vy += 600 * dt; b.r = Math.min(13, b.r + dt * 6); }
      else if (s.result === 'goal') { b.y = Math.min(GB - 8, b.y + 60 * dt); }
      else if (s.result === 'miss') { b.r = Math.max(3, b.r - dt * 6); b.x += (b.to.x - GC) * dt * 0.6; b.y -= 40 * dt; }
      if (s.t > 1.5) next();
    }
    // keeper animation
    var kp = s.keeper;
    if (s.phase === 'flight' || s.phase === 'result') {
      var t = s.phase === 'flight' ? s.t : s.ball.dur + s.t;
      var prog = keeperProgress(kp, t);
      kp.x = GC + (kp.tx - GC) * prog; kp.dive = prog * kp.dir; kp.jump = kp.dir === 0 ? Math.sin(Math.min(1, prog) * Math.PI) * 30 : prog * 12;
    } else if (s.phase === 'runup') {
      if (kp.chosen) { var pr = Math.min(1, s.t / 0.3); kp.x = GC + (kp.tx - GC) * 0.15 * pr; kp.lean = kp.dir * 0.2 * pr; }
    }
  }

  // ---------------------------------------------------------------- drawing
  function rr(x, y, w, h, r) { ctx.beginPath(); ctx.moveTo(x + r, y); ctx.arcTo(x + w, y, x + w, y + h, r); ctx.arcTo(x + w, y + h, x, y + h, r); ctx.arcTo(x, y + h, x, y, r); ctx.arcTo(x, y, x + w, y, r); ctx.closePath(); }

  function drawStadium() {
    var g = ctx.createLinearGradient(0, 0, 0, H);
    g.addColorStop(0, '#0b0d17'); g.addColorStop(0.25, '#141236'); g.addColorStop(0.5, '#0e1a26'); g.addColorStop(1, '#0b0d17');
    ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);
    crowd.forEach(function (c) { ctx.globalAlpha = 0.45; ctx.fillStyle = c.c; ctx.beginPath(); ctx.arc(c.x, c.y + (s && s.phase === 'result' ? Math.sin(clock * 12 + c.ph) * 2 : 0), 4, 0, 7); ctx.fill(); });
    ctx.globalAlpha = 1;
    // floodlights
    [[60, 12], [660, 12]].forEach(function (f) {
      var lg = ctx.createRadialGradient(f[0], f[1], 4, f[0], f[1], 260); lg.addColorStop(0, 'rgba(238,240,255,0.35)'); lg.addColorStop(1, 'rgba(0,0,0,0)');
      ctx.fillStyle = lg; ctx.fillRect(0, 0, W, H);
      ctx.fillStyle = '#fff'; ctx.beginPath(); ctx.arc(f[0], f[1], 5, 0, 7); ctx.fill();
    });
    // ad boards
    var ag = ctx.createLinearGradient(0, 0, W, 0); ag.addColorStop(0, '#7c5cff'); ag.addColorStop(0.5, '#22d3ee'); ag.addColorStop(1, '#f472b6');
    ctx.fillStyle = ag; ctx.globalAlpha = 0.8; ctx.fillRect(0, 118, W, 14); ctx.globalAlpha = 1;
    ctx.fillStyle = '#0b0d17'; ctx.font = '900 10px system-ui, sans-serif'; ctx.textAlign = 'center';
    for (var ax = 60; ax < W; ax += 160) ctx.fillText('NEBULO', ax + ((clock * 30) % 160), 129);
    // pitch with perspective stripes
    var top = 132;
    for (var i = 0; i < 9; i++) {
      var y0 = top + Math.pow(i / 9, 1.6) * (H - top), y1 = top + Math.pow((i + 1) / 9, 1.6) * (H - top);
      ctx.fillStyle = i % 2 ? '#0f3a2e' : '#124436'; ctx.fillRect(0, y0, W, y1 - y0 + 1);
    }
    ctx.strokeStyle = 'rgba(238,240,255,0.75)'; ctx.lineWidth = 3;
    ctx.beginPath(); ctx.moveTo(0, GB); ctx.lineTo(W, GB); ctx.stroke();
    ctx.beginPath(); ctx.moveTo(GL - 70, GB); ctx.lineTo(GL - 120, GB + 70); ctx.lineTo(GR + 120, GB + 70); ctx.lineTo(GR + 70, GB); ctx.stroke();
    ctx.beginPath(); ctx.moveTo(GL - 200, GB); ctx.lineTo(-60, H - 10); ctx.moveTo(GR + 200, GB); ctx.lineTo(W + 60, H - 10); ctx.stroke();
    ctx.fillStyle = 'rgba(238,240,255,0.8)'; ctx.beginPath(); ctx.ellipse(SPOT.x, SPOT.y + 12, 7, 3, 0, 0, 7); ctx.fill();
  }

  function drawGoal() {
    var sh = s ? s.netShake : 0;
    // net
    ctx.fillStyle = 'rgba(11,13,23,0.55)'; ctx.fillRect(GL, GT, GR - GL, GB - GT);
    ctx.strokeStyle = 'rgba(238,240,255,0.22)'; ctx.lineWidth = 1;
    for (var x = GL; x <= GR; x += 16) { ctx.beginPath(); ctx.moveTo(x, GT); ctx.quadraticCurveTo(x + Math.sin(clock * 20 + x) * sh * 6, (GT + GB) / 2, x, GB); ctx.stroke(); }
    for (var y = GT; y <= GB; y += 16) { ctx.beginPath(); ctx.moveTo(GL, y); ctx.quadraticCurveTo(GC, y + sh * 10, GR, y); ctx.stroke(); }
    // posts
    ctx.fillStyle = '#eef0ff'; ctx.shadowColor = '#22d3ee'; ctx.shadowBlur = 16;
    ctx.fillRect(GL - 8, GT - 8, 8, GB - GT + 8); ctx.fillRect(GR, GT - 8, 8, GB - GT + 8); ctx.fillRect(GL - 8, GT - 8, GR - GL + 16, 8);
    ctx.shadowBlur = 0;
  }

  function drawKeeper(k, player) {
    var col = player ? '#22d3ee' : '#f472b6', dark = player ? '#0e7490' : '#9d174d';
    ctx.save();
    var baseY = GB - 2 - (k.jump || 0);
    ctx.translate(k.x, baseY);
    var ang = (k.dive || 0) * 1.25 + (k.lean || 0);
    ctx.fillStyle = 'rgba(0,0,0,0.35)'; ctx.beginPath(); ctx.ellipse(0, (k.jump || 0) + 2, 34, 6, 0, 0, 7); ctx.fill();
    ctx.rotate(ang);
    // legs
    ctx.fillStyle = '#111827'; ctx.fillRect(-14, -36, 11, 36); ctx.fillRect(3, -36, 11, 36);
    ctx.fillStyle = dark; ctx.fillRect(-16, -48, 32, 14);
    // body
    ctx.fillStyle = col; ctx.shadowColor = col; ctx.shadowBlur = 14; rr(-19, -98, 38, 54, 10); ctx.fill(); ctx.shadowBlur = 0;
    ctx.fillStyle = 'rgba(255,255,255,0.25)'; ctx.font = '900 18px system-ui, sans-serif'; ctx.textAlign = 'center'; ctx.fillText('1', 0, -64);
    // arms (raised more when diving)
    var lift = Math.abs(k.dive || 0);
    ctx.strokeStyle = col; ctx.lineWidth = 10; ctx.lineCap = 'round';
    ctx.beginPath(); ctx.moveTo(-16, -90); ctx.lineTo(-38 - lift * 10, -110 - lift * 28); ctx.moveTo(16, -90); ctx.lineTo(38 + lift * 10, -110 - lift * 28); ctx.stroke();
    ctx.fillStyle = '#fde047'; ctx.beginPath(); ctx.arc(-40 - lift * 10, -114 - lift * 28, 8, 0, 7); ctx.arc(40 + lift * 10, -114 - lift * 28, 8, 0, 7); ctx.fill();
    // head
    ctx.fillStyle = '#f5d0b0'; ctx.beginPath(); ctx.arc(0, -112, 13, 0, 7); ctx.fill();
    ctx.fillStyle = dark; ctx.beginPath(); ctx.arc(0, -116, 13, Math.PI, 2 * Math.PI); ctx.fill();
    ctx.restore();
  }

  function drawKicker(kk) {
    var lean = s.phase === 'runup' ? (s.tellDir || 0) * 0.2 * Math.min(1, kk.phase * 1.5) : 0;
    ctx.save(); ctx.translate(kk.x, kk.y); ctx.rotate(lean);
    ctx.fillStyle = 'rgba(0,0,0,0.35)'; ctx.beginPath(); ctx.ellipse(0, 0, 26, 7, 0, 0, 7); ctx.fill();
    var step = Math.sin(kk.phase * 18) * 8;
    ctx.fillStyle = '#e5e7eb'; ctx.fillRect(-14, -50, 11, 50 + step * 0.2); ctx.fillRect(3, -50, 11, 50 - step * 0.2);
    ctx.fillStyle = '#f472b6'; rr(-22, -118, 44, 72, 12); ctx.fill();
    ctx.fillStyle = '#0b0d17'; ctx.font = '900 26px system-ui, sans-serif'; ctx.textAlign = 'center'; ctx.fillText('9', 0, -72);
    ctx.fillStyle = '#7c2d12'; ctx.beginPath(); ctx.arc(0, -134, 16, 0, 7); ctx.fill();
    // eyes look where the shot is going (or where he wants you to think)
    ctx.fillStyle = '#fff'; ctx.beginPath(); ctx.arc(-5 + (s.tellDir || 0) * 5, -136, 3, 0, 7); ctx.arc(5 + (s.tellDir || 0) * 5, -136, 3, 0, 7); ctx.fill();
    ctx.restore();
  }

  function drawBall(b) {
    if (!b || !b.vis) return;
    ctx.fillStyle = 'rgba(0,0,0,0.3)';
    if (s.phase !== 'flight' || s.ball.ft < 0.1) { ctx.beginPath(); ctx.ellipse(b.x, b.y + b.r, b.r, b.r * 0.35, 0, 0, 7); ctx.fill(); }
    ctx.save(); ctx.translate(b.x, b.y); ctx.rotate(b.spin);
    var g = ctx.createRadialGradient(-b.r * 0.3, -b.r * 0.3, 1, 0, 0, b.r); g.addColorStop(0, '#ffffff'); g.addColorStop(1, '#b4b9d6');
    ctx.fillStyle = g; ctx.beginPath(); ctx.arc(0, 0, b.r, 0, 7); ctx.fill();
    ctx.fillStyle = '#1e293b';
    for (var i = 0; i < 5; i++) { var a = i * 1.2566; ctx.beginPath(); ctx.arc(Math.cos(a) * b.r * 0.62, Math.sin(a) * b.r * 0.62, b.r * 0.22, 0, 7); ctx.fill(); }
    ctx.beginPath(); ctx.arc(0, 0, b.r * 0.28, 0, 7); ctx.fill();
    ctx.restore();
  }

  function drawScoreboard() {
    ctx.fillStyle = 'rgba(11,13,23,0.82)'; rr(W / 2 - 170, 8, 340, 56, 12); ctx.fill();
    ctx.strokeStyle = 'rgba(124,92,255,0.5)'; ctx.lineWidth = 1.5; rr(W / 2 - 170, 8, 340, 56, 12); ctx.stroke();
    ctx.font = '800 12px system-ui, sans-serif'; ctx.textAlign = 'left';
    [[TXT.you, s.pk, 28, '#22d3ee'], [TXT.cpu, s.ak, 50, '#f472b6']].forEach(function (row) {
      ctx.fillStyle = row[3]; ctx.fillText(row[0], W / 2 - 158, row[2] + 4);
      var n = Math.max(5, row[1].length, s.round);
      var start = Math.max(0, n - 7);
      for (var i = start; i < n; i++) {
        var x = W / 2 - 100 + (i - start) * 24, v = row[1][i];
        ctx.beginPath(); ctx.arc(x, row[2], 7, 0, 7);
        if (v === undefined) { ctx.strokeStyle = 'rgba(255,255,255,0.3)'; ctx.lineWidth = 1.5; ctx.stroke(); }
        else { ctx.fillStyle = v ? '#34d399' : '#fb7185'; ctx.fill(); }
      }
      ctx.fillStyle = '#fff'; ctx.textAlign = 'right'; ctx.font = '900 18px system-ui, sans-serif'; ctx.fillText(String(goals(row[1])), W / 2 + 158, row[2] + 6);
      ctx.textAlign = 'left'; ctx.font = '800 12px system-ui, sans-serif';
    });
  }

  function render(dt) {
    if (!s) return;
    if (game.state === 'menu') clock += dt;
    drawStadium();
    drawGoal();
    var playerKeeps = s.shooter === 'a';
    var ballBehindKeeper = s.phase === 'result' && s.result === 'goal';
    if (ballBehindKeeper) drawBall(s.ball);
    drawKeeper(s.keeper, playerKeeps);
    if (!ballBehindKeeper && !(s.phase !== 'flight' && s.phase !== 'result')) drawBall(s.ball);
    if (s.shooter === 'a' && (s.phase === 'intro' || s.phase === 'runup')) drawKicker(s.kicker);
    if (s.phase !== 'flight' && s.phase !== 'result') drawBall(s.ball);
    // aim reticle
    if (s.shooter === 'p' && (s.phase === 'aim' || s.phase === 'power')) {
      var a = s.aim;
      ctx.strokeStyle = s.phase === 'power' ? '#fde047' : '#22d3ee'; ctx.lineWidth = 2.5; ctx.shadowColor = ctx.strokeStyle; ctx.shadowBlur = 10;
      ctx.beginPath(); ctx.arc(a.x, a.y, 16 + Math.sin(clock * 6) * 2, 0, 7); ctx.stroke();
      ctx.beginPath(); ctx.moveTo(a.x - 24, a.y); ctx.lineTo(a.x - 8, a.y); ctx.moveTo(a.x + 8, a.y); ctx.lineTo(a.x + 24, a.y);
      ctx.moveTo(a.x, a.y - 24); ctx.lineTo(a.x, a.y - 8); ctx.moveTo(a.x, a.y + 8); ctx.lineTo(a.x, a.y + 24); ctx.stroke(); ctx.shadowBlur = 0;
      ctx.setLineDash([4, 8]); ctx.strokeStyle = 'rgba(238,240,255,0.25)'; ctx.lineWidth = 2;
      ctx.beginPath(); ctx.moveTo(SPOT.x, SPOT.y); ctx.quadraticCurveTo((SPOT.x + a.x) / 2, (SPOT.y + a.y) / 2 - 50, a.x, a.y); ctx.stroke(); ctx.setLineDash([]);
    }
    if (s.phase === 'power') {
      var bx = W - 64, by = 360, bh = 200;
      ctx.fillStyle = 'rgba(11,13,23,0.8)'; rr(bx - 4, by - 4, 32, bh + 8, 10); ctx.fill();
      ctx.fillStyle = 'rgba(52,211,153,0.35)'; ctx.fillRect(bx, by + bh * (1 - 0.86), 24, bh * (0.86 - 0.55));
      ctx.fillStyle = 'rgba(251,113,133,0.35)'; ctx.fillRect(bx, by, 24, bh * 0.1);
      var pv = s.power, pc = pv > 0.9 ? '#fb7185' : pv >= 0.55 && pv <= 0.86 ? '#34d399' : '#fde047';
      ctx.fillStyle = pc; ctx.shadowColor = pc; ctx.shadowBlur = 12; ctx.fillRect(bx, by + bh * (1 - pv), 24, bh * pv); ctx.shadowBlur = 0;
    }
    s.parts.forEach(function (p) { ctx.globalAlpha = Math.max(0, p.life / 0.8); ctx.fillStyle = p.c; ctx.fillRect(p.x - 2, p.y - 2, 4, 4); });
    ctx.globalAlpha = 1;
    drawScoreboard();
    if (game.state === 'menu') return;
    ctx.textAlign = 'center';
    var hint = '';
    if (s.phase === 'intro') hint = s.banner;
    else if (s.phase === 'aim') hint = TXT.aim;
    else if (s.phase === 'power') hint = TXT.power;
    else if (s.shooter === 'a' && (s.phase === 'runup' || s.phase === 'flight') && !s.keeper.chosen) hint = TXT.dive;
    if (hint) {
      ctx.font = '800 18px system-ui, sans-serif';
      var tw = ctx.measureText(hint).width + 36;
      ctx.fillStyle = 'rgba(11,13,23,0.75)'; rr(W / 2 - tw / 2, 346, tw, 40, 20); ctx.fill();
      ctx.fillStyle = s.phase === 'intro' ? '#fde047' : '#eef0ff'; ctx.fillText(hint, W / 2, 372);
    }
    if (s.phase === 'result' && s.msg) {
      var sc = 1 + Math.max(0, 0.3 - s.t) * 2;
      ctx.save(); ctx.translate(W / 2, 250); ctx.scale(sc, sc);
      ctx.font = '900 54px system-ui, sans-serif'; ctx.fillStyle = s.msgC; ctx.shadowColor = s.msgC; ctx.shadowBlur = 24;
      ctx.fillText(s.msg, 0, 0); ctx.restore(); ctx.shadowBlur = 0;
    }
  }

  // ---------------------------------------------------------------- input
  var cv = view.canvas;
  function toGoalPoint(e) { var p = view.toLogical(e.clientX, e.clientY); return p; }
  cv.addEventListener('pointermove', function (e) {
    if (e.pointerType === 'mouse') { var p = toGoalPoint(e); pointer.x = p.x; pointer.y = p.y; pointer.has = true; }
  });
  cv.addEventListener('pointerdown', function (e) {
    if (game.state !== 'playing') return;
    var p = toGoalPoint(e);
    if (s.shooter === 'p') {
      if (s.phase === 'aim') {
        s.aim.x = Math.max(GL - 30, Math.min(GR + 30, p.x)); s.aim.y = Math.max(GT - 30, Math.min(GB - 8, p.y));
        pointer.has = false; lockAim();
      } else if (s.phase === 'power') strike();
    } else {
      playerDive(p.x < GL + (GR - GL) / 3 ? -1 : p.x > GR - (GR - GL) / 3 ? 1 : 0);
    }
  });
  game.on('keydown', function (e) {
    if (game.state !== 'playing') return;
    var key = e.key;
    if (s.shooter === 'p') {
      if (key === ' ' || e.code === 'Space' || key === 'Enter') { pointer.has = false; if (s.phase === 'aim') lockAim(); else if (s.phase === 'power') strike(); }
    } else {
      if (key === 'ArrowLeft' || key === 'a' || key === 'A') playerDive(-1);
      else if (key === 'ArrowRight' || key === 'd' || key === 'D') playerDive(1);
      else if (key === 'ArrowDown' || key === 's' || key === 'S' || key === ' ' || key === 'ArrowUp' || key === 'w' || key === 'W') playerDive(0);
    }
  });
  game.on('start', function () { pointer.has = false; newGame(); });
  game.loop(update, render);

  newGame();
  game.showMenu();
  game.ready();
})();
