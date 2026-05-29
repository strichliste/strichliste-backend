import { chromium } from '@playwright/test';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import fs from 'node:fs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const outDir = path.resolve(__dirname, '..', 'screenshots', 'review-articles-deep');
fs.mkdirSync(outDir, { recursive: true });

const LOCAL = 'http://127.0.0.1:8765';
const SPA = 'https://demo.strichliste.org';

let spaId = 1;
try {
  const spaJson = await (await fetch(`${SPA}/api/article?active=true`)).json();
  spaId = spaJson.articles?.[0]?.id ?? 1;
} catch (e) {
  console.error('spa id fetch failed', e.message);
}
const localId = 1;
console.log('local id:', localId, 'spa id:', spaId);

const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
const page = await ctx.newPage();

const shots = [
  { name: 'articles-active', local: `${LOCAL}/articles/active`, spa: `${SPA}/#/articles/active` },
  { name: 'articles-add', local: `${LOCAL}/articles/add`, spa: `${SPA}/#/articles/add` },
  { name: 'articles-edit', local: `${LOCAL}/articles/${localId}/edit`, spa: `${SPA}/#/articles/${spaId}/edit` },
];

for (const s of shots) {
  try {
    await page.goto(s.local, { waitUntil: 'networkidle', timeout: 30_000 });
    await page.waitForTimeout(600);
    await page.screenshot({ path: path.join(outDir, `${s.name}.local.png`), fullPage: true });
    console.log('wrote local', s.name);
  } catch (e) { console.error('local fail', s.local, e.message); }
  try {
    await page.goto(s.spa, { waitUntil: 'networkidle', timeout: 30_000 });
    await page.waitForTimeout(1500);
    await page.screenshot({ path: path.join(outDir, `${s.name}.spa.png`), fullPage: true });
    console.log('wrote spa', s.name);
  } catch (e) { console.error('spa fail', s.spa, e.message); }
}

// Capture computed styles for both edit pages
async function snap(url, file, sels) {
  await page.goto(url, { waitUntil: 'networkidle', timeout: 30_000 });
  await page.waitForTimeout(1200);
  const out = await page.evaluate((sels) => {
    const props = ['display','padding','padding-top','padding-bottom','padding-left','padding-right',
      'margin','margin-top','margin-bottom','margin-left','margin-right',
      'background-color','color','border','border-top','border-bottom','border-left','border-right',
      'border-radius','box-shadow','font-size','font-weight','line-height','text-transform','letter-spacing',
      'width','height','min-height','max-width','gap','grid-template-columns','justify-content','align-items',
      'text-align','flex-direction'];
    const out = {};
    for (const sel of sels) {
      const nodes = Array.from(document.querySelectorAll(sel)).slice(0, 3);
      out[sel] = nodes.map(n => {
        const cs = getComputedStyle(n);
        const r = { tag: n.tagName.toLowerCase(), classes: (n.className?.toString?.() ?? ''), text: (n.innerText||'').slice(0,40) };
        for (const p of props) r[p] = cs.getPropertyValue(p);
        return r;
      });
    }
    return out;
  }, sels);
  fs.writeFileSync(file, JSON.stringify(out, null, 2));
}

// Selectors we care about
const editSelsLocal = [
  '.article-form', '.article-form__subhead', '.article-form input[type="text"]',
  '.article-form-grid', '.form-cancel-circle', '.send-form__accept',
  '.chip-list', '.chip', '.chip__label', '.chip__remove', '.inline-add',
  '.article-form-archive', '.article-form-archive .btn', '.btn--red',
];
const listSelsLocal = [
  '.article-rows', '.article-row', '.article-row__link', '.article-row__name',
  '.article-row__price', '.tag-filter', '.tag-chip', '.tag-chip--active',
];

await snap(`${LOCAL}/articles/${localId}/edit`, path.join(outDir, 'local-edit.json'), editSelsLocal);
await snap(`${LOCAL}/articles/active`, path.join(outDir, 'local-active.json'), listSelsLocal);
await snap(`${LOCAL}/articles/add`, path.join(outDir, 'local-add.json'), editSelsLocal);

// SPA selectors — discover by scanning class list
const spaSelsDiscover = async (url) => {
  await page.goto(url, { waitUntil: 'networkidle', timeout: 30_000 });
  await page.waitForTimeout(1500);
  return await page.evaluate(() => {
    const all = Array.from(document.querySelectorAll('*'));
    const classes = new Set();
    for (const n of all) {
      const c = n.className?.toString?.() || '';
      c.split(/\s+/).filter(Boolean).forEach(x => classes.add(x));
    }
    return Array.from(classes).sort();
  });
};
const spaEditClasses = await spaSelsDiscover(`${SPA}/#/articles/${spaId}/edit`);
fs.writeFileSync(path.join(outDir, 'spa-edit-classes.json'), JSON.stringify(spaEditClasses, null, 2));
const spaActiveClasses = await spaSelsDiscover(`${SPA}/#/articles/active`);
fs.writeFileSync(path.join(outDir, 'spa-active-classes.json'), JSON.stringify(spaActiveClasses, null, 2));

// Also dump HTML of SPA edit + add + active for structure
for (const [n, url] of [
  ['spa-edit.html', `${SPA}/#/articles/${spaId}/edit`],
  ['spa-add.html', `${SPA}/#/articles/add`],
  ['spa-active.html', `${SPA}/#/articles/active`],
]) {
  await page.goto(url, { waitUntil: 'networkidle', timeout: 30_000 });
  await page.waitForTimeout(1200);
  fs.writeFileSync(path.join(outDir, n), await page.content());
}

// Now snapshot SPA computed styles by heuristics on classes
const spaSels = [
  '[class*="article-form"]','[class*="ArticleForm"]','[class*="subhead"]','[class*="Subhead"]',
  '[class*="chip"]','[class*="Chip"]','[class*="tag"]','[class*="Tag"]',
  '[class*="article-row"]','[class*="ArticleRow"]','[class*="article-list"]','[class*="ArticleList"]',
  '[class*="cancel"]','[class*="Cancel"]','[class*="accept"]','[class*="Accept"]','[class*="check"]','[class*="Check"]',
  '[class*="archive"]','[class*="Archive"]','[class*="grid"]','[class*="Grid"]',
  'input[type="text"]','input[type="number"]','form button[type="submit"]',
];
await snap(`${SPA}/#/articles/${spaId}/edit`, path.join(outDir, 'spa-edit.json'), spaSels);
await snap(`${SPA}/#/articles/active`, path.join(outDir, 'spa-active.json'), spaSels);
await snap(`${SPA}/#/articles/add`, path.join(outDir, 'spa-add.json'), spaSels);

await browser.close();
console.log('done');
