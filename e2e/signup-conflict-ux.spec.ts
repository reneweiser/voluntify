import { expect, test, type Locator, type Page } from '@playwright/test';

import { loadFixtures } from './fixtures.js';

type Fixtures = {
    signupConflictPublicToken: string;
    signupConflictNewVolunteerHash: string;
    signupConflictReturningConflictHash: string;
    signupConflictReactivateSuccessHash: string;
    signupConflictReactivateBlockedHash: string;
};

function publicSignupUrl(publicToken: string, verificationHash: string): string {
    return `/events/${publicToken}?vt=${verificationHash}`;
}

async function continueToShiftSelection(page: Page, url: string): Promise<void> {
    await page.goto(url);
    await page.getByRole('button', { name: 'Continue' }).click();
    await expect(page.getByRole('heading', { name: 'Choose Your Shifts' })).toBeVisible();
}

function jobCard(page: Page, jobName: string): Locator {
    return page
        .locator('div[wire\\:key^="job-"]')
        .filter({ has: page.locator('h3', { hasText: jobName }) });
}

function shiftCheckbox(page: Page, jobName: string): Locator {
    return jobCard(page, jobName).locator('input[type="checkbox"]').first();
}

test('newly selected overlapping shifts show specific conflict names and clear after deselection', async ({ page }) => {
    const fixtures = await loadFixtures<Fixtures>();

    await continueToShiftSelection(
        page,
        publicSignupUrl(fixtures.signupConflictPublicToken, fixtures.signupConflictNewVolunteerHash),
    );

    await shiftCheckbox(page, 'Morning Setup').check();
    await shiftCheckbox(page, 'Registration Desk').check();

    const conflictWarning = page.locator('#shift-selection-conflicts');

    await expect(conflictWarning).toBeVisible();
    await expect(conflictWarning).toContainText('Morning Setup');
    await expect(conflictWarning).toContainText('Registration Desk');

    await shiftCheckbox(page, 'Registration Desk').uncheck();

    await expect(conflictWarning).toHaveCount(0);

    await page.getByRole('button', { name: 'Continue' }).click();

    await expect(page.getByRole('heading', { name: 'Confirm Your Signup' })).toBeVisible();
    await expect(page.locator('div').filter({ hasText: 'Selected Shifts' }).getByText('Morning Setup:')).toBeVisible();
});

test('returning volunteer conflict copy names the held shift and only flags the new selection', async ({ page }) => {
    const fixtures = await loadFixtures<Fixtures>();

    await continueToShiftSelection(
        page,
        publicSignupUrl(fixtures.signupConflictPublicToken, fixtures.signupConflictReturningConflictHash),
    );

    const heldShift = jobCard(page, 'Held Check-In');
    await expect(heldShift).toContainText('Already signed up');

    await shiftCheckbox(page, 'Crowd Support').check();

    const conflictItems = page.locator('#shift-selection-conflicts li');
    await expect(conflictItems).toHaveCount(1);
    await expect(conflictItems.first()).toContainText('Crowd Support');
    await expect(conflictItems.first()).toContainText('Held Check-In');

    await shiftCheckbox(page, 'Crowd Support').uncheck();
    await shiftCheckbox(page, 'Closing Support').check();

    await expect(page.locator('#shift-selection-conflicts')).toHaveCount(0);

    await page.getByRole('button', { name: 'Continue' }).click();

    await expect(page.getByRole('heading', { name: 'Confirm Your Signup' })).toBeVisible();
    await expect(page.locator('div').filter({ hasText: 'Selected Shifts' }).getByText('(already signed up)')).toBeVisible();

    await page.getByRole('button', { name: 'Back' }).click();
    await expect(page.getByRole('heading', { name: 'Choose Your Shifts' })).toBeVisible();

    await shiftCheckbox(page, 'Crowd Support').check();

    await page.getByRole('button', { name: 'Continue' }).click();

    await expect(page.getByText('Some selected shifts overlap in time. Review the conflict details below before continuing.')).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Choose Your Shifts' })).toBeVisible();
});

test('cancelled shift can be re-selected and reactivated when the active schedule no longer overlaps', async ({ page }) => {
    const fixtures = await loadFixtures<Fixtures>();

    await continueToShiftSelection(
        page,
        publicSignupUrl(fixtures.signupConflictPublicToken, fixtures.signupConflictReactivateSuccessHash),
    );

    const activeShift = jobCard(page, 'Welcome Tent');
    await expect(activeShift).toContainText('Already signed up');

    await shiftCheckbox(page, 'Warehouse Morning').check();
    await expect(page.locator('#shift-selection-conflicts')).toHaveCount(0);

    await page.getByRole('button', { name: 'Continue' }).click();
    await expect(page.getByRole('heading', { name: 'Confirm Your Signup' })).toBeVisible();

    await page.getByRole('button', { name: 'Sign Up' }).click();
    await expect(page.getByRole('heading', { name: "You're signed up!" })).toBeVisible();
});

test('cancelled shift stays blocked when a newer active signup still overlaps it', async ({ page }) => {
    const fixtures = await loadFixtures<Fixtures>();

    await continueToShiftSelection(
        page,
        publicSignupUrl(fixtures.signupConflictPublicToken, fixtures.signupConflictReactivateBlockedHash),
    );

    const activeShift = jobCard(page, 'Main Stage Help');
    await expect(activeShift).toContainText('Already signed up');

    await shiftCheckbox(page, 'Check-In Revisit').check();

    const conflictItems = page.locator('#shift-selection-conflicts li');
    await expect(conflictItems).toHaveCount(1);
    await expect(conflictItems.first()).toContainText('Check-In Revisit');
    await expect(conflictItems.first()).toContainText('Main Stage Help');

    await page.getByRole('button', { name: 'Continue' }).click();

    await expect(page.getByText('Some selected shifts overlap in time. Review the conflict details below before continuing.')).toBeVisible();
    await expect(page.locator('#shift-selection-conflicts')).toContainText('Check-In Revisit');
    await expect(page.locator('#shift-selection-conflicts')).toContainText('Main Stage Help');
    await expect(page.getByRole('heading', { name: 'Choose Your Shifts' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Confirm Your Signup' })).toHaveCount(0);
});
