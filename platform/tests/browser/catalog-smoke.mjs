// Browser launch test for catalog games, driven by `php artisan games:smoke`.
// Loads each game's real frame URL inside an iframe with the production sandbox
// attributes, waits for the platform protocol's ready message (or the load event for
// third-party embeds), then watches for runtime errors for a few seconds.
// Usage: node tests/browser/catalog-smoke.mjs <games.json>   -> prints one JSON line of results.
import { chromium } from '@playwright/test';
import fs from 'node:fs';

const input = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));
const { appUrl, sandbox, allow, games, timeoutMs = 20000, settleMs = 2500 } = input;
const MANAGED = ['original', 'html5', 'phaser', 'unity', 'ruffle'];

const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/pw-browsers/chromium' });
const results = [];
for (const g of games) {
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 800 } });
  const page = await ctx.newPage();
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message));
  page.on('console', (m) => { if (m.type() === 'error' && !/favicon/.test(m.location()?.url || '')) errors.push(m.text() + ' ' + (m.location()?.url || '')); });
  const started = Date.now();
  try {
    // The parent page must be on the app origin, like the real game page (frames use frame-ancestors 'self').
    await page.goto(appUrl + '/robots.txt');
    await page.setContent(`<html><body style="margin:0;background:#000">
      <iframe id="f" sandbox="${sandbox}" allow="${allow}" style="width:100vw;height:100vh;border:0"></iframe>
      <script>window.msgs=[];window.loaded=false;addEventListener('message',e=>window.msgs.push(e.data));
      const f=document.getElementById('f');f.addEventListener('load',()=>{window.loaded=true});f.src=${JSON.stringify(g.frame_url)};</script></body></html>`);
    if (MANAGED.includes(g.engine)) {
      await page.waitForFunction(() => window.msgs.some((m) => m && (m.type === 'nebulo:ready' || m.type === 'nebulo:error')), null, { timeout: timeoutMs });
      const err = await page.evaluate(() => window.msgs.find((m) => m && m.type === 'nebulo:error'));
      if (err) throw new Error('Game reported error: ' + String(err.message || '').slice(0, 200));
    } else {
      await page.waitForFunction(() => window.loaded, null, { timeout: timeoutMs });
    }
    await page.waitForTimeout(settleMs);
    const relevant = errors.filter((e) => !/favicon|ERR_BLOCKED_BY_CLIENT/i.test(e));
    if (relevant.length) throw new Error('Runtime errors: ' + relevant.slice(0, 3).join(' | ').slice(0, 400));
    results.push({ id: g.id, ok: true, ms: Date.now() - started, message: 'Loaded in a sandboxed frame without runtime errors.' });
  } catch (e) {
    results.push({ id: g.id, ok: false, ms: Date.now() - started, message: String(e.message || e).split('\n')[0].slice(0, 400) });
  }
  await ctx.close();
}
await browser.close();
console.log(JSON.stringify(results));
