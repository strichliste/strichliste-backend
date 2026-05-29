import { test, expect } from '@playwright/test';

/**
 * Final regression: extra coverage beyond the smoke/bugfix specs.
 * Anything we explicitly fixed during the review-driven cleanup gets a test here.
 */

test('card class is rendered cleanly (no nested class="" attribute)', async ({ page }) => {
  await page.goto('/user/active/add');
  const card = page.locator('.card').first();
  const cls = await card.getAttribute('class');
  expect(cls).toBeTruthy();
  // Defends against the original Card.html.twig bug that produced
  // 'class="card class="foo""'.
  expect(cls).not.toContain('class=');
});

test('article price field accepts euros (MoneyType) and stores cents', async ({ page, request }) => {
  await page.goto('/articles/add');
  await page.locator('input[name="create_article[name]"]').fill(`PW-Article-${Date.now()}`);
  await page.locator('input[name="create_article[amount]"]').fill('1.50');
  const token = await page.locator('input[name="create_article[_token]"]').getAttribute('value');

  const resp = await page.request.post('/articles/add', {
    form: {
      'create_article[name]': `PW-Article-${Date.now()}`,
      'create_article[amount]': '1.50',
      'create_article[_token]': token ?? '',
    },
  });
  expect([200, 303]).toContain(resp.status());

  // Verify via API
  const list = await (await request.get('/api/article')).json();
  const found = list.articles.find((a: any) => a.name.startsWith('PW-Article'));
  expect(found?.amount).toBe(150);
});

test('PayPal start route 404s when paypal is disabled', async ({ request }) => {
  const settings = await (await request.get('/api/settings')).json();
  if (settings.settings.paypal.enabled) {
    test.skip();
    return;
  }
  const resp = await request.post('/user/1/paypal/start', {
    form: { _token: 'whatever', amount: '5' },
    maxRedirects: 0,
  });
  expect(resp.status()).toBe(404);
});

test('buy tab search input does not have autofocus', async ({ page, request }) => {
  // The buy tab's text input is a client-side article-name filter (the barcode
  // scanner is now document-level — see barcode.spec.ts).
  const r = await request.post('/api/user', { form: { name: `PW-BuyTab-${Date.now()}` } });
  const id = ((await r.json()) as any).user.id;
  await page.goto(`/user/${id}?tab=buy`);
  const input = page.locator('#buy-search');
  await expect(input).not.toHaveAttribute('autofocus', /.+/);
});

test('balance pill announces sign for screen readers', async ({ page, request }) => {
  const r = await request.post('/api/user', { form: { name: `PW-Pill-${Date.now()}` } });
  const id = ((await r.json()) as any).user.id;
  await request.post(`/api/user/${id}/transaction`, { form: { amount: '500' } });

  await page.goto(`/user/${id}`);
  const srLabel = page.locator('.balance-pill .visually-hidden').first();
  await expect(srLabel).toHaveText(/positive/i);
});

test('flash region has no nested aria-live (avoids double-announce)', async ({ page, request }) => {
  // Trigger a flash by creating a user via the UI form
  await page.goto('/user/active/add');
  const token = await page.locator('input[name="create_user[_token]"]').getAttribute('value');
  await page.request.post('/user/active/add', {
    form: {
      'create_user[name]': `PW-Flash-${Date.now()}`,
      'create_user[_token]': token ?? '',
    },
  });
  await page.goto('/user/active');
  // Outer .flashes div should NOT have aria-live; inner .flash should have role=status or role=alert.
  const outer = page.locator('.flashes');
  if (await outer.count()) {
    await expect(outer).not.toHaveAttribute('aria-live', /.+/);
  }
});
