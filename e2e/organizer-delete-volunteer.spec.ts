import { expect, test } from '@playwright/test';

test('organizer can delete a volunteer profile from event detail', async ({ page }) => {
    await page.goto('/login');

    await page.getByPlaceholder('email@example.com').fill('test@example.com');
    await page.getByPlaceholder('Password').fill('password');
    await page.getByRole('button', { name: 'Log in' }).click();

    await expect(page).toHaveURL(/\/admin\/dashboard$/);

    await page.goto('/admin/events');
    await page.getByRole('link', { name: 'Spring Community Fair' }).click();

    await expect(page).toHaveURL(/\/admin\/events\/\d+$/);

    await page.goto(`${page.url()}/volunteers`);

    await expect(page).toHaveURL(/\/admin\/events\/\d+\/volunteers$/);

    await page.getByPlaceholder('Search volunteers...').fill('E2E Delete Volunteer');
    await page.getByRole('link', { name: 'E2E Delete Volunteer' }).click();

    await expect(page).toHaveURL(/\/admin\/events\/\d+\/volunteers\/\d+$/);
    await expect(page.getByText('E2E Delete Volunteer').first()).toBeVisible();

    await page.getByRole('button', { name: 'Volunteer löschen' }).click();

    await expect(page.getByText('Dieses Event öffnet nur den Einstiegspunkt.')).toBeVisible();

    await page.getByRole('checkbox', { name: 'Ich bestätige die endgültige Löschung des gesamten Volunteer-Profils.' }).check();
    await page.getByRole('button', { name: 'Volunteer endgültig löschen' }).click();

    await expect(page).toHaveURL(/\/admin\/events\/\d+\/volunteers$/);

    await page.getByPlaceholder('Search volunteers...').fill('E2E Delete Volunteer');
    await expect(page.getByText('No volunteers match your search.')).toBeVisible();
});
