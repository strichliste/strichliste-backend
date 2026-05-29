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

// SPA route for buy article on user 1
await page.goto('https://demo.strichliste.org/#/user/1', { waitUntil: 'networkidle', timeout: 30_000 });
await page.waitForTimeout(1500);
// Click BUY ARTICLE tab
const buyTab = page.getByText(/buy article/i).first();
if (await buyTab.count()) {
  await buyTab.click();
  await page.waitForTimeout(1500);
}
await page.screenshot({ path: path.join(outDir, 'user-detail-buy.png'), fullPage: true });
const html = await page.content();
fs.writeFileSync(path.join(outDir, 'user-detail-buy.html'), html);

// Also dump computed styles of the article-list-item / modal / buy-input
const computed = await page.evaluate(() => {
  const props = ['display', 'padding', 'margin', 'background-color', 'box-shadow',
                 'font-size', 'font-weight', 'text-transform', 'border-radius',
                 'grid-template-columns', 'grid-template-rows', 'gap', 'border',
                 'width', 'height', 'position'];
  const selectors = ['[class*="modal"]', '[class*="ArticleListItem"]',
                     '[class*="articleScanner"]', '[class*="ArticleScanner"]',
                     '[class*="ArticleSelection"]', '[class*="ArticleTransaction"]',
                     '[class*="article-list"]', '[class*="input"]',
                     '[class*="UserArticleTransaction"]'];
  const out = {};
  for (const sel of selectors) {
    const nodes = Array.from(document.querySelectorAll(sel)).slice(0, 3);
    out[sel] = nodes.map((n) => {
      const cs = getComputedStyle(n);
      const row = { tag: n.tagName.toLowerCase(), classes: n.className?.toString?.() ?? '' };
      for (const p of props) row[p] = cs.getPropertyValue(p);
      return row;
    });
  }
  return out;
});
fs.writeFileSync(path.join(outDir, 'user-detail-buy.computed.json'), JSON.stringify(computed, null, 2));

await browser.close();
console.log('wrote', outDir, '/user-detail-buy.{png,html,computed.json}');
