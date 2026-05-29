import { chromium } from '@playwright/test';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import fs from 'node:fs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const outDir = path.resolve(__dirname, '..', 'screenshots', 'final-pass');
fs.mkdirSync(outDir, { recursive: true });

const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
const page = await ctx.newPage();

const pages = [
  ['home',       'http://127.0.0.1:8765/user/active'],
  ['articles',   'http://127.0.0.1:8765/articles/active'],
  ['art-add',    'http://127.0.0.1:8765/articles/add'],
  ['art-edit',   'http://127.0.0.1:8765/articles/1/edit'],
  ['split',      'http://127.0.0.1:8765/split-invoice'],
  ['metrics',    'http://127.0.0.1:8765/metrics'],
  ['user-det',   'http://127.0.0.1:8765/user/12'],
  ['user-send',  'http://127.0.0.1:8765/user/12?tab=send'],
  ['user-buy',   'http://127.0.0.1:8765/user/12?tab=buy'],
  ['user-edit',  'http://127.0.0.1:8765/user/12?tab=edit'],
  ['user-tx',    'http://127.0.0.1:8765/user/12/transactions'],
  ['user-met',   'http://127.0.0.1:8765/user/12/metrics'],
];

for (const [name, url] of pages) {
  try {
    await page.goto(url, { waitUntil: 'networkidle', timeout: 20000 });
    await page.waitForTimeout(400);
    await page.screenshot({ path: path.join(outDir, `${name}.png`), fullPage: true });
    console.log(`captured ${name}`);
  } catch (e) {
    console.log(`failed ${name}: ${e.message}`);
  }
}

await browser.close();
console.log('done →', outDir);
