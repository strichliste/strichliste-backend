import { chromium } from '@playwright/test';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import fs from 'node:fs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const outDir = path.resolve(__dirname, '..', 'screenshots', 'review-global');
fs.mkdirSync(outDir, { recursive: true });

const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, deviceScaleFactor: 1 });
const page = await ctx.newPage();

// Submit invalid split-invoice (no recipient, no amount, no participants selected)
await page.goto('http://127.0.0.1:8765/split-invoice', { waitUntil: 'networkidle' });
// Override required attributes to bypass HTML5 validation
await page.evaluate(() => {
  document.querySelectorAll('input, select').forEach(el => {
    el.removeAttribute('required');
    el.removeAttribute('min');
  });
});
const submitBtn = page.locator('form .form-actions button[type=submit]').first();
await submitBtn.click().catch(()=>{});
await page.waitForTimeout(800);
await page.screenshot({ path: path.join(outDir, 'form-error.local.light.png'), fullPage: true });

// Capture an inline form-error close-up (the .row-error and unordered-list under field)
const errCard = page.locator('.form-card, .split-invoice-wrapper, form').first();
if (await errCard.count()) {
  try {
    await errCard.screenshot({ path: path.join(outDir, 'form-error-card.local.light.png') });
  } catch {}
}

// Trigger a global flash by submitting an invalid undo (POST to a non-existent /api/transaction route).
// Easier: try to send-money with insufficient balance via the user-detail form.
// Find a user.
const r = await fetch('http://127.0.0.1:8765/api/user?active=true');
const j = await r.json();
const uid = j.users?.[0]?.id;
if (uid) {
  await page.goto(`http://127.0.0.1:8765/user/${uid}?tab=send`, { waitUntil: 'networkidle' });
  // Try to submit massive amount to a recipient
  await page.evaluate(() => {
    document.querySelectorAll('input, select').forEach(el => {
      el.removeAttribute('required');
      el.removeAttribute('min');
      el.removeAttribute('max');
    });
  });
  const amountInput = page.locator('input[name*="amount"], .send-form__amount').first();
  await amountInput.fill('999999').catch(()=>{});
  // Try submit
  const sendBtn = page.locator('.send-form__accept, form[action*="transfer"] button[type=submit]').first();
  await sendBtn.click().catch(()=>{});
  await page.waitForTimeout(700);
  await page.screenshot({ path: path.join(outDir, 'flash.local.light.png'), fullPage: true });
}

await browser.close();
console.log('Done. Output:', outDir);
