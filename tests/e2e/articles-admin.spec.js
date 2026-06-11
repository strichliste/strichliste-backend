import { test, expect } from '@playwright/test';
import { uniqueName } from './helpers.js';

test.describe('article administration', () => {
    test('create an article, attach a barcode and a tag, then archive it', async ({
        page,
    }) => {
        const name = uniqueName('Spezi');

        await page.goto('/articles/active');
        await page.getByRole('link', { name: 'Add article' }).click();
        await page.getByLabel('Name').fill(name);
        await page.getByLabel('Price').fill('1.20');
        await page.getByRole('button', { name: 'Create article' }).click();

        // create lands on the edit page where barcodes and tags live
        await expect(
            page.getByRole('heading', { name: name }).first(),
        ).toBeVisible();
        await page.getByLabel('New barcode').fill('4029764001807');
        await page.getByRole('button', { name: /Add barcode/ }).click();
        await expect(
            page.locator('.chip', { hasText: '4029764001807' }),
        ).toBeVisible();

        await page.getByLabel('New tag').fill('drinks');
        await page.getByRole('button', { name: /Add tag/ }).click();
        await expect(
            page.locator('.chip', { hasText: 'drinks' }),
        ).toBeVisible();

        // archive goes through a confirm page — no one-tap destruction
        await page.getByRole('link', { name: 'Archive article' }).click();
        await expect(
            page.getByRole('heading', { name: /Archive article/ }),
        ).toBeVisible();
        await page.getByRole('button', { name: 'Archive article' }).click();

        await expect(page).toHaveURL(/articles\/inactive/);
        await expect(
            page.getByRole('link', { name: new RegExp(name) }),
        ).toBeVisible();
    });
});
