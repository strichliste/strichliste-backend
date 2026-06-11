import { test, expect } from '@playwright/test';

test.use({ viewport: { width: 375, height: 812 } });

test.describe('phone layout', () => {
    test('nav collapses into the burger menu and still navigates', async ({
        page,
    }) => {
        await page.goto('/user/active');

        // desktop nav hidden, burger visible
        await expect(
            page.getByRole('navigation', { name: 'Main navigation' }).first(),
        ).toBeHidden();
        const burger = page.locator('.app-header__menu summary');
        await burger.click();
        await page
            .locator('.app-header__menu-panel')
            .getByRole('link', { name: 'Article List' })
            .click();
        await expect(page).toHaveURL(/articles\/active/);

        // menu is closed again after the Turbo visit
        await expect(page.locator('.app-header__menu-panel')).toBeHidden();
    });
});
