#!/usr/bin/env node
/*
 * Scrapes demo.strichliste.org for visual-parity work:
 *   - downloads every <link rel="stylesheet"> referenced from each route
 *   - saves the rendered DOM of each route (after the SPA hydrates)
 *   - dumps computed styles of key selectors for each route
 *   - takes a full-page PNG of each route
 *
 * Output: tests/e2e/spa-snapshot/
 *   ├── css/<bundle>.css        (deduped by URL)
 *   ├── <route>.html
 *   ├── <route>.computed.json
 *   └── <route>.png
 */
import { chromium } from '@playwright/test';
import path from 'node:path';
import fs from 'node:fs';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const OUT = path.resolve(__dirname, '..', 'spa-snapshot');
const CSS_OUT = path.join(OUT, 'css');
fs.mkdirSync(CSS_OUT, { recursive: true });

const DEMO = 'https://demo.strichliste.org';
const ROUTES = [
  { name: 'home', hash: '#/user/active' },
  { name: 'user-inactive', hash: '#/user/inactive' },
  { name: 'user-detail', hash: '#/user/1' },
  { name: 'articles-active', hash: '#/articles/active' },
  { name: 'articles-inactive', hash: '#/articles/inactive' },
  { name: 'metrics', hash: '#/metrics' },
  { name: 'split-invoice', hash: '#/split-invoice' },
  { name: 'search-results', hash: '#/search-results' },
];

// Selectors whose computed styles we want, per route.
const COMPUTED_KEYS = [
  'html', 'body',
  'header', '[class*="HeaderMenu"]', '[class*="Brand"]', '[class*="NavTab"]',
  'main',
  '[class*="TabMenu"]', '[class*="TabMenuItem"]',
  '[class*="UserCard"]', '[class*="ArticleCard"]', '[class*="ArticleListItem"]',
  '[class*="UserDetails"]', '[class*="UserDetailsHeader"]',
  '[class*="Balance"]', '[class*="AlertText"]',
  '[class*="Button"]', '[class*="PaymentButton"]', '[class*="StepButton"]',
  '[class*="Input"]', '[class*="SearchInput"]',
  '[class*="Card"]', '[class*="Modal"]',
  '[class*="TransactionList"]', '[class*="TransactionListItem"]',
  '[class*="Splitinvoice"], [class*="SplitInvoice"]',
  '[class*="Metrics"]',
  '[class*="Tag"]',
  '[class*="Footer"]',
];

const COMPUTED_PROPS = [
  'background-color', 'color', 'border', 'border-radius', 'box-shadow',
  'padding', 'margin', 'gap',
  'font-family', 'font-size', 'font-weight', 'line-height', 'letter-spacing', 'text-transform',
  'display', 'flex-direction', 'justify-content', 'align-items', 'grid-template-columns', 'grid-gap', 'gap',
  'width', 'height', 'min-width', 'max-width',
  'opacity', 'transition',
];

const browser = await chromium.launch();
const ctx = await browser.newContext({
  viewport: { width: 1280, height: 900 },
  userAgent: 'Mozilla/5.0 strichliste-css-parity-scraper',
});
const page = await ctx.newPage();

// Capture every CSS bundle as it loads.
const cssUrls = new Set();
page.on('response', async (resp) => {
  const url = resp.url();
  const ct = resp.headers()['content-type'] || '';
  if (ct.includes('text/css') || url.endsWith('.css')) {
    cssUrls.add(url);
  }
});

for (const route of ROUTES) {
  const fullUrl = DEMO + '/' + route.hash;
  console.log(`→ ${route.name} ${fullUrl}`);
  await page.goto(fullUrl, { waitUntil: 'networkidle', timeout: 30_000 });
  await page.waitForTimeout(1500); // SPA hydration
  // HTML
  const html = await page.content();
  fs.writeFileSync(path.join(OUT, `${route.name}.html`), html);

  // PNG
  await page.screenshot({ path: path.join(OUT, `${route.name}.png`), fullPage: true });

  // Computed styles
  const computed = await page.evaluate((args) => {
    const { selectors, props } = args;
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
  }, { selectors: COMPUTED_KEYS, props: COMPUTED_PROPS });
  fs.writeFileSync(path.join(OUT, `${route.name}.computed.json`), JSON.stringify(computed, null, 2));
}

// Download every captured CSS bundle.
for (const url of cssUrls) {
  try {
    const resp = await page.request.get(url);
    if (!resp.ok()) continue;
    const body = await resp.text();
    const safeName = url.replace(/^https?:\/\//, '').replace(/[^a-z0-9.\-_]/gi, '_');
    fs.writeFileSync(path.join(CSS_OUT, safeName), body);
    console.log(`  css ${safeName} (${body.length}b)`);
  } catch (e) {
    console.log(`  fail ${url}: ${e.message}`);
  }
}

await browser.close();
console.log(`\ndone → ${OUT}`);
console.log(`  stylesheets: ${cssUrls.size}`);
