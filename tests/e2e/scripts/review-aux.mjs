import { chromium } from '@playwright/test';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import fs from 'node:fs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const outDir = path.resolve(__dirname, '..', 'screenshots', 'review-aux');
fs.mkdirSync(outDir, { recursive: true });

const LOCAL = 'http://127.0.0.1:8765';
const SPA = 'https://demo.strichliste.org';

function shotPath(name) {
  return path.join(outDir, name);
}

// Fetch a real user id from the local API.
async function getLocalUserId() {
  const resp = await fetch(`${LOCAL}/api/user?active=true`);
  const data = await resp.json();
  // Pick one that's likely tidy: first one.
  return data.users[0].id;
}

// Fetch the first user id from the SPA's API.
async function getSpaUserId() {
  const resp = await fetch(`${SPA}/api/user?active=true`);
  const data = await resp.json();
  return data.users[0].id;
}

const browser = await chromium.launch();

try {
  const localUserId = await getLocalUserId();
  const spaUserId = await getSpaUserId();
  console.log('local user id:', localUserId, 'spa user id:', spaUserId);

  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, deviceScaleFactor: 1 });
  const page = await ctx.newPage();

  // ----- 1. CREATE USER -----
  // Local: /user/active/add (Twig form page)
  await page.goto(`${LOCAL}/user/active/add`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(300);
  await page.screenshot({ path: shotPath('create-user.local.png'), fullPage: false });
  console.log('wrote create-user.local.png');

  // SPA: open inline modal by clicking the FAB on /#/user/active
  await page.goto(`${SPA}/#/user/active`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(1500);
  // The FAB has class .button_fab__... — search by role or text.
  // Heuristic: an "add" button.
  // Try a few selectors.
  const fab = page.locator('button[class*="fab"]').first();
  await fab.click().catch(() => {});
  await page.waitForTimeout(800);
  await page.screenshot({ path: shotPath('create-user.spa.png'), fullPage: false });
  console.log('wrote create-user.spa.png');

  // ----- 2. EDIT USER -----
  // Local: /user/{id}/edit
  await page.goto(`${LOCAL}/user/${localUserId}/edit`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(400);
  await page.screenshot({ path: shotPath('edit-user.local.png'), fullPage: false });
  console.log('wrote edit-user.local.png');

  // SPA: /#/user/{id} then click EDIT USER tab
  await page.goto(`${SPA}/#/user/${spaUserId}`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(1200);
  // Try to find a tab labeled "Edit"
  const editTab = page.locator('a:has-text("Edit"), button:has-text("Edit")').first();
  await editTab.click().catch(() => {});
  await page.waitForTimeout(800);
  await page.screenshot({ path: shotPath('edit-user.spa.png'), fullPage: false });
  console.log('wrote edit-user.spa.png');

  // ----- 3. TRANSACTIONS HISTORY -----
  // Local only.
  await page.goto(`${LOCAL}/user/${localUserId}/transactions`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(400);
  await page.screenshot({ path: shotPath('transactions.local.png'), fullPage: false });
  console.log('wrote transactions.local.png');

  // Capture the SPA user detail with its recent-list as the closest analog.
  await page.goto(`${SPA}/#/user/${spaUserId}`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(1500);
  await page.screenshot({ path: shotPath('transactions.spa-user-detail.png'), fullPage: false });
  console.log('wrote transactions.spa-user-detail.png');

  // ----- 4. SPLIT INVOICE -----
  await page.goto(`${LOCAL}/split-invoice`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(400);
  await page.screenshot({ path: shotPath('split-invoice.local.png'), fullPage: false });
  console.log('wrote split-invoice.local.png');

  await page.goto(`${SPA}/#/split-invoice`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(1500);
  await page.screenshot({ path: shotPath('split-invoice.spa.png'), fullPage: false });
  console.log('wrote split-invoice.spa.png');

  // ----- 5. 404 -----
  // Local. Note: dev env shows a debug page, but we'll try anyway and just snapshot.
  await page.goto(`${LOCAL}/this-does-not-exist`, { waitUntil: 'domcontentloaded' }).catch(() => {});
  await page.waitForTimeout(400);
  await page.screenshot({ path: shotPath('404.local.png'), fullPage: false });
  console.log('wrote 404.local.png');

  await page.goto(`${SPA}/#/totally-fake`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(1500);
  await page.screenshot({ path: shotPath('404.spa.png'), fullPage: false });
  console.log('wrote 404.spa.png');
} finally {
  await browser.close();
}
