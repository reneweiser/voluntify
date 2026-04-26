import { expect, test } from '@playwright/test';

test('dashboard shows accessible organizations and lets user switch active org', async ({ page }) => {
    await page.goto('/login');

    await page.getByPlaceholder('email@example.com').fill('dashboard@example.com');
    await page.getByPlaceholder('Password').fill('password');
    await page.getByRole('button', { name: 'Log in' }).click();

    await expect(page).toHaveURL(/\/admin\/dashboard$/);
    await expect(page.getByText('Dashboard Explorer Personal Org').first()).toBeVisible();
    await expect(page.getByText('Organisationen')).toBeVisible();
    await expect(page.locator('div').filter({ hasText: /^Shared Community Org$/ })).toBeVisible();
    await expect(page.getByText('Neighborhood Welcome Project', { exact: true })).toBeVisible();
    await expect(page.getByText('Community Onboarding Day', { exact: true })).toBeVisible();

    const sharedOrgCard = page.locator('div')
        .filter({ hasText: 'Shared Community Org' })
        .filter({ has: page.getByRole('button', { name: 'Wechseln' }) })
        .first();
    await sharedOrgCard.getByRole('button', { name: 'Wechseln' }).click();

    await expect(page).toHaveURL(/\/admin\/dashboard$/);
    await expect(page.locator('div').filter({ hasText: 'Willkommen, Dashboard Explorer!' }).first()).toContainText('Shared Community Org');
});
