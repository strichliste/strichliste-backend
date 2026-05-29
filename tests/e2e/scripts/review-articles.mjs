import { chromium } from '@playwright/test';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import fs from 'node:fs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const outDir = path.resolve(__dirname, '..', 'screenshots', 'review-articles');
fs.mkdirSync(outDir, { recursive: true });

const LOCAL = 'http://127.0.0.1:8765';
const SPA = 'https://demo.strichliste.org';

// Find ids
const localJson = await (await fetch(`${LOCAL}/api/article?active=true`)).json();
const spaJson = await (await fetch(`${SPA}/api/article?active=true`)).json();
const localId = localJson.articles[0].id;
const spaId = spaJson.articles[0].id;
console.log('local id:', localId, 'spa id:', spaId);

const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
const page = await ctx.newPage();

const shots = [
  { name: 'articles-active', local: `${LOCAL}/articles/active`, spa: `${SPA}/#/articles/active` },
  { name: 'articles-inactive', local: `${LOCAL}/articles/inactive`, spa: `${SPA}/#/articles/inactive` },
  { name: 'articles-add', local: `${LOCAL}/articles/add`, spa: `${SPA}/#/articles/add` },
  { name: 'articles-edit', local: `${LOCAL}/articles/${localId}/edit`, spa: `${SPA}/#/articles/${spaId}/edit` },
  { name: 'articles-delete', local: `${LOCAL}/articles/${localId}/delete`, spa: null }, // SPA likely opens modal inline
];

for (const s of shots) {
  // local
  try {
    await page.goto(s.local, { waitUntil: 'networkidle', timeout: 30_000 });
    await page.waitForTimeout(600);
    const f = path.join(outDir, `${s.name}.local.png`);
    await page.screenshot({ path: f, fullPage: true });
    console.log('wrote', f);
  } catch (e) {
    console.error('local fail', s.local, e.message);
  }
  // spa
  if (s.spa) {
    try {
      await page.goto(s.spa, { waitUntil: 'networkidle', timeout: 30_000 });
      await page.waitForTimeout(1500);
      const f = path.join(outDir, `${s.name}.spa.png`);
      await page.screenshot({ path: f, fullPage: true });
      console.log('wrote', f);
    } catch (e) {
      console.error('spa fail', s.spa, e.message);
    }
  }
}

// Also capture SPA delete-confirm: navigate to edit then click delete/archive
try {
  await page.goto(`${SPA}/#/articles/${spaId}/edit`, { waitUntil: 'networkidle', timeout: 30_000 });
  await page.waitForTimeout(1200);
  // grab full HTML of edit page for SPA structure
  const html = await page.content();
  fs.writeFileSync(path.join(outDir, 'articles-edit.spa.html'), html);
  // try clicking archive/delete
  const del = page.locator('button, a').filter({ hasText: /archive|löschen|delete/i }).first();
  if (await del.count()) {
    await del.click().catch(() => {});
    await page.waitForTimeout(1000);
    await page.screenshot({ path: path.join(outDir, 'articles-delete.spa.png'), fullPage: true });
    console.log('wrote spa delete');
  }
} catch (e) {
  console.error('spa delete fail', e.message);
}

// Capture local edit HTML for structure
try {
  await page.goto(`${LOCAL}/articles/${localId}/edit`, { waitUntil: 'networkidle', timeout: 30_000 });
  await page.waitForTimeout(500);
  const html = await page.content();
  fs.writeFileSync(path.join(outDir, 'articles-edit.local.html'), html);
} catch {}

// Capture computed styles for article rows
try {
  await page.goto(`${SPA}/#/articles/active`, { waitUntil: 'networkidle', timeout: 30_000 });
  await page.waitForTimeout(1500);
  const computed = await page.evaluate(() => {
    const props = ['display', 'padding', 'margin', 'background-color', 'border', 'border-bottom',
                   'font-size', 'font-weight', 'justify-content', 'align-items', 'gap',
                   'border-radius', 'color'];
    const selectors = ['[class*="article-list"]', '[class*="ArticleList"]', '[class*="tag"]',
                       '[class*="Tag"]', '[class*="fab"]', '[class*="Fab"]', '[class*="tab"]',
                       '[class*="pagination"]', '[class*="Pagination"]'];
    const out = {};
    for (const sel of selectors) {
      const nodes = Array.from(document.querySelectorAll(sel)).slice(0, 4);
      out[sel] = nodes.map((n) => {
        const cs = getComputedStyle(n);
        const r = { tag: n.tagName.toLowerCase(), classes: n.className?.toString?.() ?? '' };
        for (const p of props) r[p] = cs.getPropertyValue(p);
        return r;
      });
    }
    return out;
  });
  fs.writeFileSync(path.join(outDir, 'spa-active.computed.json'), JSON.stringify(computed, null, 2));
} catch (e) { console.error('computed fail', e.message); }

await browser.close();
console.log('done');
