import { chromium } from '@playwright/test';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import fs from 'node:fs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const outDir = path.resolve(__dirname, '..', 'screenshots', 'review-user-deep');
fs.mkdirSync(outDir, { recursive: true });

// Pick a local user that has some transactions (not balance=0 and not freshly created).
const res = await fetch('http://127.0.0.1:8765/api/user?active=true');
const json = await res.json();
const candidates = (json.users || []).filter(u => u.balance !== 0);
const local = candidates[0] || json.users?.[0];
const userId = local?.id;
if (!userId) {
  console.error('No active user found');
  process.exit(1);
}
console.log('Local userId', userId, '(' + local.name + ', balance=' + local.balance + ')');

// SPA: pick a user with healthy balance and history.
let spaUserId = 156;
try {
  const r2 = await fetch('https://demo.strichliste.org/api/user?active=true');
  if (r2.ok) {
    const j2 = await r2.json();
    const spaCand = (j2.users || []).find(u => u.balance > 100) || j2.users?.[0];
    spaUserId = spaCand?.id || 156;
  }
} catch (e) {
  console.warn('SPA user fetch failed', e.message);
}
console.log('SPA userId', spaUserId);

const routes = [
  { tag: '1-default',     local: `/user/${userId}`,                spa: `/#/user/${spaUserId}` },
  { tag: '2-send',        local: `/user/${userId}?tab=send`,       spa: `/#/user/${spaUserId}/sendMoney` },
  { tag: '3-buy',         local: `/user/${userId}?tab=buy`,        spa: `/#/user/${spaUserId}/buyProduct` },
  { tag: '4-edit',        local: `/user/${userId}?tab=edit`,       spa: `/#/user/${spaUserId}/edit` },
  { tag: '5-transactions',local: `/user/${userId}/transactions`,   spa: `/#/user/${spaUserId}` },
  { tag: '6-metrics',     local: `/user/${userId}/metrics`,        spa: `/#/user/${spaUserId}` },
];

const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, deviceScaleFactor: 1 });
const page = await ctx.newPage();

for (const r of routes) {
  const localUrl = `http://127.0.0.1:8765${r.local}`;
  console.log('local ->', localUrl);
  try {
    await page.goto(localUrl, { waitUntil: 'networkidle', timeout: 20_000 });
    await page.waitForTimeout(300);
    await page.screenshot({ path: path.join(outDir, `${r.tag}.local.png`), fullPage: true });
  } catch (e) {
    console.warn('Local capture failed for', r.tag, e.message);
  }

  const spaUrl = `https://demo.strichliste.org${r.spa}`;
  console.log('spa   ->', spaUrl);
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
