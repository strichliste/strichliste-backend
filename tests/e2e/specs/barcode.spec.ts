import { test, expect } from '@playwright/test';

/**
 * Document-level barcode scanner: typing a barcode anywhere on the user
 * detail page (not in a focused field) buys the matching article.
 */

test('rapid-fire barcode keystrokes + Enter buys the article (no focused input)', async ({ page, request }) => {
  const ts = Date.now();
  // Set up: create article + barcode, create user.
  const article = ((await (await request.post('/api/article', {
    form: { name: `PW-Scan-Article-${ts}`, amount: '300' },
  })).json()) as any).article;
  await request.post(`/api/article/${article.id}/barcode`, { form: { barcode: `PWSCAN${ts}` } });
  const userId = ((await (await request.post('/api/user', { form: { name: `PW-Scan-User-${ts}` } })).json()) as any).user.id;
  const balanceBefore = ((await (await request.get(`/api/user/${userId}`)).json()) as any).user.balance;

  await page.goto(`/user/${userId}`);
  // No tab — default state, no visible buy input. Type the barcode anyway.
  // Each press has a 5ms delay so the controller treats them as a HID burst.
  for (const ch of `PWSCAN${ts}`.split('')) {
    await page.keyboard.press(ch, { delay: 5 });
  }
  await page.keyboard.press('Enter');
  // Wait for the navigation triggered by the hidden form submit.
  await page.waitForLoadState('load');

  const after = ((await (await request.get(`/api/user/${userId}`)).json()) as any).user.balance;
  expect(after - balanceBefore).toBe(-300);
});

test('typing into a real input field is NOT intercepted by the scanner', async ({ page, request }) => {
  // Open BUY tab, type into the article-name search filter at human speed.
  // The buffer must NOT trigger a buy.
  const ts = Date.now();
  const userId = ((await (await request.post('/api/user', { form: { name: `PW-NoFire-${ts}` } })).json()) as any).user.id;
  await page.goto(`/user/${userId}?tab=buy`);
  await page.locator('#buy-search').click();
  await page.locator('#buy-search').type('coffee', { delay: 100 });
  await page.keyboard.press('Enter');
  await page.waitForTimeout(500);

  // No matching barcode → no transaction. Balance unchanged.
  const after = ((await (await request.get(`/api/user/${userId}`)).json()) as any).user.balance;
  expect(after).toBe(0);
});
