import { chromium } from '@playwright/test';
const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
const page = await ctx.newPage();
await page.goto('http://127.0.0.1:8765/split-invoice', { waitUntil: 'networkidle' });
await page.waitForTimeout(800);
await page.screenshot({ path: 'tests/e2e/screenshots/final-pass/split-v2.png', fullPage: true });
await browser.close();
console.log('done');
