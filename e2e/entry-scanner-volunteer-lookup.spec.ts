import { expect, test, type Page } from '@playwright/test';

test.use({
    viewport: { width: 430, height: 820 },
});

async function prepareScannerPage(page: Page) {
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
}

test('entry staff volunteer lookup supports past-event volunteer check-in and offline cached search', async ({ page, context }) => {
    await prepareScannerPage(page);

    await page.goto('/s/e2e-entry-volunteer-lookup-token');
    await page.getByLabel('Enter 6-digit code').fill('333333');
    await page.getByRole('button', { name: 'Authenticate' }).click();

    await expect(page).toHaveURL(/\/s\/e2e-entry-volunteer-lookup-token\/scan$/);

    await page.getByRole('tab', { name: 'Volunteers' }).click();

    const searchInput = page.getByPlaceholder('Search volunteers...');
    await searchInput.fill('Past');

    const pastVolunteerRow = page.locator('#tabpanel-volunteers button').filter({ hasText: 'Past Volunteer' }).first();
    await expect(pastVolunteerRow).toBeVisible();
    await pastVolunteerRow.click();

    const volunteerPanel = page.locator('#tabpanel-volunteers').locator('div.rounded-xl.border.border-zinc-700.bg-zinc-800.p-4').last();
    await expect(volunteerPanel.getByText('Past Volunteer')).toBeVisible();
    await expect(volunteerPanel.getByText('entry-past@example.com')).toBeVisible();
    await expect(volunteerPanel.getByText('+491701010101')).toBeVisible();
    await expect(volunteerPanel.getByText('E2E Entry Past Event')).toBeVisible();
    await expect(volunteerPanel.getByText('Ready to check in.')).toHaveCount(0);

    await context.setOffline(true);
    await page.getByRole('button', { name: 'Confirm Arrival' }).click();

    await expect(volunteerPanel.getByText('Past Volunteer checked in successfully.')).toBeVisible();

    const manualArrivalEntries = await page.evaluate(async () => {
        const database = await new Promise<IDBDatabase>((resolve, reject) => {
            const request = indexedDB.open('voluntify-scanner');
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });

        const transaction = database.transaction('outbox', 'readonly');
        const store = transaction.objectStore('outbox');
        const rows = await new Promise<any[]>((resolve, reject) => {
            const request = store.getAll();
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });

        return rows.filter((row) => row.type === 'arrival' && row.method === 'manual_lookup');
    });

    expect(manualArrivalEntries).toHaveLength(1);
    expect(manualArrivalEntries[0].event_id).toBeGreaterThan(0);

    await page.getByRole('button', { name: 'Done' }).click();
    await searchInput.fill('Past');
    await expect(page.locator('#tabpanel-volunteers button').filter({ hasText: 'Past Volunteer' }).first()).toContainText('Bereits eingecheckt');

    await searchInput.fill('NoShift');
    await expect(page.locator('#tabpanel-volunteers button').filter({ hasText: 'NoShift Volunteer' }).first()).toBeVisible();
});

test('entry staff volunteer lookup requires event choice for ambiguous project-wide volunteers', async ({ page }) => {
    await prepareScannerPage(page);

    await page.goto('/s/e2e-entry-volunteer-lookup-token');
    await page.getByLabel('Enter 6-digit code').fill('333333');
    await page.getByRole('button', { name: 'Authenticate' }).click();

    await expect(page).toHaveURL(/\/s\/e2e-entry-volunteer-lookup-token\/scan$/);

    await page.getByRole('tab', { name: 'Volunteers' }).click();
    await page.getByPlaceholder('Search volunteers...').fill('Ambiguous');

    await page.locator('#tabpanel-volunteers button').filter({ hasText: 'Ambiguous Volunteer' }).first().click();

    const confirmButton = page.getByRole('button', { name: 'Confirm Arrival' });
    const eventSelect = page.locator('#tabpanel-volunteers select');

    await expect(page.getByText('Choose event')).toBeVisible();
    await expect(eventSelect).toContainText('E2E Entry Past Event');
    await expect(eventSelect).toContainText('E2E Entry Current Event');
    await expect(confirmButton).toBeDisabled();

    await eventSelect.selectOption({ label: 'E2E Entry Current Event' });
    await expect(confirmButton).toBeEnabled();
});
