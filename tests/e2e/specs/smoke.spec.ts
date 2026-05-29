import { test, expect } from '@playwright/test';

/**
 * Baseline smoke specs. These will surface bugs as we walk the codebase fixing
 * critical/high/medium findings from the multi-agent review.
 */

test('home redirects to /user/active', async ({ page }) => {
  await page.goto('/');
  await expect(page).toHaveURL(/\/user\/active$/);
});

test('user list shows active users when present', async ({ page, request }) => {
  const name = `PW-Visible-${Date.now()}`;
  await request.post('/api/user', { form: { name } });
  // List is sorted alphabetically; recently-created PW-* users may be on later pages.
  // Walk pages until we find it or run out.
  for (let pageNum = 1; pageNum <= 20; pageNum++) {
    await page.goto(`/user/active?page=${pageNum}`);
    if (await page.getByText(name).count() > 0) {
      await expect(page.getByText(name).first()).toBeVisible();
      return;
    }
    const nextLink = page.locator('a[rel="next"]');
    if (!(await nextLink.count())) break;
  }
  throw new Error(`User ${name} not found on any page`);
});

test('coming-soon stubs no longer exist for unimplemented routes', async ({ page }) => {
  // Every navigation target in the header now resolves to a real page
  // (no stub controllers left after Epic 6).
  for (const route of ['/articles/active', '/split-invoice', '/metrics', '/search-results']) {
    const resp = await page.goto(route);
    expect.soft(resp?.status(), `${route} should be 200`).toBe(200);
    await expect.soft(page.locator('.coming-soon'), `${route} should NOT render the stub template`).toHaveCount(0);
  }
});

test('header search submits to /search-results', async ({ page }) => {
  await page.goto('/user/active');
  await page.locator('#hsearch').fill('PW');
  await page.locator('.app-header__search button[type="submit"]').click();
  await expect(page).toHaveURL(/\/search-results\?q=PW/);
});

test('split-invoice form starts with 3 participant rows', async ({ page }) => {
  await page.goto('/split-invoice');
  await expect(page.locator('.participants__row')).toHaveCount(3);
});

test('split-invoice "Add participant" appends a row via JS', async ({ page }) => {
  await page.goto('/split-invoice');
  await expect(page.locator('.participants__row')).toHaveCount(3);
  await page.locator('.participants__add').click();
  await expect(page.locator('.participants__row')).toHaveCount(4);
  await page.locator('.participants__add').click();
  await expect(page.locator('.participants__row')).toHaveCount(5);
});

test('split-invoice "Remove" button removes a row, keeps at least one', async ({ page }) => {
  await page.goto('/split-invoice');
  // Add one extra so we have 4 total, then remove 3.
  await page.locator('.participants__add').click();
  await expect(page.locator('.participants__row')).toHaveCount(4);
  // Remove three rows
  for (let i = 0; i < 3; i++) {
    await page.locator('.participants__row .participants__remove:not([hidden])').first().click();
  }
  await expect(page.locator('.participants__row')).toHaveCount(1);
  // Remove button on the last row should be hidden — controller keeps at least one row.
  await expect(page.locator('.participants__row .participants__remove')).toBeHidden();
});

test('metrics chart canvas is present', async ({ page }) => {
  await page.goto('/metrics');
  await expect(page.locator('canvas[data-controller~="chart"]')).toBeVisible();
});

test('user detail has no tab selected by default', async ({ page, request }) => {
  const r = await request.post('/api/user', { form: { name: `PW-NoTab-${Date.now()}` } });
  const id = ((await r.json()) as any).user.id;
  await page.goto(`/user/${id}`);
  await expect(page.locator('.user-tabs a[aria-current="page"]')).toHaveCount(0);
  await expect(page.locator('.tab-panel')).toHaveCount(0);
});

test('clicking a user tab toggles it off when clicked again', async ({ page, request }) => {
  const r = await request.post('/api/user', { form: { name: `PW-Toggle-${Date.now()}` } });
  const id = ((await r.json()) as any).user.id;
  await page.goto(`/user/${id}`);
  // Click EDIT USER
  await page.locator('.user-tabs a').filter({ hasText: /edit user/i }).click();
  await expect(page).toHaveURL(/tab=edit/);
  await expect(page.locator('.tab-panel')).toBeVisible();
  // Click it again — should drop back to no-tab.
  await page.locator('.user-tabs a').filter({ hasText: /edit user/i }).click();
  await expect(page).toHaveURL(new RegExp(`/user/${id}$`));
  await expect(page.locator('.tab-panel')).toHaveCount(0);
});

test('metrics page shows the three KPI cards', async ({ page }) => {
  await page.goto('/metrics');
  const kpiLabels = page.locator('.kpi__label');
  await expect(kpiLabels.nth(0)).toHaveText('Balance');
  await expect(kpiLabels.nth(1)).toHaveText('Users');
  await expect(kpiLabels.nth(2)).toHaveText('Transactions');
});

test('deposit step button charges the displayed amount in cents', async ({ page, request }) => {
  // Create a fresh user
  const res = await request.post('/api/user', { form: { name: `PW-Deposit-${Date.now()}` } });
  const data = (await res.json()) as { user: { id: number } };
  const id = data.user.id;

  await page.goto(`/user/${id}?tab=send`);
  // Step buttons now use .step-btn--green and display "+€0.50" (or similar).
  const button = page.locator('.step-btn--green', { hasText: '0.50' }).first();
  await expect(button).toBeVisible();
  await button.click();
  // Redirected back; balance should be 50
  const r = await request.get(`/api/user/${id}`);
  const ud = (await r.json()) as { user: { balance: number } };
  expect(ud.user.balance).toBe(50);
});

test('custom-amount deposit accepts euros and stores cents', async ({ page, request }) => {
  // After the fix: input is major units (€). Typing 1.23 deposits 123 cents.
  const res = await request.post('/api/user', { form: { name: `PW-Custom-${Date.now()}` } });
  const id = ((await res.json()) as { user: { id: number } }).user.id;

  await page.goto(`/user/${id}?tab=send`);
  const customForm = page.locator('details.custom-form');
  await customForm.click();
  await customForm.locator('input[name="create_transaction[amount]"]').first().fill('1.23');
  await customForm.getByRole('button', { name: 'Deposit' }).first().click();
  const r = await request.get(`/api/user/${id}`);
  const ud = (await r.json()) as { user: { balance: number } };
  expect(ud.user.balance).toBe(123);
});

test('header brand link goes to /user/active', async ({ page }) => {
  await page.goto('/metrics');
  // Brand is the anchor itself now (no nested <a>).
  await page.locator('.app-header__brand').click();
  await expect(page).toHaveURL(/\/user\/active$/);
});
