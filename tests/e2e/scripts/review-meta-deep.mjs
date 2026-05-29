import { chromium } from '@playwright/test';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import fs from 'node:fs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const outDir = path.resolve(__dirname, '..', 'screenshots', 'review-meta-deep');
fs.mkdirSync(outDir, { recursive: true });

// User ids for /user/:id/metrics
const localUserId = 12;
let spaUserId = localUserId;
try {
  const r2 = await fetch('https://demo.strichliste.org/api/user?active=true');
  if (r2.ok) {
    const j2 = await r2.json();
    spaUserId = j2.users?.[0]?.id || localUserId;
  }
} catch (e) {
  console.warn('SPA user-id fetch failed; using local id', e.message);
}
console.log('local userId', localUserId, 'spa userId', spaUserId);

const lightRoutes = [
  { tag: 'metrics-global', local: '/metrics', spa: '/#/metrics' },
  { tag: 'metrics-user',   local: `/user/${localUserId}/metrics`, spa: `/#/user/${spaUserId}/metrics` },
  { tag: 'home',           local: '/user/active', spa: '/#/user/active' },
  { tag: 'user-detail',    local: `/user/${localUserId}`, spa: `/#/user/${spaUserId}` },
  { tag: 'user-edit',      local: `/user/${localUserId}/edit`, spa: null },
  { tag: 'articles',       local: '/article', spa: '/#/article' },
  { tag: 'article-create', local: '/article/create', spa: null },
];

async function captureCtx(ctx, suffix, routes) {
  const page = await ctx.newPage();
  for (const r of routes) {
    const localUrl = `http://127.0.0.1:8765${r.local}`;
    try {
      console.log('local', suffix, '->', localUrl);
      await page.goto(localUrl, { waitUntil: 'networkidle', timeout: 30_000 });
      await page.waitForTimeout(800);
      await page.screenshot({ path: path.join(outDir, `${r.tag}.local.${suffix}.png`), fullPage: true });
    } catch (e) {
      console.warn('local fail', r.tag, e.message);
    }
    if (r.spa) {
      const spaUrl = `https://demo.strichliste.org${r.spa}`;
      try {
        console.log('spa', suffix, '->', spaUrl);
        await page.goto(spaUrl, { waitUntil: 'networkidle', timeout: 30_000 });
        await page.waitForTimeout(2000);
        await page.screenshot({ path: path.join(outDir, `${r.tag}.spa.${suffix}.png`), fullPage: true });
      } catch (e) {
        console.warn('spa fail', r.tag, e.message);
      }
    }
  }
  // Focus capture on home page: focus the search input + skip link
  try {
    await page.goto('http://127.0.0.1:8765/user/active', { waitUntil: 'networkidle' });
    await page.evaluate(() => {
      const input = document.querySelector('.app-header__search input');
      if (input) input.focus();
    });
    await page.waitForTimeout(150);
    const header = await page.locator('.app-header').first();
    if (await header.count()) {
      await header.screenshot({ path: path.join(outDir, `focus-search.local.${suffix}.png`) });
    }
    // Skip link: tab once to force focus
    await page.goto('http://127.0.0.1:8765/user/active', { waitUntil: 'networkidle' });
    await page.keyboard.press('Tab');
    await page.waitForTimeout(150);
    await page.screenshot({
      path: path.join(outDir, `focus-skiplink.local.${suffix}.png`),
      clip: { x: 0, y: 0, width: 600, height: 120 },
    });
  } catch (e) {
    console.warn('focus capture fail', e.message);
  }
  await page.close();
}

const browser = await chromium.launch();

// Light
const ctxLight = await browser.newContext({
  viewport: { width: 1280, height: 900 },
  deviceScaleFactor: 1,
  colorScheme: 'light',
});
await captureCtx(ctxLight, 'light', lightRoutes);
await ctxLight.close();

// Dark: home + user-detail
const ctxDark = await browser.newContext({
  viewport: { width: 1280, height: 900 },
  deviceScaleFactor: 1,
  colorScheme: 'dark',
});
const darkRoutes = [
  { tag: 'home',        local: '/user/active', spa: '/#/user/active' },
  { tag: 'user-detail', local: `/user/${localUserId}`, spa: `/#/user/${spaUserId}` },
];
await captureCtx(ctxDark, 'dark', darkRoutes);
await ctxDark.close();

await browser.close();
console.log('Done. Output:', outDir);
