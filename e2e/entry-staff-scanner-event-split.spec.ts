import { expect, test } from '@playwright/test';

import { loadFixtures } from './fixtures.js';

test('organizer configures an entry staff scanner with separate entry and pool events', async ({ page }) => {
    await page.goto('/login');
    await page.getByPlaceholder('email@example.com').fill('test@example.com');
    await page.getByPlaceholder('Password').fill('password');
    await page.getByRole('button', { name: 'Log in' }).click();

    await expect(page).toHaveURL(/\/admin\/dashboard$/);

    const fixtures = await loadFixtures<{
        entryPoolProjectId: number;
        entryPoolEntryEventId: number;
        entryPoolPoolEventId: number;
    }>();

    await page.goto(`/admin/projects/${fixtures.entryPoolProjectId}/scanners`);
    await expect(page.getByRole('button', { name: 'New Scanner' })).toBeVisible();

    await page.getByRole('button', { name: 'New Scanner' }).click();
    await expect(page.getByPlaceholder('e.g. Eingang Nord')).toBeVisible();

    await page.getByPlaceholder('e.g. Eingang Nord').fill('Browser Config Scanner');
    await page.evaluate(({ entryEventId, poolEventId }) => {
        const root = Array.from(document.querySelectorAll('[wire\\:id]')).find((element) => {
            return element.textContent?.includes('Pool Events') && element.textContent?.includes('New Scanner');
        }) as HTMLElement | undefined;
        const componentId = root?.getAttribute('wire:id');
        const livewire = (window as Window & {
            Livewire?: { find: (id: string) => { set: (name: string, value: unknown) => void } };
        }).Livewire;

        if (!componentId || !livewire) {
            throw new Error('Livewire component not found.');
        }

        const component = livewire.find(componentId);
        component.set('form.entryEventId', poolEventId);
        component.set('form.poolEventIds', [entryEventId, poolEventId]);
    }, {
        entryEventId: fixtures.entryPoolEntryEventId,
        poolEventId: fixtures.entryPoolPoolEventId,
    });
    await page.getByLabel('Starts at').fill('2026-04-29T17:00');
    await page.getByLabel('Ends at').fill('2026-04-29T21:00');
    await page.getByRole('button', { name: 'Create' }).click();

    await expect(page.getByText('Browser Config Scanner')).toBeVisible();
    await expect(page.getByText('Entry Event: E2E Pool Event')).toBeVisible();
    await expect(page.getByText('Pool Events: E2E Entry Event, E2E Pool Event').last()).toBeVisible();
});
