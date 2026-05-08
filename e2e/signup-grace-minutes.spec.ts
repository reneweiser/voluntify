import { expect, test, type Page } from '@playwright/test';

import { loadFixtures } from './fixtures.js';

type Fixtures = {
    signupGraceEventId: number;
    signupGracePublicToken: string;
    signupGraceVerificationHash: string;
};

async function login(page: Page): Promise<void> {
    await page.goto('/login');
    await page.getByPlaceholder('email@example.com').fill('test@example.com');
    await page.getByPlaceholder('Password').fill('password');
    await page.getByRole('button', { name: 'Log in' }).click();
    await expect(page).toHaveURL(/\/admin\/dashboard$/);
}

test('organizer updates signup grace minutes and the public signup list reacts immediately', async ({ page }) => {
    const fixtures = await loadFixtures<Fixtures>();

    const publicSignupUrl = `/events/${fixtures.signupGracePublicToken}?vt=${fixtures.signupGraceVerificationHash}`;

    await page.goto(publicSignupUrl);
    await page.getByPlaceholder('Your first name').fill('Grace');
    await page.getByPlaceholder('Your last name').fill('Tester');
    await page.getByRole('button', { name: 'Continue' }).click();

    const visibleGraceShift = page.locator('label').filter({ hasText: 'Grace Window Shift' });
    const visiblePastCutoffShift = page.locator('label').filter({ hasText: 'Past Cutoff Shift' });

    await expect(visibleGraceShift).toBeVisible();
    await expect(visiblePastCutoffShift).toHaveCount(0);

    await login(page);
    await page.goto(`/admin/events/${fixtures.signupGraceEventId}/settings`);

    await page.getByLabel('Signup Grace Period (minutes)').fill('60');
    await page.getByRole('button', { name: 'Save Changes' }).click();

    await expect(page).toHaveURL(new RegExp(`/admin/events/${fixtures.signupGraceEventId}$`));

    await page.goto(publicSignupUrl);
    await page.getByPlaceholder('Your first name').fill('Grace');
    await page.getByPlaceholder('Your last name').fill('Tester');
    await page.getByRole('button', { name: 'Continue' }).click();

    await expect(visibleGraceShift).toBeVisible();
    await expect(visiblePastCutoffShift).toBeVisible();
});
