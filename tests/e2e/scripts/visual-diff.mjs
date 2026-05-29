#!/usr/bin/env node
// Side-by-side visual capture: local Twig vs demo.strichliste.org SPA.
// Usage: node tests/e2e/scripts/visual-diff.mjs [iteration-tag]
// Writes PNGs into tests/e2e/screenshots/<tag>/.

import { chromium } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

const TAG = process.argv[2] ?? 'baseline';
const OUT_DIR = path.resolve(new URL('.', import.meta.url).pathname, '..', 'screenshots', TAG);
fs.mkdirSync(OUT_DIR, { recursive: true });

const LOCAL = process.env.LOCAL_URL ?? 'http://127.0.0.1:8765';
const DEMO = 'https://demo.strichliste.org';

// SPA uses hash routes; Twig uses real paths. Map by intent.
const ROUTES = [
  { name: 'home',            local: '/user/active',          demo: '/#/user/active' },
  { name: 'user-inactive',   local: '/user/inactive',        demo: '/#/user/inactive' },
  { name: 'user-detail',     local: '/user/1',               demo: '/#/user/1' },
  { name: 'articles-active', local: '/articles/active',      demo: '/#/articles/active' },
  { name: 'metrics',         local: '/metrics',              demo: '/#/metrics' },
  { name: 'split-invoice',   local: '/split-invoice',        demo: '/#/split-invoice' },
];

async function shoot(browser, base, route, file) {
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const page = await ctx.newPage();
  try {
    await page.goto(base + route, { waitUntil: 'networkidle', timeout: 20_000 });
    // Give SPAs a moment to settle after route swap.
    await page.waitForTimeout(750);
    await page.screenshot({ path: file, fullPage: false });
  } catch (e) {
    console.error(`fail ${base + route}: ${e.message}`);
  } finally {
    await ctx.close();
  }
}

const browser = await chromium.launch();
for (const r of ROUTES) {
  const localFile = path.join(OUT_DIR, `${r.name}.local.png`);
  const demoFile = path.join(OUT_DIR, `${r.name}.demo.png`);
  await Promise.all([
    shoot(browser, LOCAL, r.local, localFile),
    shoot(browser, DEMO, r.demo, demoFile),
  ]);
  console.log(`  ${r.name}`);
}
await browser.close();
console.log(`done → ${OUT_DIR}`);
