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
