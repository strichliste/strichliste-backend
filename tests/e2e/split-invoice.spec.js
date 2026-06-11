import { test, expect } from '@playwright/test';
import { createUser, expectBalance, uniqueName } from './helpers.js';

test.describe('split invoice', () => {
    test('three-way split with the payer on the list books equal shares', async ({
        page,
    }) => {
        const payer = uniqueName('payer');
        const friend1 = uniqueName('friend');
        const friend2 = uniqueName('friend');
        for (const n of [payer, friend1, friend2]) await createUser(page, n);

        await page.goto('/split-invoice');
        await page.getByLabel('amount').fill('27.50');
        await page
            .getByLabel('select recipient')
            .selectOption({ label: payer });

        await page.getByRole('button', { name: 'add participant' }).click();
        await page.getByRole('button', { name: 'add participant' }).click();
        const picks = page.getByLabel(/^Participant \d+$/);
        await picks.nth(0).selectOption({ label: payer });
        await picks.nth(1).selectOption({ label: friend1 });
        await picks.nth(2).selectOption({ label: friend2 });

        // live preview: 27.50 / 3 people
        await expect(page.locator('#split-preview')).toContainText(
            '€9.17 per person',
        );

        await page.getByRole('button', { name: 'Submit' }).click();

        // lands on the payer: credited the others' shares, own share untouched
        await expect(page.locator('.flash--success')).toContainText('27.50');
        await expectBalance(page, '+€18.33');

        await page.goto('/user/active');
        await page.getByRole('link', { name: friend1 }).click();
        await expectBalance(page, '-€9.17');
    });
});
