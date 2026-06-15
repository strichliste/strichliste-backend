import { test, expect } from '@playwright/test';
import { createUser, expectBalance, uniqueName } from './helpers.js';

test.describe('buying and sending', () => {
    test('buy an article from the buy tab, with the search filter', async ({
        page,
    }) => {
        await createUser(page, uniqueName('buyer'));

        await page.getByRole('link', { name: 'Buy article' }).click();
        const filter = page.getByPlaceholder('search for article');
        await expect(filter).toBeVisible(); // revealed by JS, hidden without it
        await filter.fill('club');
        await expect(page.getByRole('button', { name: /Beer/ })).toBeHidden();

        await page.getByRole('button', { name: /Club Mate/ }).click();
        await expect(page.locator('.flash--success')).toContainText(
            'Bought Club Mate.',
        );
        await expectBalance(page, '-€1.50');
    });

    test('send money to another user with a note', async ({ page }) => {
        const recipient = uniqueName('recv');
        const sender = uniqueName('send');
        await createUser(page, recipient);
        await createUser(page, sender);

        await page.getByRole('link', { name: 'Send money' }).click();
        // scope to the send composer — the quick-action card labels an "Amount" too
        const form = page.locator('.send-form-wrap');
        await form.getByLabel('Amount').fill('2.50');
        await form.getByLabel('Recipient').selectOption({ label: recipient });
        await form.getByLabel('Comment (optional)').fill('pizza');
        await form.getByRole('button', { name: 'Send' }).click();

        await expect(page.locator('.flash--success')).toContainText(
            `Transferred to ${recipient}.`,
        );
        await expectBalance(page, '-€2.50');
        await expect(page.locator('.transaction-row').first()).toContainText(
            'pizza',
        );

        await page.goto('/user/active');
        await page.getByRole('link', { name: recipient }).click();
        await expectBalance(page, '+€2.50');
        await expect(page.locator('.transaction-row').first()).toContainText(
            sender,
        );
    });
});
