import { chromium } from '@playwright/test';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import fs from 'node:fs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const outDir = path.resolve(__dirname, '..', 'screenshots', 'review-split-deep');
fs.mkdirSync(outDir, { recursive: true });

const LOCAL = 'http://127.0.0.1:8765/split-invoice';
const SPA   = 'https://demo.strichliste.org/#!/split-invoice';

const PROPS = [
  'display','padding','padding-left','padding-right','padding-top','padding-bottom',
  'margin','margin-top','margin-bottom','margin-left','margin-right',
  'font-size','font-weight','font-family','text-transform','letter-spacing','text-align',
  'max-width','min-width','width','height',
  'grid-template-columns','grid-template-rows','gap','column-gap','row-gap',
  'color','background-color',
  'border','border-radius','border-color','border-style','border-width',
  'box-shadow',
  'flex-direction','align-items','justify-content',
];

function pickComputed(selectors) {
  const out = {};
  for (const sel of selectors) {
    const nodes = Array.from(document.querySelectorAll(sel)).slice(0, 6);
    out[sel] = nodes.map((n, i) => {
      const cs = getComputedStyle(n);
      const row = {
        idx: i,
        tag: n.tagName.toLowerCase(),
        classes: (n.className && n.className.toString ? n.className.toString() : '') || '',
        id: n.id || '',
        text: (n.textContent || '').trim().slice(0, 60),
      };
      for (const p of PROPS) row[p] = cs.getPropertyValue(p);
      return row;
    });
  }
  return out;
}

async function dumpComputed(page, label, selectors) {
  const data = await page.evaluate((sels) => {
    const PROPS = [
      'display','padding','padding-left','padding-right','padding-top','padding-bottom',
      'margin','margin-top','margin-bottom','margin-left','margin-right',
      'font-size','font-weight','font-family','text-transform','letter-spacing','text-align',
      'max-width','min-width','width','height',
      'grid-template-columns','grid-template-rows','gap','column-gap','row-gap',
      'color','background-color',
      'border','border-radius','border-color','border-style','border-width',
      'box-shadow',
      'flex-direction','align-items','justify-content',
    ];
    const out = {};
    for (const sel of sels) {
      const nodes = Array.from(document.querySelectorAll(sel)).slice(0, 6);
      out[sel] = nodes.map((n, i) => {
        const cs = getComputedStyle(n);
        const row = {
          idx: i,
          tag: n.tagName.toLowerCase(),
          classes: (n.className && n.className.toString ? n.className.toString() : '') || '',
          id: n.id || '',
          text: (n.textContent || '').trim().slice(0, 60),
        };
        for (const p of PROPS) row[p] = cs.getPropertyValue(p);
        return row;
      });
    }
    return out;
  }, selectors);
  fs.writeFileSync(path.join(outDir, `${label}.computed.json`), JSON.stringify(data, null, 2));
  return data;
}

// Selectors tailored per site (local uses our class names, SPA uses CSS modules)
const LOCAL_SELS = [
  '.split-invoice-wrapper',
  '.split-invoice__title',
  '.split-invoice-form',
  '.split-invoice-grid',
  '.split-invoice-grid > .split-invoice-input',
  '.split-invoice-grid > .split-invoice-conj',
  '.split-invoice-grid > .split-invoice-pick',
  '.split-invoice-form > .split-invoice-input', // comment input
  '.split-invoice-conj--block',
  '.split-invoice-participants',
  '.split-invoice-participants-list',
  '.participants__row',
  '.participants__row .split-invoice-pick',
  '.participants__remove',
  '.split-invoice-add',
  '.split-invoice-actions',
];

const SPA_SELS = [
  '[class*="split-invoice_wrapper"]',
  '[class*="split-invoice_wrapper"] > h1',
  '[class*="split-invoice_grid"]',
  '[class*="split-invoice_grid"] > input',
  '[class*="split-invoice_grid"] > div',
  '[class*="split-invoice_grid"] > button',
  // SPA uses no specific class for the body inputs/buttons under the wrapper
  '[class*="split-invoice_wrapper"] input[placeholder="comment"]',
  '[class*="split-invoice_wrapper"] button',
];

async function setupPage(p) {
  await p.setViewportSize({ width: 1280, height: 900 });
}

