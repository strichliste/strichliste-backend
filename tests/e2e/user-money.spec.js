import { test, expect } from '@playwright/test';
import { createUser, expectBalance, uniqueName } from './helpers.js';

test.describe('user accounts and money', () => {
    test('create a user, deposit and withdraw with the step buttons', async ({
        page,
    }) => {
        const name = uniqueName('stepper');
        await createUser(page, name);
        await expectBalance(page, '€0.00');

        await page
            .getByRole('group', { name: 'Deposit' })
            .getByRole('button', { name: '+€1.00' })
            .click();
        await expect(page.locator('.flash--success')).toContainText(
            'Deposit confirmed.',
        );
        await expectBalance(page, '+€1.00');

        await page
            .getByRole('group', { name: 'Withdraw' })
            .getByRole('button', { name: '-€0.50' })
            .click();
        await expect(page.locator('.flash--success')).toContainText(
            'Withdrawal confirmed.',
        );
        await expectBalance(page, '+€0.50');
    });

    test('custom amount deposits via + and withdraws via −', async ({
        page,
    }) => {
        await createUser(page, uniqueName('custom'));

        await page.getByPlaceholder('Custom amount').fill('3.33');
        await page
            .getByRole('button', { name: 'Deposit', exact: true })
            .last()
            .click();
        await expectBalance(page, '+€3.33');

        await page.getByPlaceholder('Custom amount').fill('1,11');
        await page
            .getByRole('button', { name: 'Withdraw', exact: true })
            .last()
            .click();
        await expectBalance(page, '+€2.22');
    });

    test('undo reverts a transaction and marks the row', async ({ page }) => {
        await createUser(page, uniqueName('undoer'));

        await page
            .getByRole('group', { name: 'Deposit' })
            .getByRole('button', { name: '+€5.00' })
            .click();
        await expectBalance(page, '+€5.00');

        const row = page.locator('.transaction-row').first();
        await expect(row.locator('.transaction-row__date')).toBeVisible();
        await row.getByRole('button', { name: /Undo/ }).click();

        await expect(page.locator('.flash--success')).toContainText(
            'Transaction reverted.',
        );
        await expectBalance(page, '€0.00');
        await expect(
            page.locator('.transaction-row__reverted').first(),
        ).toBeVisible();
    });
});
