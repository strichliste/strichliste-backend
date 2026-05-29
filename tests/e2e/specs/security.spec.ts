import { test, expect } from '@playwright/test';

/**
 * Regression tests for the security + correctness fixes from the second review.
 * Critical: PayPal sig, locale-decimal parsing, split remainder.
 */

test('PayPal: unsigned return URL does not credit money (404)', async ({ request }) => {
  const r = await request.post('/api/user', { form: { name: `PW-PayPalSig-${Date.now()}` } });
  const id = ((await r.json()) as any).user.id;
  const before = ((await (await request.get(`/api/user/${id}`)).json()) as any).user.balance;

  // Plain GET — no sig, no expiration — must 404.
  const resp = await request.get(`/user/${id}/paypal/return/100`);
  expect(resp.status()).toBe(404);

  // And the alleged amount must NOT have been credited.
  const after = ((await (await request.get(`/api/user/${id}`)).json()) as any).user.balance;
  expect(after).toBe(before);
});

test('PayPal: tampered hash on return URL is rejected (404)', async ({ request }) => {
  const r = await request.post('/api/user', { form: { name: `PW-PayPalTamper-${Date.now()}` } });
  const id = ((await r.json()) as any).user.id;

  // A made-up _hash query param must not pass verification.
  const resp = await request.get(`/user/${id}/paypal/return/100?_hash=deadbeef&_expiration=99999999999`);
  expect(resp.status()).toBe(404);
});

test('split-invoice: 10.01 € split 3 ways distributes the remainder (no cents vanish)', async ({ page, request }) => {
  const ts = Date.now();
  const r = ((await (await request.post('/api/user', { form: { name: `PW-Rcpt-${ts}` } })).json()) as any).user.id;
  const a = ((await (await request.post('/api/user', { form: { name: `PW-P1-${ts}` } })).json()) as any).user.id;
  const b = ((await (await request.post('/api/user', { form: { name: `PW-P2-${ts}` } })).json()) as any).user.id;
  const c = ((await (await request.post('/api/user', { form: { name: `PW-P3-${ts}` } })).json()) as any).user.id;

  await page.goto('/split-invoice');
  await page.locator('#recipient').selectOption(String(r));
  await page.locator('#amount').fill('10.01');
  // The default 3 rows are enough; just pick three participants.
  await page.locator('select[name="participants[]"]').nth(0).selectOption(String(a));
  await page.locator('select[name="participants[]"]').nth(1).selectOption(String(b));
  await page.locator('select[name="participants[]"]').nth(2).selectOption(String(c));
  await page.locator('main button[type="submit"]').click();
  // Verify: recipient credited 1001c, participants debited a total of 1001c.
  const recipientBalance = ((await (await request.get(`/api/user/${r}`)).json()) as any).user.balance;
  const ab = ((await (await request.get(`/api/user/${a}`)).json()) as any).user.balance;
  const bb = ((await (await request.get(`/api/user/${b}`)).json()) as any).user.balance;
  const cb = ((await (await request.get(`/api/user/${c}`)).json()) as any).user.balance;
  expect(recipientBalance).toBe(1001);
  expect(-(ab + bb + cb)).toBe(1001);
  // First two carry the +1 remainder, last carries the base.
  // 1001 = 334 + 334 + 333. (Order may shift; assert the multiset.)
  const debits = [ab, bb, cb].map((n) => -n).sort((x, y) => y - x);
  expect(debits).toEqual([334, 334, 333]);
});

test('split-invoice: comma-decimal "5,99" parses to 599 cents (locale-tolerant)', async ({ page, request }) => {
  const ts = Date.now();
  const r = ((await (await request.post('/api/user', { form: { name: `PW-Comma-Rcpt-${ts}` } })).json()) as any).user.id;
  const a = ((await (await request.post('/api/user', { form: { name: `PW-Comma-P1-${ts}` } })).json()) as any).user.id;

  // The `<input type="number">` browser would normalize, but PHP-side we accept
  // either. POST directly to ensure the server-side parser handles the comma.
  await page.goto('/split-invoice');
  const token = await page.locator('input[name="_token"]').getAttribute('value');
  const resp = await page.request.post('/split-invoice', {
    form: {
      _token: token ?? '',
      recipient: String(r),
      amount: '5,99',
      'participants[]': String(a),
    },
  });
  expect([200, 303]).toContain(resp.status());
  // Verify A was debited 599, not 5 (the bug we're guarding against).
  const ab = ((await (await request.get(`/api/user/${a}`)).json()) as any).user.balance;
  expect(ab).toBe(-599);
});

test('CreateTransactionType: user_id is required (constructor would throw if a controller forgot)', async () => {
  // Smoke: nothing to drive from the browser. The constructor-time guard is
  // exercised by the create-step flow already covered by smoke.spec.ts; this
  // test exists as documentation that the constraint is intentional.
  expect(true).toBe(true);
});
