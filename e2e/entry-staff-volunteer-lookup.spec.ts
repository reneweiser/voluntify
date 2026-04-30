import { expect, test } from '@playwright/test';

test.use({
    viewport: { width: 430, height: 720 },
});

test('entry staff scanner supports volunteer manual lookup, confirm, duplicate badge, and cached reload', async ({ page }) => {
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

    await page.goto('/s/e2e-entry-manual-lookup-token');

    await page.getByLabel('Enter 6-digit code').fill('555555');
    await page.getByRole('button', { name: 'Authenticate' }).click();

    await expect(page).toHaveURL(/\/s\/e2e-entry-manual-lookup-token\/scan$/);
    await expect(page.getByRole('tab', { name: 'Scanner' })).toBeVisible();
    await expect(page.getByRole('tab', { name: 'Volunteers' })).toBeVisible();
    await expect(page.getByRole('tab', { name: 'Gastliste' })).toBeVisible();

    await page.getByRole('tab', { name: 'Volunteers' }).click();

    const volunteersTab = page.locator('#tabpanel-volunteers');
    const searchInput = page.getByPlaceholder('Search volunteers...');
    const volunteerRow = volunteersTab.getByRole('button').filter({ hasText: 'Past Lookup' });

    await searchInput.fill('P');
    await expect(volunteersTab.getByText('Mindestens 2 Zeichen eingeben.')).toBeVisible();

    await searchInput.fill('Past');
    await expect(volunteerRow).toBeVisible();
    await expect(volunteerRow).toContainText('past-manual-lookup@example.com');

    await volunteerRow.click();

    await expect(volunteersTab.getByText('Past Lookup').first()).toBeVisible();
    await expect(volunteersTab).toContainText('past-manual-lookup@example.com');
    await expect(volunteersTab).toContainText('+491701234999');
    await expect(volunteersTab.getByRole('button', { name: 'Confirm Arrival' })).toBeVisible();

    await volunteersTab.getByRole('button', { name: 'Confirm Arrival' }).click();

    await expect(volunteersTab.getByText('Already checked in.')).toBeVisible();
    await expect(volunteerRow.getByText('Bereits eingecheckt')).toBeVisible();

    await page.locator('[x-data]').evaluate(async (element) => {
        const component = (element as HTMLElement & { _x_dataStack?: Array<any> })._x_dataStack?.[0];

        if (!component) {
            throw new Error('Scanner Alpine component not found.');
        }

        component.isOnline = false;
        await component.reloadScannerData({ preserveUiState: true });
    });

    await expect(volunteersTab.getByText('Offline - showing cached volunteer data.')).toBeVisible();
    await expect(searchInput).toHaveValue('Past');
    await expect(volunteersTab.getByText('Already checked in.')).toBeVisible();
    await expect(volunteerRow).toBeVisible();
});
