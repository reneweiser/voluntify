import { expect, test } from '@playwright/test';

test.use({
    viewport: { width: 430, height: 520 },
});

test('volunteer admin scanner shift list supports browse, jump, search, and detail handoff', async ({ page }) => {
    await page.addInitScript(() => {
        Object.defineProperty(HTMLMediaElement.prototype, 'play', {
            configurable: true,
            value: async () => undefined,
        });

        Object.defineProperty(navigator, 'mediaDevices', {
            configurable: true,
            value: {
                getUserMedia: async () => new MediaStream(),
            },
        });
    });

    await page.goto('/s/e2e-va-shift-list-token');

    await page.getByLabel('Enter 6-digit code').fill('222222');
    await page.getByRole('button', { name: 'Authenticate' }).click();

    await expect(page).toHaveURL(/\/s\/e2e-va-shift-list-token\/scan$/);
    await expect(page.getByRole('tab', { name: 'Scanner' })).toBeVisible();
    await expect(page.getByRole('tab', { name: 'Schichtliste' })).toBeVisible();

    await page.getByRole('tab', { name: 'Schichtliste' }).click();
    await expect(page.getByText('Welcome Desk')).toBeVisible();

    const groupHeadings = await page.locator('#tabpanel-shifts p.text-sm.font-semibold.text-white').allTextContents();
    expect(groupHeadings).toEqual(['Welcome Desk', 'Badge Check', 'Cleanup Crew']);

    await expect(page.getByText('Laeuft jetzt')).toBeVisible();
    await expect(page.getByText('Als Naechstes')).toBeVisible();

    const lisaRows = page.locator('#tabpanel-shifts button').filter({ hasText: 'Lisa Mueller' });
    await expect(lisaRows).toHaveCount(2);

    const nextUpcomingTopBefore = await page.getByText('Badge Check').evaluate((element) => element.getBoundingClientRect().top);

    await page.getByRole('button', { name: 'Jetzt' }).click();

    await expect
        .poll(async () => page.getByText('Badge Check').evaluate((element) => element.getBoundingClientRect().top))
        .toBeLessThan(nextUpcomingTopBefore);

    const searchInput = page.getByPlaceholder('Search shifts or volunteers...');
    await searchInput.fill('L');
    await expect(page.getByText('Mindestens 2 Zeichen eingeben.')).toBeVisible();

    await searchInput.fill('Lisa');
    await expect(lisaRows).toHaveCount(2);
    await expect(page.getByText('Badge Check')).not.toBeVisible();
    await expect(page.locator('#tabpanel-shifts button').filter({ hasText: 'Tom Weber' })).toHaveCount(0);

    await lisaRows.first().click();

    const scannerTabPanel = page.locator('#tabpanel-va-scanner');

    await expect(page.getByRole('tab', { name: 'Scanner' })).toHaveAttribute('aria-selected', 'true');
    await expect(scannerTabPanel.getByText('Lisa Mueller').first()).toBeVisible();
    await expect(scannerTabPanel.getByText('lisa-shifts@example.com')).toBeVisible();
    await expect(scannerTabPanel.getByText('+491701234567')).toBeVisible();
    await expect(scannerTabPanel.getByRole('heading', { name: 'Shifts' })).toBeVisible();
    await expect(scannerTabPanel.getByRole('heading', { name: 'Gear' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Attendance' }).first()).toBeVisible();
    await expect(page.getByRole('button', { name: 'Pick Up E2E Vest' })).toBeVisible();

    await page.getByRole('button', { name: 'Attendance' }).first().click();
    await page.getByRole('tab', { name: 'Schichtliste' }).click();

    const lisaRecordedRow = page.locator('#tabpanel-shifts button').filter({ hasText: 'Lisa Mueller' }).first();
    await expect(lisaRecordedRow).toContainText('Recorded');
});

test('volunteer admin shift list disables Jetzt when all visible shifts are over', async ({ page }) => {
    await page.addInitScript(() => {
        Object.defineProperty(HTMLMediaElement.prototype, 'play', {
            configurable: true,
            value: async () => undefined,
        });

        Object.defineProperty(navigator, 'mediaDevices', {
            configurable: true,
            value: {
                getUserMedia: async () => new MediaStream(),
            },
        });
    });

    await page.goto('/s/e2e-va-all-past-token');

    await page.getByLabel('Enter 6-digit code').fill('444444');
    await page.getByRole('button', { name: 'Authenticate' }).click();

    await expect(page).toHaveURL(/\/s\/e2e-va-all-past-token\/scan$/);

    await page.getByRole('tab', { name: 'Schichtliste' }).click();

    await expect(page.getByText('No active or upcoming shifts.')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Jetzt' })).toBeDisabled();
});
