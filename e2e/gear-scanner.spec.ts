import { expect, test } from '@playwright/test';

test.use({
    viewport: { width: 430, height: 720 },
});

test('gear scanner supports cached volunteer and guest lookup without check-in actions', async ({ page }) => {
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

    await page.goto('/s/e2e-gear-scanner-token');

    await page.getByLabel('Enter 6-digit code').fill('777777');
    await page.getByRole('button', { name: 'Authenticate' }).click();

    await expect(page).toHaveURL(/\/s\/e2e-gear-scanner-token\/scan$/);
    await expect(page.getByRole('tab', { name: 'Scanner' })).toBeVisible();
    await expect(page.getByRole('tab', { name: 'Volunteers' })).toBeVisible();
    await expect(page.getByRole('tab', { name: 'Guests' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Confirm Arrival' })).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Check In' })).toHaveCount(0);

    await page.getByRole('tab', { name: 'Volunteers' }).click();

    const volunteersTab = page.locator('#tabpanel-gear-volunteers');
    const volunteerSearch = page.getByPlaceholder('Search volunteers...');

    await volunteerSearch.fill('Gear');
    await expect(volunteersTab.getByRole('button').filter({ hasText: 'Gear Pool' })).toBeVisible();
    await volunteersTab.getByRole('button').filter({ hasText: 'Gear Pool' }).click();

    await expect(volunteersTab).toContainText('gear-volunteer@example.com');
    await expect(volunteersTab).toContainText('E2E Hoodie');
    await expect(volunteersTab).toContainText('Size: M');
    await expect(volunteersTab.getByRole('button', { name: 'Pick Up' })).toBeVisible();

    await page.locator('[x-data]').evaluate(async (element) => {
        const component = (element as HTMLElement & { _x_dataStack?: Array<any> })._x_dataStack?.[0];

        if (!component) {
            throw new Error('Scanner Alpine component not found.');
        }

        component.isOnline = false;
        await component.reloadScannerData({ preserveUiState: true });
    });

    await expect(volunteersTab.getByText('Offline - showing cached volunteer data.')).toBeVisible();
    await expect(volunteerSearch).toHaveValue('Gear');
    await expect(volunteersTab.getByRole('button').filter({ hasText: 'Gear Pool' })).toBeVisible();

    await page.locator('[x-data]').evaluate(async (element) => {
        const component = (element as HTMLElement & { _x_dataStack?: Array<any> })._x_dataStack?.[0];

        if (!component) {
            throw new Error('Scanner Alpine component not found.');
        }

        component.isOnline = true;
        await component.reloadScannerData({ preserveUiState: true });
    });

    await page.getByRole('tab', { name: 'Guests' }).click();

    const guestsTab = page.locator('#tabpanel-gear-guests');
    const guestSearch = page.getByPlaceholder('Search guests...');

    await guestSearch.fill('DJ');
    await page.waitForTimeout(400);
    await expect(guestsTab.getByRole('button').filter({ hasText: 'DJ Tester' })).toBeVisible();
    await guestsTab.getByRole('button').filter({ hasText: 'DJ Tester' }).click();

    await expect(guestsTab).toContainText('Artists');
    await expect(guestsTab).toContainText('DJ Tester');
    await expect(guestsTab).toContainText('E2E Wristband');
    await expect(guestsTab).toContainText('0 / 1 picked up');

    await guestsTab.getByRole('button', { name: 'Record Pickup' }).click();

    await expect(guestsTab).toContainText('1 / 1 picked up');
    await expect(page.getByRole('button', { name: 'Check In' })).toHaveCount(0);
});
