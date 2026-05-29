import { chromium } from '@playwright/test';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import fs from 'node:fs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const outDir = path.resolve(__dirname, '..', 'screenshots', 'review-metrics');
fs.mkdirSync(outDir, { recursive: true });

// Pick an active user id for the per-user metrics page.
const localRes = await fetch('http://127.0.0.1:8765/api/user?active=true');
const localJson = await localRes.json();
const localUserId = localJson.users?.[0]?.id;
if (!localUserId) {
  console.error('No active local user found');
  process.exit(1);
}

let spaUserId = localUserId;
try {
  const r2 = await fetch('https://demo.strichliste.org/api/user?active=true');
  if (r2.ok) {
    const j2 = await r2.json();
    spaUserId = j2.users?.[0]?.id || localUserId;
  }
} catch (e) {
  console.warn('SPA user-id fetch failed; falling back', e.message);
}
console.log('local userId', localUserId, 'spa userId', spaUserId);

const routes = [
  { tag: 'metrics',      local: '/metrics',                       spa: '/#/metrics' },
  { tag: 'user-metrics', local: `/user/${localUserId}/metrics`,   spa: `/#/user/${spaUserId}/metrics` },
];

const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, deviceScaleFactor: 1 });
const page = await ctx.newPage();

for (const r of routes) {
  const localUrl = `http://127.0.0.1:8765${r.local}`;
  console.log('local ->', localUrl);
  await page.goto(localUrl, { waitUntil: 'networkidle' });
  await page.waitForTimeout(800); // chart animation
  await page.screenshot({ path: path.join(outDir, `${r.tag}.local.png`), fullPage: true });

  const spaUrl = `https://demo.strichliste.org${r.spa}`;
  console.log('spa ->', spaUrl);
  try {
    await page.goto(spaUrl, { waitUntil: 'networkidle', timeout: 30_000 });
    await page.waitForTimeout(2000); // recharts render
    await page.screenshot({ path: path.join(outDir, `${r.tag}.spa.png`), fullPage: true });
  } catch (e) {
    console.warn('SPA capture failed for', r.tag, e.message);
  }
}

await browser.close();
console.log('Done. Output:', outDir);
