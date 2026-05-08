import { expect, test, type Page } from '@playwright/test';

import { loadFixtures } from './fixtures.js';

type Fixtures = {
    scannerAuthBerlinToken: string;
    scannerAuthBerlinStartLabel: string;
    scannerAuthUtcToken: string;
    scannerAuthUtcStartLabel: string;
};

test('scheduled scanner auth shows the project timezone start time', async ({ page }) => {
    const fixtures = await loadFixtures<Fixtures>();

    await page.goto(`/s/${fixtures.scannerAuthBerlinToken}`);

    await expect(page.getByText(`Scanner ist noch nicht aktiv. Das Zeitfenster beginnt um ${fixtures.scannerAuthBerlinStartLabel}.`)).toBeVisible();
    await expect(page.getByRole('heading', { name: 'E2E Berlin Scheduled Scanner' })).toBeVisible();
});

test('scheduled scanner auth falls back to UTC when the project timezone is empty', async ({ page }) => {
    const fixtures = await loadFixtures<Fixtures>();

    await page.goto(`/s/${fixtures.scannerAuthUtcToken}`);

    await expect(page.getByText(`Scanner ist noch nicht aktiv. Das Zeitfenster beginnt um ${fixtures.scannerAuthUtcStartLabel}.`)).toBeVisible();
    await expect(page.getByRole('heading', { name: 'E2E UTC Scheduled Scanner' })).toBeVisible();
});
