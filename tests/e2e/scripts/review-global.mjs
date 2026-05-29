import { chromium } from '@playwright/test';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import fs from 'node:fs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const outDir = path.resolve(__dirname, '..', 'screenshots', 'review-global');
fs.mkdirSync(outDir, { recursive: true });

// Discover an active user id via the local API.
const res = await fetch('http://127.0.0.1:8765/api/user?active=true');
const json = await res.json();
const userId = json.users?.[0]?.id;
if (!userId) {
  console.error('No active user found');
  process.exit(1);
}

// SPA user id
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

const routes = [
  { tag: 'home',          local: '/',                       spa: '/' },
  { tag: 'user-detail',   local: `/user/${userId}`,         spa: `/#/user/${spaUserId}` },
  { tag: 'articles',      local: '/articles',               spa: '/#/article' },
  { tag: 'metrics',       local: '/metrics',                spa: '/#/global-statistics' },
  { tag: 'split-invoice', local: '/split-invoice',          spa: '/#/split-invoice' },
];

const browser = await chromium.launch();

async function capture(colorScheme, suffix) {
  const ctx = await browser.newContext({
    viewport: { width: 1280, height: 900 },
    deviceScaleFactor: 1,
    colorScheme,
  });
  const page = await ctx.newPage();
  for (const r of routes) {
    const localUrl = `http://127.0.0.1:8765${r.local}`;
    console.log(`[${suffix}] local ->`, localUrl);
    try {
      await page.goto(localUrl, { waitUntil: 'networkidle', timeout: 30_000 });
      await page.waitForTimeout(400);
      await page.screenshot({ path: path.join(outDir, `${r.tag}.local.${suffix}.png`), fullPage: true });
    } catch (e) {
      console.warn('local capture failed for', r.tag, e.message);
    }

    const spaUrl = `https://demo.strichliste.org${r.spa}`;
    console.log(`[${suffix}] spa   ->`, spaUrl);
    try {
      await page.goto(spaUrl, { waitUntil: 'networkidle', timeout: 30_000 });
      await page.waitForTimeout(1500);
      await page.screenshot({ path: path.join(outDir, `${r.tag}.spa.${suffix}.png`), fullPage: true });
    } catch (e) {
      console.warn('spa capture failed for', r.tag, e.message);
    }
  }

  // Header crops on home for both
  try {
    await page.goto('http://127.0.0.1:8765/', { waitUntil: 'networkidle' });
    await page.waitForTimeout(300);
    await page.screenshot({
      path: path.join(outDir, `header.local.${suffix}.png`),
      clip: { x: 0, y: 0, width: 1280, height: 80 },
    });
  } catch {}
  try {
    await page.goto('https://demo.strichliste.org/', { waitUntil: 'networkidle' });
    await page.waitForTimeout(800);
    await page.screenshot({
      path: path.join(outDir, `header.spa.${suffix}.png`),
      clip: { x: 0, y: 0, width: 1280, height: 80 },
    });
  } catch {}

  // Focus state — tab into local skip link
  try {
    await page.goto('http://127.0.0.1:8765/', { waitUntil: 'networkidle' });
    await page.waitForTimeout(300);
    await page.keyboard.press('Tab');
    await page.waitForTimeout(150);
    await page.screenshot({
      path: path.join(outDir, `focus-skiplink.local.${suffix}.png`),
      clip: { x: 0, y: 0, width: 400, height: 100 },
    });
    // Focus the search input next
    await page.click('#hsearch');
    await page.waitForTimeout(150);
    await page.screenshot({
      path: path.join(outDir, `focus-search.local.${suffix}.png`),
      clip: { x: 0, y: 0, width: 1280, height: 80 },
    });
  } catch {}

  await ctx.close();
}

await capture('light', 'light');
await capture('dark',  'dark');

await browser.close();
console.log('Done. Output:', outDir);
