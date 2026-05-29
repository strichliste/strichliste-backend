import { chromium } from '@playwright/test';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import fs from 'node:fs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const outDir = path.resolve(__dirname, '..', 'spa-snapshot');
fs.mkdirSync(outDir, { recursive: true });

const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
const page = await ctx.newPage();

for (const hash of ['#!/split-invoice', '#/split-invoice']) {
  await page.goto('https://demo.strichliste.org/' + hash, { waitUntil: 'networkidle' });
  await page.waitForTimeout(1500);
  if (page.url().includes('split')) break;
}
console.log('landed', page.url());

await page.screenshot({ path: path.join(outDir, 'split-invoice.png'), fullPage: true });
fs.writeFileSync(path.join(outDir, 'split-invoice.body.html'),
  await page.evaluate(() => document.body.outerHTML));

const computed = await page.evaluate(() => {
  const props = ['display', 'padding', 'margin', 'background-color', 'box-shadow',
    'font-size', 'font-weight', 'text-transform', 'border-radius',
    'grid-template-columns', 'gap', 'border',
    'width', 'min-width', 'max-width', 'height',
    'flex-direction', 'flex-wrap', 'align-items', 'justify-content',
    'color', 'border-bottom'];
  const sels = ['main', '[class*="split"]', '[class*="Split"]', '[class*="invoice"]',
    '[class*="Invoice"]', '[class*="userSearch"]', '[class*="UserSearch"]',
    '[class*="input"]', '[class*="Input"]', 'input', 'select', 'button',
    'h1','h2','h3','p', 'form'];
  const out = {};
  for (const s of sels) {
    const ns = Array.from(document.querySelectorAll(s)).slice(0, 8);
    out[s] = ns.map(n => {
      const cs = getComputedStyle(n);
      const row = { tag: n.tagName.toLowerCase(), classes: n.className?.toString?.() ?? '',
                    text: (n.textContent || '').slice(0,80) };
      for (const p of props) row[p] = cs.getPropertyValue(p);
      return row;
    });
  }
  return out;
});
fs.writeFileSync(path.join(outDir, 'split-invoice.computed.json'),
  JSON.stringify(computed, null, 2));

await browser.close();
console.log('wrote', outDir, '/split-invoice.{png,body.html,computed.json}');
