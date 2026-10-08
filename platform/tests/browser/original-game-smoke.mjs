// Smoke-test original games in a real browser: loads each game standalone, checks for runtime
// errors, presses Play, sends inputs, and confirms the SDK reported readiness.
// Usage: node tests/browser/original-game-smoke.mjs [key ...]   (serves public/ on a random port)
import { chromium } from '@playwright/test';
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve('public');
const types = { '.html': 'text/html', '.js': 'text/javascript', '.css': 'text/css', '.svg': 'image/svg+xml', '.json': 'application/json', '.png': 'image/png', '.wasm': 'application/wasm' };
const server = http.createServer((req, res) => {
  const p = path.join(root, decodeURIComponent(new URL(req.url, 'http://x').pathname));
  if (!p.startsWith(root) || !fs.existsSync(p) || fs.statSync(p).isDirectory()) { res.writeHead(404); return res.end(); }
  res.writeHead(200, { 'Content-Type': types[path.extname(p)] || 'application/octet-stream', 'Access-Control-Allow-Origin': '*' });
  fs.createReadStream(p).pipe(res);
});
await new Promise((r) => server.listen(0, '127.0.0.1', r));
const base = `http://127.0.0.1:${server.address().port}`;

const dir = path.join(root, 'games/originals');
const keys = process.argv.slice(2).length ? process.argv.slice(2)
  : fs.readdirSync(dir).filter((k) => !k.startsWith('_') && fs.existsSync(path.join(dir, k, 'index.html')));

const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/pw-browsers/chromium' });
let failed = 0;
for (const key of keys) {
  for (const vp of [{ name: 'desktop', width: 1280, height: 800, hasTouch: false }, { name: 'mobile', width: 390, height: 844, hasTouch: true, isMobile: true }]) {
    const ctx = await browser.newContext({ viewport: { width: vp.width, height: vp.height }, hasTouch: vp.hasTouch, isMobile: !!vp.isMobile });
    const page = await ctx.newPage();
    const errors = [];
    page.on('pageerror', (e) => errors.push(e.message));
    page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
    // Wrap the game in a parent page so the postMessage protocol is exercised like in production.
    await page.setContent(`<html><body style="margin:0"><iframe id="f" sandbox="allow-scripts" style="width:100vw;height:100vh;border:0" src="${base}/games/originals/${key}/index.html"></iframe>
      <script>window.msgs=[];addEventListener('message',e=>window.msgs.push(e.data));</script></body></html>`);
    const frame = await (await page.waitForSelector('#f')).contentFrame();
    try {
      await page.waitForFunction(() => window.msgs.some((m) => m && m.type === 'nebulo:ready'), null, { timeout: 8000 });
      await frame.waitForSelector('.ng-action', { timeout: 5000 });
      await frame.click('.ng-action');
      await page.waitForTimeout(300);
      for (const k of ['ArrowLeft', 'ArrowUp', 'ArrowRight', 'ArrowDown', 'Space', 'Enter']) { await frame.press('body', k).catch(() => {}); await page.waitForTimeout(80); }
      const box = await (await frame.$('#ng-stage')).boundingBox();
      for (let i = 0; i < 6; i++) {
        const x = box.x + box.width * (0.2 + 0.12 * i), y = box.y + box.height * (0.3 + 0.08 * i);
        if (vp.hasTouch) await page.touchscreen.tap(x, y); else await page.mouse.click(x, y);
        await page.waitForTimeout(60);
      }
      await page.waitForTimeout(1500);
      const state = await frame.evaluate(() => document.querySelector('.ng-overlay h2')?.textContent || 'running');
      if (errors.length) throw new Error(errors.join(' | '));
      await page.screenshot({ path: `storage/app/smoke-${key}-${vp.name}.png` });
      console.log(`PASS ${key} [${vp.name}] state=${state}`);
    } catch (e) {
      failed++;
      console.log(`FAIL ${key} [${vp.name}]: ${e.message}`);
    }
    await ctx.close();
  }
}
await browser.close();
server.close();
process.exit(failed ? 1 : 0);
