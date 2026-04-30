import { expect, test } from '@playwright/test';

test.use({
    viewport: { width: 430, height: 720 },
});

test('volunteer admin scanner skips the entry arrival duplicate status after QR scan', async ({ page }) => {
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

    await expect.poll(async () => {
        return page.locator('[x-data]').evaluate((element) => {
            const component = (element as HTMLElement & { _x_dataStack?: Array<any> })._x_dataStack?.[0];

            return component?._volunteers.find((entry: { email: string }) => entry.email === 'lisa-shifts@example.com')?.ticket?.jwt_token ?? '';
        });
    }).toBeTruthy();

    const qrToken = await page.locator('[x-data]').evaluate((element) => {
        const component = (element as HTMLElement & { _x_dataStack?: Array<any> })._x_dataStack?.[0];

        return component?._volunteers.find((entry: { email: string }) => entry.email === 'lisa-shifts@example.com')?.ticket?.jwt_token ?? '';
    });

    const scannerState = await page.locator('[x-data]').evaluate(async (element, token) => {
        const component = (element as HTMLElement & { _x_dataStack?: Array<any> })._x_dataStack?.[0];

        if (!component) {
            throw new Error('Scanner Alpine component not found.');
        }

        await component._onQrDetected(token);

        return {
            state: component.state,
            resultMessage: component.resultMessage,
        };
    }, qrToken);

    expect(scannerState).toEqual({
        state: 'result',
        resultMessage: '',
    });

    const scannerTab = page.locator('#tabpanel-va-scanner');

    await expect(scannerTab.getByText('Lisa Mueller').first()).toBeVisible();
    await expect(scannerTab.getByText('lisa-shifts@example.com')).toBeVisible();
    await expect(scannerTab.getByRole('heading', { name: 'Shifts' })).toBeVisible();
    await expect(scannerTab.getByRole('heading', { name: 'Gear' })).toBeVisible();
    await expect(scannerTab.locator('[role="alert"]')).not.toBeVisible();
    await expect(scannerTab.getByText(/Already checked in at/i)).toHaveCount(0);
});
