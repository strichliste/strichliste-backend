import { chromium } from '@playwright/test';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import fs from 'node:fs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const outDir = path.resolve(__dirname, '..', 'screenshots', 'review-userpages');
fs.mkdirSync(outDir, { recursive: true });

// Discover an active user id via the local API.
const res = await fetch('http://127.0.0.1:8765/api/user?active=true');
const json = await res.json();
const userId = json.users?.[0]?.id;
if (!userId) {
  console.error('No active user found');
  process.exit(1);
}
console.log('Using userId', userId);

// Find an SPA user id too (use first active from demo SPA API).
let spaUserId = userId;
try {
  const r2 = await fetch('https://demo.strichliste.org/api/user?active=true');
  if (r2.ok) {
    const j2 = await r2.json();
    spaUserId = j2.users?.[0]?.id || userId;
  }
} catch (e) {
  console.warn('Could not fetch SPA user id, falling back to local id', e.message);
}
console.log('SPA userId', spaUserId);

const routes = [
  { tag: 'user-active',        local: '/user/active',                 spa: '/#/user/active' },
  { tag: 'user-inactive',      local: '/user/inactive',               spa: '/#/user/inactive' },
  { tag: 'user-detail',        local: `/user/${userId}`,              spa: `/#/user/${spaUserId}` },
  { tag: 'user-detail-send',   local: `/user/${userId}?tab=send`,     spa: `/#/user/${spaUserId}/sendMoney` },
  { tag: 'user-detail-buy',    local: `/user/${userId}?tab=buy`,      spa: `/#/user/${spaUserId}/buyProduct` },
  { tag: 'user-detail-edit',   local: `/user/${userId}?tab=edit`,     spa: `/#/user/${spaUserId}/edit` },
];

const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, deviceScaleFactor: 1 });
const page = await ctx.newPage();

for (const r of routes) {
  // Local
  const localUrl = `http://127.0.0.1:8765${r.local}`;
  console.log('local ->', localUrl);
  await page.goto(localUrl, { waitUntil: 'networkidle' });
  await page.waitForTimeout(400);
  await page.screenshot({ path: path.join(outDir, `${r.tag}.local.png`), fullPage: true });

  // SPA
  const spaUrl = `https://demo.strichliste.org${r.spa}`;
  console.log('spa ->', spaUrl);
  try {
    await page.goto(spaUrl, { waitUntil: 'networkidle', timeout: 30_000 });
    await page.waitForTimeout(1500);
    await page.screenshot({ path: path.join(outDir, `${r.tag}.spa.png`), fullPage: true });
  } catch (e) {
    console.warn('SPA capture failed for', r.tag, e.message);
  }
}

await browser.close();
console.log('Done. Output:', outDir);
