import { expect } from '@playwright/test';

let counter = 0;

export function uniqueName(prefix) {
    counter += 1;
    return `${prefix}-${Date.now()}-${counter}`;
}

// creates a user through the real UI and lands on their detail page
export async function createUser(page, name) {
    await page.goto('/user/active');
    await page.getByRole('link', { name: 'Add user' }).click();
    await page.getByLabel('Name').fill(name);
    await page.getByRole('button', { name: 'Create user' }).click();
    await expect(page.getByRole('heading', { level: 1 })).toContainText(name);
}

export async function expectBalance(page, text) {
    await expect(page.locator('.user-hero__balance')).toContainText(text);
}

// drives a ux-autocomplete (Tom Select) recipient combobox: the native <select>
// is hidden once enhanced, so selectOption() no longer applies — open the
// control, type to filter, then click the matching option. `wrapper` is a
// locator for the .ts-wrapper element.
export async function pickRecipient(wrapper, optionLabel) {
    await wrapper.locator('.ts-control').click();
    await wrapper.locator('.ts-control input').fill(optionLabel);
    await wrapper
        .locator('.ts-dropdown .option', { hasText: optionLabel })
        .first()
        .click();
}
