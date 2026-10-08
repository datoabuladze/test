# Nebulo original games – authoring guide

Each game lives in `public/games/originals/<key>/`:

| File | Purpose |
|---|---|
| `index.html` | Entry document. Loads `../_sdk/sdk.css`, `../_sdk/sdk.js`, then `game.js`. Classic scripts only (no ES modules – frames run with an opaque origin, modules would need CORS). |
| `game.js` | The game, wrapped in an IIFE, `'use strict'`. No external network requests, no CDNs, no fonts from the web. |
| `thumb.svg` | Original 640×480 cover art (vector, hand-authored). No copyrighted characters/logos. |
| `meta.json` | Catalog metadata in en/ka/tr/ru (see `merge-orbit/meta.json` for the exact schema). |

## SDK cheat sheet (`window.NebuloGame`)

```js
var game = NebuloGame.create({ id: 'snake', title: 'Neon Snake', tagline: '…', howTo: ['…', '…'], pausable: true, lowerIsBetter: false });
game.canvas(w, h)        // → view {canvas, ctx, w, h, scale, toLogical(clientX, clientY), fit()}; draw in logical units
game.loop(update, render)// fixed 60 Hz update(dt) while state === 'playing'; render(dt) every frame
game.on('start'|'stop'|'pause'|'resume'|'keydown'|'keyup'|'resize'|'session', fn)
game.keys[key]           // held keys (also set by touch buttons)
game.touchButtons([{key:'ArrowLeft', label:'◀'}], 'left'|'right'|'center', alwaysShow)
game.onSwipe(element, dir => …)
game.setStat('score', n) // HUD stat; 'score' is also reported to the page live
game.over({score, win, evidence, lines, formatScore}) // shows game-over card, saves best, reports score
game.overlay({title, big, lines, list, actions:[{label, onClick}]}) / game.closeOverlay()
game.showMenu()          // start card with tagline + how-to list + Play button
game.rng()               // seeded PRNG for the current round (use instead of Math.random for gameplay)
game.elapsed()           // active ms (excludes pauses)
game.audio.sfx('click'|'move'|'point'|'bonus'|'hit'|'explode'|'jump'|'lose'|'win'|'tick'), game.audio.tone(freq, dur, type, vol, slideTo)
game.store.get(k, d) / game.store.set(k, v)  // safe storage
game.t('score'|'level'|'time'|'moves'|'lives'|…), NebuloGame.lang ('en'|'ka'|'tr'|'ru')
game.ready()             // call once after the menu is shown
```

States: `menu → playing ⇄ paused → over`. `P`/`Esc` pause, the HUD has pause/restart/mute buttons.
Always: call `game.showMenu(); game.ready();` at the end of setup; reset all round state in `game.on('start')`.