const browser = await chromium.launch();
try {
  // ---------- LOCAL ----------
  {
    const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    const page = await ctx.newPage();

    // Initial
    await page.goto(LOCAL, { waitUntil: 'networkidle' });
    await page.waitForTimeout(400);
    await page.screenshot({ path: path.join(outDir, 'split.local.initial.png'), fullPage: true });
    await dumpComputed(page, 'split.local.initial', LOCAL_SELS);

    // Three rows: click ADD PARTICIPANT 3 times
    for (let i = 0; i < 3; i++) {
      await page.click('.split-invoice-add');
      await page.waitForTimeout(120);
    }
    await page.screenshot({ path: path.join(outDir, 'split.local.threeRows.png'), fullPage: true });
    await dumpComputed(page, 'split.local.threeRows', LOCAL_SELS);

    // Filled
    await page.fill('#amount', '12.50');
    // pick first non-empty option for recipient
    await page.evaluate(() => {
      const sel = document.getElementById('recipient');
      if (sel) {
        const opt = Array.from(sel.options).find(o => o.value);
        if (opt) sel.value = opt.value;
        sel.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });
    await page.fill('#comment', 'Pizza Friday');
    // also fill first 1-2 participant selects so display states are visible
    await page.evaluate(() => {
      const rows = document.querySelectorAll('.split-invoice-participants-list .participants__row select');
      let count = 0;
      rows.forEach((s) => {
        if (count >= 2) return;
        const opt = Array.from(s.options).find(o => o.value);
        if (opt) { s.value = opt.value; s.dispatchEvent(new Event('change', { bubbles: true })); }
        count++;
      });
    });
    await page.waitForTimeout(200);
    await page.screenshot({ path: path.join(outDir, 'split.local.filled.png'), fullPage: true });
    await dumpComputed(page, 'split.local.filled', LOCAL_SELS);

    await ctx.close();
  }

  // ---------- SPA ----------
  {
    const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    const page = await ctx.newPage();
    await page.goto(SPA, { waitUntil: 'networkidle' });
    // SPA hash sometimes hits us at /home; wait until split-invoice grid shows
    await page.waitForSelector('[class*="split-invoice_grid"]', { timeout: 15000 });
    await page.waitForTimeout(600);
    await page.screenshot({ path: path.join(outDir, 'split.spa.initial.png'), fullPage: true });
    await dumpComputed(page, 'split.spa.initial', SPA_SELS);

    // Click "add participant" 3 times. SPA pops a user-selection modal each time;
    // pick the first user, then re-click. Modal selector is #user-selection.
    const addBtn = page.locator('button:has-text("add participant")');
    for (let i = 0; i < 3; i++) {
      await addBtn.click({ force: true });
      // wait for modal user list, click first user-card
      try {
        await page.waitForSelector('#user-selection [class*="search-result-item"], #user-selection [class*="userCard"], #user-selection button', { timeout: 4000 });
        // pick the i-th user so we get distinct ones
        const items = page.locator('#user-selection button');
        const count = await items.count();
        if (count > 0) {
          await items.nth(Math.min(i, count - 1)).click({ force: true });
        }
        // wait for modal to close
        await page.waitForSelector('#user-selection', { state: 'detached', timeout: 4000 }).catch(() => {});
      } catch (e) {
        console.log('no modal for add participant click', i, e.message);
      }
      await page.waitForTimeout(250);
    }
    await page.screenshot({ path: path.join(outDir, 'split.spa.threeRows.png'), fullPage: true });
    await dumpComputed(page, 'split.spa.threeRows', SPA_SELS);

    // Filled state
    const amountInput = page.locator('[class*="split-invoice_grid"] input').first();
    await amountInput.click();
    await amountInput.fill('');
    await amountInput.type('12.50');

    // Select recipient via the modal
    const selectRecipientBtn = page.locator('button:has-text("select recipient")');
    if (await selectRecipientBtn.count()) {
      await selectRecipientBtn.click({ force: true });
      try {
        await page.waitForSelector('#user-selection button', { timeout: 4000 });
        await page.locator('#user-selection button').first().click({ force: true });
        await page.waitForSelector('#user-selection', { state: 'detached', timeout: 4000 }).catch(() => {});
      } catch (e) {
        console.log('select recipient modal not found', e.message);
      }
    }

    // comment input (the one with placeholder "comment" inside the wrapper, outside the grid)
    const commentInput = page.locator('[class*="split-invoice_wrapper"] input[placeholder="comment"]').first();
    if (await commentInput.count()) {
      await commentInput.click();
      await commentInput.fill('');
      await commentInput.type('Pizza Friday');
    }
    await page.waitForTimeout(300);
    await page.screenshot({ path: path.join(outDir, 'split.spa.filled.png'), fullPage: true });
    await dumpComputed(page, 'split.spa.filled', SPA_SELS);

    await ctx.close();
  }
} finally {
  await browser.close();
}

console.log('wrote', outDir);
