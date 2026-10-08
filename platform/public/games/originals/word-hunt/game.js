/* Hidden Words – original word search for Nebulo.
 * Themed word lists for every supported language (en / ka / tr / ru, each in its own alphabet).
 * Grids are generated from the round's seeded RNG: words are placed in up to 8 directions and the
 * rest is filled with letters of the same alphabet.
 */
(function () {
  'use strict';
  var LANG = NebuloGame.lang;
  var L = { en: 0, ka: 1, tr: 2, ru: 3 }[LANG] || 0;
  var TXT = {
    tagline: ['Find every hidden word in the letter grid – across, down, diagonal and even backwards.', 'იპოვე ყველა დამალული სიტყვა ასოების ბადეში – ჰორიზონტალურად, ვერტიკალურად, დიაგონალზე და უკუღმაც.', 'Harf tablosundaki tüm gizli kelimeleri bul – yatay, dikey, çapraz ve hatta tersten.', 'Найдите все спрятанные слова в сетке букв — по горизонтали, вертикали, диагонали и даже задом наперёд.'][L],
    how: [
      ['Drag across letters to select a word (or tap its first and last letter).', 'Words run in straight lines in any of 8 directions.', 'Found words are crossed off the list.', 'Longer words and harder grids score more; finish fast for a time bonus.', 'Keyboard: arrows move, Space / Enter marks start and end.'],
      ['გადაატარე თითი ან მაუსი ასოებზე სიტყვის მოსანიშნად (ან შეეხე პირველ და ბოლო ასოს).', 'სიტყვები სწორ ხაზზეა 8-დან ნებისმიერი მიმართულებით.', 'ნაპოვნი სიტყვები სიიდან იშლება.', 'გრძელი სიტყვები და რთული ბადე მეტ ქულას იძლევა; სწრაფად დასრულებისთვის – დროის ბონუსი.', 'კლავიატურა: ისრები – გადაადგილება, Space / Enter – დასაწყისი და დასასრული.'],
      ['Bir kelimeyi seçmek için harflerin üzerinden sürükle (veya ilk ve son harfine dokun).', 'Kelimeler 8 yönden herhangi birinde düz bir çizgi üzerindedir.', 'Bulunan kelimeler listeden çizilir.', 'Uzun kelimeler ve zor tablolar daha çok puan verir; hızlı bitirirsen süre bonusu kazanırsın.', 'Klavye: ok tuşlarıyla gezin, Space / Enter başlangıç ve bitişi işaretler.'],
      ['Проведите по буквам, чтобы выделить слово (или нажмите на первую и последнюю букву).', 'Слова идут по прямой в любом из 8 направлений.', 'Найденные слова вычёркиваются из списка.', 'Длинные слова и сложные сетки дают больше очков, а быстрая игра — бонус за время.', 'Клавиатура: стрелки — перемещение, Space / Enter — начало и конец слова.'],
    ][L],
    difficulty: ['Grid size', 'ბადის ზომა', 'Tablo boyutu', 'Размер сетки'][L],
    levels: [['Easy · 8×8', 'Normal · 10×10', 'Hard · 12×12'], ['მარტივი · 8×8', 'საშუალო · 10×10', 'რთული · 12×12'], ['Kolay · 8×8', 'Orta · 10×10', 'Zor · 12×12'], ['Легко · 8×8', 'Средне · 10×10', 'Сложно · 12×12']][L],
    theme: ['Theme', 'თემა', 'Tema', 'Тема'][L],
    words: ['Words', 'სიტყვები', 'Kelime', 'Слова'][L],
    summary: function (n, t) { return ['All ' + n + ' words found in ' + t, 'ნაპოვნია ყველა ' + n + ' სიტყვა: ' + t, n + ' kelimenin tamamı bulundu: ' + t, 'Найдены все ' + n + ' слов за ' + t][L]; },
    bonusLine: function (b) { return ['Time bonus +' + b, 'დროის ბონუსი +' + b, 'Süre bonusu +' + b, 'Бонус за время +' + b][L]; },
  };

  // ---------------------------------------------------------------- word data (real words, per language)
  var DATA = {
    en: {
      alphabet: 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
      themes: [
        ['Space', 'PLANET COMET GALAXY ORBIT ROCKET NEBULA METEOR STAR MOON ASTEROID ECLIPSE SATURN COSMOS GRAVITY LUNAR SATELLITE'],
        ['Animals', 'TIGER ZEBRA OTTER EAGLE PANDA CAMEL RABBIT DOLPHIN GIRAFFE LEOPARD FALCON BADGER PARROT WALRUS LIZARD HEDGEHOG'],
        ['Food', 'APPLE MANGO BREAD CHEESE PASTA HONEY PEPPER ONION LEMON CARROT BUTTER COOKIE WAFFLE NOODLE PEACH PANCAKE'],
        ['Sports', 'SOCCER TENNIS HOCKEY RUGBY GOLF BOXING ROWING SKIING KARATE CYCLING ARCHERY SURFING JUDO DIVING CHESS VOLLEYBALL'],
        ['Nature', 'RIVER FOREST CANYON ISLAND VALLEY DESERT MEADOW GLACIER VOLCANO LAGOON BREEZE THUNDER CLOUD OCEAN STONE WATERFALL'],
        ['Music', 'GUITAR PIANO VIOLIN DRUMS FLUTE MELODY RHYTHM TEMPO CHORD HARP BANJO CELLO OPERA LYRICS TRUMPET ORCHESTRA'],
      ],
    },
    ka: {
      alphabet: 'აბგდევზთიკლმნოპჟრსტუფქღყშჩცძწჭხჯჰ',
      themes: [
        ['კოსმოსი', 'პლანეტა კომეტა გალაქტიკა ორბიტა რაკეტა ნისლეული მეტეორი ვარსკვლავი მთვარე ასტეროიდი დაბნელება სატურნი კოსმოსი თანამგზავრი მზე'],
        ['ცხოველები', 'ვეფხვი ზებრა წავი არწივი პანდა აქლემი კურდღელი დელფინი ჟირაფი ლეოპარდი შევარდენი მაჩვი თუთიყუში ლომი ხვლიკი მელია დათვი ზღარბი'],
        ['საჭმელი', 'ვაშლი მანგო პური ყველი თაფლი წიწაკა ხახვი ლიმონი სტაფილო კარაქი ნამცხვარი ატამი ხაჭაპური ლობიო მწვადი ბადრიჯანი'],
        ['სპორტი', 'ფეხბურთი ჩოგბურთი ჰოკეი რაგბი გოლფი კრივი ნიჩბოსნობა კარატე ველოსიპედი ძიუდო ცურვა ჭადრაკი ჭიდაობა კალათბურთი ფრენბურთი რბენა'],
        ['ბუნება', 'მდინარე ტყე კანიონი კუნძული ხეობა უდაბნო მდელო მყინვარი ვულკანი ლაგუნა ქარი ქუხილი ღრუბელი ოკეანე ქვა ჩანჩქერი მთა'],
        ['მუსიკა', 'გიტარა პიანინო ვიოლინო დოლი ფლეიტა მელოდია რიტმი ტემპი აკორდი არფა ფანდური ჩელო ოპერა სიმღერა საყვირი ნოტი'],
      ],
    },
    tr: {
      alphabet: 'ABCÇDEFGĞHIİJKLMNOÖPRSŞTUÜVYZ',
      themes: [
        ['Uzay', 'GEZEGEN YILDIZ GÖKADA YÖRÜNGE ROKET GÜNEŞ METEOR ASTEROİT TUTULMA BULUTSU EVREN UYDU SATÜRN MERKÜR KOZMOS'],
        ['Hayvanlar', 'KAPLAN ZEBRA ASLAN KARTAL PANDA DEVE TAVŞAN YUNUS ZÜRAFA LEOPAR ŞAHİN PAPAĞAN KERTENKELE BALİNA SİNCAP KİRPİ'],
        ['Yiyecekler', 'ELMA MUZ EKMEK PEYNİR MAKARNA BAL BİBER SOĞAN LİMON HAVUÇ TEREYAĞI KURABİYE ŞEFTALİ ÇORBA PİLAV ZEYTİN'],
        ['Spor', 'FUTBOL TENİS HOKEY RAGBİ GOLF BOKS KÜREK KAYAK KARATE BİSİKLET OKÇULUK SÖRF SATRANÇ JUDO DALIŞ VOLEYBOL'],
        ['Doğa', 'NEHİR ORMAN KANYON ADA VADİ ÇÖL ÇAYIR BUZUL YANARDAĞ LAGÜN RÜZGAR GÖKYÜZÜ BULUT OKYANUS TAŞ DAĞ ŞELALE'],
        ['Müzik', 'GİTAR PİYANO KEMAN DAVUL FLÜT MELODİ RİTİM AKOR ARP BAĞLAMA OPERA ŞARKI TROMPET NOTA NEY TEF'],
      ],
    },
    ru: {
      alphabet: 'АБВГДЕЖЗИЙКЛМНОПРСТУФХЦЧШЩЫЬЭЮЯ',
      themes: [
        ['Космос', 'ПЛАНЕТА КОМЕТА ГАЛАКТИКА ОРБИТА РАКЕТА ТУМАННОСТЬ МЕТЕОР ЗВЕЗДА ЛУНА АСТЕРОИД ЗАТМЕНИЕ САТУРН КОСМОС СПУТНИК ВСЕЛЕННАЯ'],
        ['Животные', 'ТИГР ЗЕБРА ВЫДРА ОРЕЛ ПАНДА ВЕРБЛЮД КРОЛИК ДЕЛЬФИН ЖИРАФ ЛЕОПАРД СОКОЛ БАРСУК ПОПУГАЙ МОРЖ ЯЩЕРИЦА ЛИСА МЕДВЕДЬ'],
        ['Еда', 'ЯБЛОКО МАНГО ХЛЕБ СЫР МАКАРОНЫ МЕД ПЕРЕЦ ЛУК ЛИМОН МОРКОВЬ МАСЛО ПЕЧЕНЬЕ БЛИНЫ ПЕРСИК СУП КАША'],
        ['Спорт', 'ФУТБОЛ ТЕННИС ХОККЕЙ РЕГБИ ГОЛЬФ БОКС ГРЕБЛЯ ЛЫЖИ КАРАТЭ ВЕЛОСПОРТ ДЗЮДО ПЛАВАНИЕ ШАХМАТЫ БЕГ ВОЛЕЙБОЛ'],
        ['Природа', 'РЕКА ЛЕС КАНЬОН ОСТРОВ ДОЛИНА ПУСТЫНЯ ЛУГ ЛЕДНИК ВУЛКАН ЛАГУНА ВЕТЕР ГРОЗА ОБЛАКО ОКЕАН КАМЕНЬ ВОДОПАД'],
        ['Музыка', 'ГИТАРА РОЯЛЬ СКРИПКА БАРАБАН ФЛЕЙТА МЕЛОДИЯ РИТМ ТЕМП АККОРД АРФА БАЛАЛАЙКА ВИОЛОНЧЕЛЬ ОПЕРА ПЕСНЯ ТРУБА НОТА'],
      ],
    },
  };
  var LANGDATA = DATA[LANG] || DATA.en;
  function chars(w) { return Array.from(w); }

  var game = NebuloGame.create({ id: 'word-hunt', title: 'Hidden Words', tagline: TXT.tagline, howTo: TXT.how });

  // Run fn after ms if the same round is still active (waits while paused, drops it after a restart).
  var roundId = 0;
  game.on('start', function () { roundId++; });
  function later(fn, ms) {
    var rid = roundId;
    setTimeout(function tick() {
      if (rid !== roundId) return;
      if (game.state === 'paused') { setTimeout(tick, 250); return; }
      if (game.state === 'playing') fn();
    }, ms);
  }

  var LEVELS = [
    { n: 8, words: 6, mult: 1, target: 180, dirs: [[1, 0], [0, 1], [1, 1], [1, -1]] },
    { n: 10, words: 9, mult: 2, target: 300, dirs: [[1, 0], [0, 1], [1, 1], [1, -1], [-1, 0], [0, -1], [-1, -1], [-1, 1]] },
    { n: 12, words: 13, mult: 3, target: 480, dirs: [[1, 0], [0, 1], [1, 1], [1, -1], [-1, 0], [0, -1], [-1, -1], [-1, 1]] },
  ];
  var COLORS = ['#22d3ee', '#f472b6', '#a78bfa', '#4ade80', '#fb923c', '#facc15', '#38bdf8', '#f87171', '#2dd4bf', '#c084fc', '#fde047', '#60a5fa', '#fb7185'];
  var diff = game.store.get('word-hunt.diff', 0); if (!(diff >= 0 && diff <= 2)) diff = 0;
  var lvl = LEVELS[diff], n = 8, grid = [], words = [], found = 0, score = 0, themeName = '', cell = 40, lastSec = -1, done = false;
  var sel = null, anchor = null, cursor = { x: 0, y: 0 }, kb = false, flashes = [], parts = [];

  // ---------------------------------------------------------------- DOM
  var wrap = document.createElement('div'); wrap.id = 'wh-wrap'; game.stage.appendChild(wrap);
  var canvas = document.createElement('canvas'); canvas.id = 'wh-grid'; wrap.appendChild(canvas);
  var ctx = canvas.getContext('2d');
  var side = document.createElement('div'); side.id = 'wh-side'; wrap.appendChild(side);
  var themeEl = document.createElement('div'); themeEl.id = 'wh-theme'; side.appendChild(themeEl);
  var listEl = document.createElement('div'); listEl.id = 'wh-list'; side.appendChild(listEl);

  // ---------------------------------------------------------------- generation
  function shuffle(a, rng) { for (var i = a.length - 1; i > 0; i--) { var j = Math.floor(rng() * (i + 1)); var t = a[i]; a[i] = a[j]; a[j] = t; } return a; }
  function generate(rng) {
    var th = LANGDATA.themes[Math.floor(rng() * LANGDATA.themes.length)];
    var pool = th[1].split(' ').filter(function (w) { var l = chars(w).length; return l >= 3 && l <= n; });
    shuffle(pool, rng);
    var pick = pool.slice(0, Math.min(pool.length, lvl.words + 3));
    pick.sort(function (a, b) { return chars(b).length - chars(a).length; });
    var g = []; for (var i = 0; i < n * n; i++) g.push('');
    var placed = [];
    for (var w = 0; w < pick.length && placed.length < lvl.words; w++) {
      var letters = chars(pick[w]), len = letters.length, best = null;
      for (var tries = 0; tries < 250; tries++) {
        var d = lvl.dirs[Math.floor(rng() * lvl.dirs.length)];
        var x0 = Math.floor(rng() * n), y0 = Math.floor(rng() * n);
        var x1 = x0 + d[0] * (len - 1), y1 = y0 + d[1] * (len - 1);
        if (x1 < 0 || y1 < 0 || x1 >= n || y1 >= n) continue;
        var ok = true, overlap = 0;
        for (var k = 0; k < len; k++) {
          var c = g[(y0 + d[1] * k) * n + x0 + d[0] * k];
          if (c && c !== letters[k]) { ok = false; break; }
          if (c) overlap++;
        }
        if (!ok || overlap === len) continue;
        if (!best || overlap > best.overlap) best = { x: x0, y: y0, d: d, overlap: overlap };
        if (best.overlap >= 1 || tries > 120) break;
      }
      if (!best) continue;
      var cells = [];
      for (var q = 0; q < len; q++) { var idx = (best.y + best.d[1] * q) * n + best.x + best.d[0] * q; g[idx] = letters[q]; cells.push(idx); }
      placed.push({ word: pick[w], cells: cells, found: false, color: '' });
    }
    // filler: mostly the theme's own letters (good decoys) mixed with the whole alphabet
    var alpha = chars(LANGDATA.alphabet), themeLetters = chars(th[1].replace(/ /g, ''));
    for (var f = 0; f < n * n; f++) if (!g[f]) g[f] = rng() < 0.45 ? themeLetters[Math.floor(rng() * themeLetters.length)] : alpha[Math.floor(rng() * alpha.length)];
    placed.sort(function (a, b) { return a.word < b.word ? -1 : 1; });
    return { grid: g, words: placed, theme: th[0] };
  }

  // ---------------------------------------------------------------- layout
  function layout() {
    var r = game.stage.getBoundingClientRect(); if (!r.width || !r.height) return;
    var land = r.width > r.height * 1.1;
    wrap.classList.toggle('port', !land);
    var S;
    if (land) {
      var sideW = Math.max(170, Math.min(300, r.width * 0.3));
      S = Math.min(r.height - 24, r.width - sideW - 48);
      side.style.width = sideW + 'px'; side.style.maxHeight = S + 'px';
    } else {
      S = Math.min(r.width - 20, r.height * 0.64);
      side.style.width = ''; side.style.maxHeight = Math.max(90, r.height - S - 40) + 'px';
    }
    S = Math.max(200, Math.floor(S));
    cell = S / n;
    var d = Math.min(window.devicePixelRatio || 1, 2.5);
    canvas.style.width = S + 'px'; canvas.style.height = S + 'px';
    canvas.width = Math.round(S * d); canvas.height = Math.round(S * d);
    ctx.setTransform(d, 0, 0, d, 0, 0);
  }

  function renderList() {
    themeEl.innerHTML = '';
    themeEl.appendChild(document.createTextNode(TXT.theme));
    var b = document.createElement('b'); b.textContent = themeName; themeEl.appendChild(b);
    listEl.innerHTML = '';
    words.forEach(function (w) {
      var s = document.createElement('span'); s.className = 'wh-word' + (w.found ? ' found' : ''); s.textContent = w.word;
      if (w.found) s.style.setProperty('--c', w.color);
      w.el = s; listEl.appendChild(s);
    });
  }

  // ---------------------------------------------------------------- selection
  function cellXY(e) {
    var r = canvas.getBoundingClientRect();
    return { fx: (e.clientX - r.left) / cell - 0.5, fy: (e.clientY - r.top) / cell - 0.5 };
  }
  function clampCell(v) { return Math.max(0, Math.min(n - 1, v)); }
  /** Snap a drag from start cell to an 8-direction line inside the grid. */
  function snapLine(sx, sy, fx, fy) {
    var dx = fx - sx, dy = fy - sy;
    if (Math.abs(dx) < 0.5 && Math.abs(dy) < 0.5) return { x0: sx, y0: sy, dx: 0, dy: 0, len: 1 };
    var ang = Math.atan2(dy, dx), oct = Math.round(ang / (Math.PI / 4));
    var ux = Math.round(Math.cos(oct * Math.PI / 4)), uy = Math.round(Math.sin(oct * Math.PI / 4));
    var dist = ux && uy ? (Math.abs(dx) + Math.abs(dy)) / 2 : Math.max(Math.abs(dx), Math.abs(dy));
    var steps = Math.round(dist);
    // stay inside the grid
    while (steps > 0 && (sx + ux * steps < 0 || sx + ux * steps >= n || sy + uy * steps < 0 || sy + uy * steps >= n)) steps--;
    return { x0: sx, y0: sy, dx: ux, dy: uy, len: steps + 1 };
  }
  function lineCells(l) { var out = []; for (var k = 0; k < l.len; k++) out.push((l.y0 + l.dy * k) * n + l.x0 + l.dx * k); return out; }
  function tryLine(l) {
    if (l.len < 2) return false;
    var cells = lineCells(l), str = cells.map(function (i) { return grid[i]; }).join('');
    var rev = cells.slice().reverse().map(function (i) { return grid[i]; }).join('');
    for (var w = 0; w < words.length; w++) {
      var wd = words[w];
      if (wd.found) continue;
      if (wd.word === str || wd.word === rev) {
        wd.found = true; wd.cells = wd.word === str ? cells : cells.slice().reverse();
        wd.color = COLORS[found % COLORS.length]; found++;
        var gain = chars(wd.word).length * 10 * lvl.mult; score += gain;
        game.setStat('score', score); game.setStat('words', found + '/' + words.length, TXT.words);
        game.audio.sfx(found === words.length ? 'bonus' : 'point');
        flashes.push({ cells: wd.cells, color: wd.color, t: 0, text: '+' + gain });
        var mid = wd.cells[Math.floor(wd.cells.length / 2)];
        burst((mid % n + 0.5) * cell, (((mid / n) | 0) + 0.5) * cell, wd.color, 26);
        renderList(); wd.el.classList.add('just');
        if (found === words.length) win();
        return true;
      }
    }
    game.audio.tone(180, 0.1, 'triangle', 0.08, 140);
    return false;
  }
  function burst(x, y, color, k) { for (var i = 0; i < k; i++) { var a = Math.random() * 6.283, s = 0.8 + Math.random() * 3.5; parts.push({ x: x, y: y, vx: Math.cos(a) * s, vy: Math.sin(a) * s - 1, life: 0.6 + Math.random() * 0.5, size: 1.5 + Math.random() * 3, color: color }); } }

  var drag = null;
  canvas.addEventListener('pointerdown', function (e) {
    e.preventDefault(); game.audio.ensure();
    if (game.state !== 'playing' || done) return;
    var p = cellXY(e), sx = clampCell(Math.round(p.fx)), sy = clampCell(Math.round(p.fy));
    kb = false;
    try { canvas.setPointerCapture(e.pointerId); } catch (er) { /* ignore */ }
    if (anchor && !(anchor.x === sx && anchor.y === sy)) {
      // second tap of tap-tap selection
      var l = snapLine(anchor.x, anchor.y, sx, sy);
      var endOk = l.x0 + l.dx * (l.len - 1) === sx && l.y0 + l.dy * (l.len - 1) === sy;
      anchor = null; sel = null;
      if (endOk) { tryLine(l); drag = null; return; }
    }
    drag = { sx: sx, sy: sy, moved: false };
    sel = { x0: sx, y0: sy, dx: 0, dy: 0, len: 1 };
    game.audio.sfx('tick');
  });
  canvas.addEventListener('pointermove', function (e) {
    if (!drag || game.state !== 'playing') return;
    var p = cellXY(e);
    var l = snapLine(drag.sx, drag.sy, p.fx, p.fy);
    if (l.len > 1) drag.moved = true;
    if (!sel || l.len !== sel.len || l.dx !== sel.dx || l.dy !== sel.dy) { if (l.len > 1) game.audio.tone(500 + l.len * 40, 0.025, 'triangle', 0.05); }
    sel = l;
  });
  function endDrag() {
    if (!drag) return;
    var d = drag; drag = null;
    if (game.state !== 'playing') { sel = null; return; }
    if (sel && sel.len > 1) { tryLine(sel); sel = null; anchor = null; }
    else if (!d.moved) { anchor = { x: d.sx, y: d.sy }; sel = { x0: d.sx, y0: d.sy, dx: 0, dy: 0, len: 1 }; }
    else sel = null;
  }
  canvas.addEventListener('pointerup', endDrag);
  canvas.addEventListener('pointercancel', function () { drag = null; sel = null; });

  game.on('keydown', function (e) {
    if (game.state !== 'playing' || done) return;
    var k = e.key;
    if (k === 'ArrowLeft' || k === 'ArrowRight' || k === 'ArrowUp' || k === 'ArrowDown') {
      if (kb) {
        if (k === 'ArrowLeft') cursor.x = clampCell(cursor.x - 1); else if (k === 'ArrowRight') cursor.x = clampCell(cursor.x + 1);
        else if (k === 'ArrowUp') cursor.y = clampCell(cursor.y - 1); else cursor.y = clampCell(cursor.y + 1);
      }
      kb = true;
      if (anchor) sel = snapLine(anchor.x, anchor.y, cursor.x, cursor.y);
      return;
    }
    if (k === ' ' || k === 'Enter') {
      if (!kb) { kb = true; return; }
      if (!anchor) { anchor = { x: cursor.x, y: cursor.y }; sel = { x0: cursor.x, y0: cursor.y, dx: 0, dy: 0, len: 1 }; game.audio.sfx('tick'); }
      else { var l = snapLine(anchor.x, anchor.y, cursor.x, cursor.y); anchor = null; sel = null; tryLine(l); }
      return;
    }
    if (k === 'Backspace' || k === 'Delete') { anchor = null; sel = null; }
  });

  // ---------------------------------------------------------------- rendering
  function capsule(cells, color, alpha, width) {
    var a = cells[0], b = cells[cells.length - 1];
    var ax = (a % n + 0.5) * cell, ay = (((a / n) | 0) + 0.5) * cell, bx = (b % n + 0.5) * cell, by = (((b / n) | 0) + 0.5) * cell;
    ctx.globalAlpha = alpha; ctx.strokeStyle = color; ctx.lineCap = 'round'; ctx.lineWidth = cell * width;
    ctx.beginPath(); ctx.moveTo(ax, ay); ctx.lineTo(bx + 0.01, by); ctx.stroke(); ctx.globalAlpha = 1;
  }
  function render(dt) {
    var S = n * cell; dt = dt || 0.016;
    ctx.clearRect(0, 0, S, S);
    // subtle cell grid
    ctx.fillStyle = 'rgba(255,255,255,.025)';
    for (var gy = 0; gy < n; gy++) for (var gx = 0; gx < n; gx++) if ((gx + gy) % 2 === 0) ctx.fillRect(gx * cell, gy * cell, cell, cell);
    // found words
    words.forEach(function (w) { if (w.found) capsule(w.cells, w.color, 0.32, 0.74); });
    // flash for freshly found words
    for (var f = flashes.length - 1; f >= 0; f--) {
      var fl = flashes[f]; fl.t += dt;
      if (fl.t > 0.8) { flashes.splice(f, 1); continue; }
      ctx.shadowColor = fl.color; ctx.shadowBlur = 24;
      capsule(fl.cells, fl.color, 0.7 * (1 - fl.t / 0.8), 0.9 + fl.t * 0.3);
      ctx.shadowBlur = 0;
    }
    // active selection
    var active = sel;
    if (active && game.state === 'playing') {
      ctx.shadowColor = '#22d3ee'; ctx.shadowBlur = 16;
      capsule(lineCells(active), '#22d3ee', 0.45, 0.8);
      ctx.shadowBlur = 0;
    }
    if (kb && game.state === 'playing' && !done) {
      ctx.strokeStyle = '#fde047'; ctx.lineWidth = 2.5; ctx.beginPath();
      ctx.arc((cursor.x + 0.5) * cell, (cursor.y + 0.5) * cell, cell * 0.42, 0, 6.283); ctx.stroke();
    }
    // letters
    var inSel = {};
    if (active) lineCells(active).forEach(function (i) { inSel[i] = true; });
    var foundCell = {};
    words.forEach(function (w) { if (w.found) w.cells.forEach(function (i) { foundCell[i] = true; }); });
    ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    ctx.font = '700 ' + Math.round(cell * (LANG === 'ka' ? 0.48 : 0.52)) + 'px system-ui, -apple-system, "Segoe UI", sans-serif';
    for (var i = 0; i < grid.length; i++) {
      var x = (i % n + 0.5) * cell, y = (((i / n) | 0) + 0.5) * cell;
      ctx.fillStyle = inSel[i] ? '#ffffff' : foundCell[i] ? '#f1f5f9' : '#b9c0e4';
      ctx.fillText(grid[i], x, y + cell * 0.03);
    }
    // particles
    var st = Math.min(3, dt * 60);
    for (var p = parts.length - 1; p >= 0; p--) {
      var q = parts[p]; q.life -= dt;
      if (q.life <= 0) { parts.splice(p, 1); continue; }
      q.x += q.vx * st; q.y += q.vy * st; q.vy += 0.08 * st;
      ctx.globalAlpha = Math.min(1, q.life * 1.6); ctx.fillStyle = q.color;
      ctx.beginPath(); ctx.arc(q.x, q.y, q.size, 0, 6.283); ctx.fill();
    }
    ctx.globalAlpha = 1;
    if (game.state === 'playing' && !done) {
      var sec = Math.floor(game.elapsed() / 1000);
      if (sec !== lastSec) { lastSec = sec; game.setStat('time', fmtTime(sec)); }
    }
  }
  function fmtTime(sec) { sec = Math.max(0, Math.floor(sec)); return Math.floor(sec / 60) + ':' + ('0' + (sec % 60)).slice(-2); }

  // ---------------------------------------------------------------- flow
  function win() {
    done = true; sel = null; anchor = null;
    var sec = Math.floor(game.elapsed() / 1000);
    var bonus = Math.max(0, lvl.target - sec) * lvl.mult;
    score += bonus; game.setStat('score', score);
    var S = n * cell;
    for (var b = 0; b < 5; b++) (function (b) { setTimeout(function () { burst(S * (0.15 + b * 0.18), S * 0.4, COLORS[b], 30); }, b * 100); })(b);
    later(function () {
      game.over({ score: score, win: true, lines: [TXT.summary(words.length, fmtTime(sec)), TXT.bonusLine(bonus), TXT.theme + ': ' + themeName], evidence: { level: diff, words: words.length, sec: sec } });
      addDiffPicker();
    }, 1100);
  }
  function addDiffPicker() {
    var ov = game._overlay; if (!ov) return;
    var card = ov.querySelector('.ng-card'); var acts = card.querySelector('.ng-actions');
    var w = document.createElement('div'); w.className = 'xx-diff';
    var lab = document.createElement('div'); lab.className = 'xx-diff-label'; lab.textContent = TXT.difficulty; w.appendChild(lab);
    var row = document.createElement('div'); row.className = 'xx-diff-row'; w.appendChild(row);
    TXT.levels.forEach(function (name, i) {
      var b = document.createElement('button'); b.type = 'button'; b.className = 'xx-chip' + (i === diff ? ' on' : ''); b.textContent = name;
      b.addEventListener('click', function () {
        diff = i; game.store.set('word-hunt.diff', i); game.audio.sfx('click');
        Array.prototype.forEach.call(row.children, function (c, k) { c.classList.toggle('on', k === i); });
      });
      row.appendChild(b);
    });
    card.insertBefore(w, acts);
  }
  function setup(rng) {
    lvl = LEVELS[diff]; n = lvl.n;
    var g = generate(rng);
    grid = g.grid; words = g.words; themeName = g.theme;
    found = 0; score = 0; lastSec = -1; done = false; sel = null; anchor = null; drag = null; flashes = []; parts = [];
    cursor = { x: (n / 2) | 0, y: (n / 2) | 0 }; kb = false;
    layout(); renderList();
  }
  game.on('start', function () {
    setup(game.rng);
    canvas.classList.remove('blur'); side.classList.remove('blur');
    game.setStat('score', 0); game.setStat('words', '0/' + words.length, TXT.words); game.setStat('time', '0:00');
  });
  game.on('pause', function () { canvas.classList.add('blur'); side.classList.add('blur'); });
  game.on('resume', function () { canvas.classList.remove('blur'); side.classList.remove('blur'); });
  game.loop(function () {}, render);
  window.addEventListener('resize', layout);
  if (window.ResizeObserver) new ResizeObserver(layout).observe(game.stage);

  setup(game.mulberry32(4242));
  game.setStat('score', 0); game.setStat('words', '0/' + words.length, TXT.words); game.setStat('time', '0:00');
  game.showMenu();
  addDiffPicker();
  game.ready();
})();
