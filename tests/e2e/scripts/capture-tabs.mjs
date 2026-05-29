import { chromium } from '@playwright/test';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import fs from 'node:fs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const outDir = path.resolve(__dirname, '..', 'screenshots', 'restructure');
fs.mkdirSync(outDir, { recursive: true });

const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
const page = await ctx.newPage();
for (const tab of ['send', 'edit', 'buy']) {
  await page.goto(`http://127.0.0.1:8765/user/1?tab=${tab}`);
  await page.waitForTimeout(700);
  const file = path.join(outDir, `user-detail-${tab}.local.png`);
  await page.screenshot({ path: file });
  console.log('wrote', file);
}
await browser.close();
