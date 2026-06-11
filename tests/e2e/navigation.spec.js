import { test, expect } from '@playwright/test';
import { createUser, uniqueName } from './helpers.js';

test.describe('navigation, search and metrics', () => {
    test('header nav reaches every section', async ({ page }) => {
        await page.goto('/user/active');
        await page
            .getByRole('navigation', { name: 'Main navigation' })
            .getByRole('link', { name: 'Article List' })
            .click();
        await expect(page).toHaveURL(/articles\/active/);
        await page
            .getByRole('navigation', { name: 'Main navigation' })
            .getByRole('link', { name: 'Split Invoice' })
            .click();
        await expect(
            page.getByRole('heading', { name: 'Split Invoice' }),
        ).toBeVisible();
        await page
            .getByRole('navigation', { name: 'Main navigation' })
            .getByRole('link', { name: 'Metrics' })
            .click();
        await expect(page).toHaveURL(/metrics/);
    });

    test('header search finds users and articles', async ({ page }) => {
        const name = uniqueName('findme');
        await createUser(page, name);

        const search = page.getByRole('search').getByRole('textbox');
        await search.fill(name.slice(0, 12));
        await page
            .getByRole('search')
            .getByRole('button', { name: 'Search' })
            .click();
        await expect(
            page.getByRole('link', { name: new RegExp(name) }),
        ).toBeVisible();

        await search.fill('Club');
        await page
            .getByRole('search')
            .getByRole('button', { name: 'Search' })
            .click();
        await expect(
            page.getByRole('link', { name: /Club Mate/ }),
        ).toBeVisible();
    });

    test('metrics pages render KPIs, charts fall back to tables, top sellers listed', async ({
        page,
    }) => {
        const name = uniqueName('metric');
        await createUser(page, name);
        await page
            .getByRole('group', { name: 'Deposit' })
            .getByRole('button', { name: '+€1.00' })
            .click();
        await page.getByRole('link', { name: 'Buy article' }).click();
        await page.getByRole('button', { name: /Beer/ }).click();

        await page.goto('/metrics');
        await expect(page.locator('.kpi')).toHaveCount(3);
        await expect(
            page.getByRole('heading', { name: /Top sellers/ }),
        ).toBeVisible();
        await expect(
            page.locator('table.metrics-table').first(),
        ).toBeAttached();

        await page.goto('/user/active');
        await page.getByRole('link', { name: name }).click();
        await page.getByRole('link', { name: 'My metrics' }).click();
        await expect(
            page.getByRole('heading', {
                name: new RegExp(`Metrics · ${name}`),
            }),
        ).toBeVisible();
        await expect(
            page.getByText('Reverted transactions are not counted here.'),
        ).toBeVisible();
    });
});
