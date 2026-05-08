import { expect, test, type Page } from '@playwright/test';

import { loadFixtures } from './fixtures.js';

type Fixtures = {
    deletionProjectId: number;
    deletionEventId: number;
    pendingDeletionProjectId: number;
    pendingDeletionEventId: number;
    pendingDeletionProjectDate: string;
    pendingDeletionEventDate: string;
};

async function login(page: Page): Promise<void> {
    await page.goto('/login');
    await page.getByPlaceholder('email@example.com').fill('test@example.com');
    await page.getByPlaceholder('Password').fill('password');
    await page.getByRole('button', { name: 'Log in' }).click();
    await expect(page).toHaveURL(/\/admin\/dashboard$/);
}

test('event deletion UI uses the 7-day retention copy', async ({ page }) => {
    const fixtures = await loadFixtures<Fixtures>();

    await login(page);
    await page.goto(`/admin/events/${fixtures.deletionEventId}`);

    await page.getByRole('button', { name: 'Löschen' }).click();
    await expect(page.getByText('Dieses Event wird in 7 Tagen endgültig gelöscht. Du kannst es in dieser Zeit jederzeit wiederherstellen.')).toBeVisible();
    await page.getByRole('button', { name: 'Abbrechen' }).click();

    await page.goto(`/admin/events/${fixtures.pendingDeletionEventId}`);
    await expect(page.getByText(`Dieses Event ist zur Löschung vorgemerkt und wird am ${fixtures.pendingDeletionEventDate} endgültig gelöscht.`)).toBeVisible();
});

test('project deletion UI uses the 7-day retention copy', async ({ page }) => {
    const fixtures = await loadFixtures<Fixtures>();

    await login(page);
    await page.goto(`/admin/projects/${fixtures.deletionProjectId}`);

    await page.getByRole('button', { name: 'Löschen' }).click();
    await expect(page.getByText('Das Projekt wird in 7 Tagen endgültig gelöscht. Du kannst es in dieser Zeit jederzeit wiederherstellen.')).toBeVisible();
    await page.getByRole('button', { name: 'Abbrechen' }).click();

    await page.goto(`/admin/projects/${fixtures.pendingDeletionProjectId}`);
    await expect(page.getByText(`Dieses Projekt ist zur Löschung vorgemerkt und wird am ${fixtures.pendingDeletionProjectDate} endgültig gelöscht.`)).toBeVisible();
});
