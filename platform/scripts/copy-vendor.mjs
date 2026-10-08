// Copies self-hosted runtime assets that must be served as static files (not bundled by Vite).
// Ruffle loads its .wasm and chunks relative to ruffle.js, so the whole package dir is copied.
import fs from 'node:fs';
import path from 'node:path';

const src = path.resolve('node_modules/@ruffle-rs/ruffle');
const dest = path.resolve('public/vendor/ruffle');
fs.rmSync(dest, { recursive: true, force: true });
fs.mkdirSync(dest, { recursive: true });
for (const f of fs.readdirSync(src)) {
  if (f.endsWith('.map') || f === 'node_modules') continue;
  fs.cpSync(path.join(src, f), path.join(dest, f), { recursive: true });
}
console.log('Copied Ruffle to public/vendor/ruffle');
