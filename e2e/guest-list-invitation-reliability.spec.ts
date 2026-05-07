import { expect, test, type APIRequestContext, type Page } from '@playwright/test';

type Fixtures = {
    guestListReliabilityProjectId: number;
    guestListReliabilityGuestListId: number;
    guestListReliabilityDraftGuestListId: number;
    guestListReliabilitySentInviteEmail: string;
};

type MailpitAddress = {
    Address: string;
};

type MailpitMessage = {
    ID: string;
    To?: MailpitAddress[];
};

type MailpitInbox = {
    messages?: MailpitMessage[];
};

type MailpitMessageDetails = {
    HTML?: string;
    Text?: string;
};

const mailpitBaseUrl = (globalThis as typeof globalThis & {
    process?: { env?: Record<string, string | undefined> };
}).process?.env?.MAILPIT_BASE_URL ?? 'http://mailpit:8025';

async function fetchInbox(request: APIRequestContext): Promise<MailpitInbox> {
    const response = await request.get(`${mailpitBaseUrl}/api/v1/messages`);

    return await response.json() as MailpitInbox;
}

async function fetchMessagesForRecipient(request: APIRequestContext, recipient: string): Promise<MailpitMessage[]> {
    const inbox = await fetchInbox(request);

    return (inbox.messages ?? []).filter((entry) => {
        return entry.To?.some((address) => address.Address === recipient);
    });
}

async function waitForSingleMessageForRecipient(request: APIRequestContext, recipient: string): Promise<MailpitMessageDetails> {
    await expect.poll(async () => {
        return (await fetchMessagesForRecipient(request, recipient)).length;
    }, {
        message: `Expected exactly one Mailpit message for ${recipient}`,
    }).toBe(1);

    const [message] = await fetchMessagesForRecipient(request, recipient);

    expect(message, `Expected a Mailpit message for ${recipient}`).toBeTruthy();

    const response = await request.get(`${mailpitBaseUrl}/api/v1/message/${message!.ID}`);

    return await response.json() as MailpitMessageDetails;
}

function extractUrls(content: string | undefined, pattern: RegExp): string[] {
    if (!content) {
        return [];
    }

    const normalizedContent = content.replace(/&amp;/g, '&');

    return Array.from(normalizedContent.matchAll(pattern), (match: RegExpMatchArray) => match[0]);
}

async function login(page: Page): Promise<void> {
    await page.goto('/login');
    await page.getByPlaceholder('email@example.com').fill('test@example.com');
    await page.getByPlaceholder('Password').fill('password');
    await page.getByRole('button', { name: 'Log in' }).click();
    await expect(page).toHaveURL(/\/admin\/dashboard$/);
}

async function loadFixtures(page: Page): Promise<Fixtures> {
    await page.goto('/e2e-fixtures.json');

    return JSON.parse(await page.locator('body').innerText()) as Fixtures;
}

test('organizer can see failed invitation groups, resend them, and repair a failed address inline', async ({ page, request }) => {
    const fixtures = await loadFixtures(page);

    await login(page);
    await page.goto(`/admin/projects/${fixtures.guestListReliabilityProjectId}/guest-lists/${fixtures.guestListReliabilityGuestListId}`);

    await expect(page.getByRole('button', { name: 'Send Pending Invitations (1)' })).toBeVisible();

    const retryRow = page.locator('tr').filter({ hasText: 'retry@example.com' }).first();
    await expect(retryRow.getByText('Failed')).toBeVisible();
    await retryRow.getByRole('button', { name: 'Resend' }).click();
    await expect(page.getByText('Invitation resend queued for retry@example.com.')).toBeVisible();
    await expect(page.locator('tr').filter({ hasText: 'retry@example.com' }).first()).toContainText(/Queued|Sent/);

    const retryInvitation = await waitForSingleMessageForRecipient(request, 'retry@example.com');

    expect(retryInvitation.HTML).toContain('Open Guest Pass');

    const repairRow = page.locator('tr').filter({ hasText: 'repair@example.com' }).first();
    await expect(repairRow.getByText('Failed')).toBeVisible();
    await repairRow.getByTitle('Edit').click();
    await expect(page.getByPlaceholder('Email')).toBeFocused();
    await page.getByPlaceholder('Email').fill('repaired@example.com');
    await page.getByRole('button', { name: 'Save' }).click();
    await expect(page.getByText('Guest entry updated.')).toBeVisible();

    await expect(page.locator('tr').filter({ hasText: 'repaired@example.com' })).toHaveCount(2);
    await expect(page.locator('tr').filter({ hasText: 'repaired@example.com' }).first()).toContainText(/Queued|Sent/);

    const repairedInvitation = await waitForSingleMessageForRecipient(request, 'repaired@example.com');
    const repairedGuestPassUrls = extractUrls(
        repairedInvitation.HTML,
        /http:\/\/localhost\/guest-pass\/\d+\?expires=\d+&signature=[A-Za-z0-9%_-]+/g,
    );

    expect(repairedInvitation.HTML).toContain('Open Guest Pass');
    expect(repairedGuestPassUrls).toHaveLength(2);

    page.once('dialog', async (dialog) => {
        await dialog.accept();
    });
    await page.getByRole('button', { name: 'Send Pending Invitations (1)' }).click();

    await expect(page.getByText('Invitations queued for 1 recipients.')).toBeVisible();
    await expect(page.getByRole('button', { name: /Send Pending Invitations/ })).toHaveCount(0);

    const guestPassPage = await page.context().newPage();

    await guestPassPage.goto(repairedGuestPassUrls[0]);
    await expect(guestPassPage.getByText('Guest Pass')).toBeVisible();
    await expect(guestPassPage.getByText('E2E Guest Invitation Reliability')).toBeVisible();
    await expect(guestPassPage.getByText(/Repair Group [12]\/2/)).toBeVisible();
    await expect(guestPassPage.getByText(/Repair One|Repair Two/)).toBeVisible();
    await guestPassPage.close();
});

test('organizer sees sending-active wording on guest list badges and activation flow', async ({ page }) => {
    const fixtures = await loadFixtures(page);

    await login(page);
    await page.goto(`/admin/projects/${fixtures.guestListReliabilityProjectId}/guest-lists`);

    await expect(page.locator('div').filter({ hasText: 'E2E Guest Invitation Reliability' }).getByText('Sending active')).toBeVisible();
    await expect(page.locator('div').filter({ hasText: 'E2E Draft Guest Invitation List' }).getByText('Sending inactive')).toBeVisible();

    await page.goto(`/admin/projects/${fixtures.guestListReliabilityProjectId}/guest-lists/${fixtures.guestListReliabilityDraftGuestListId}`);

    await expect(page.getByText('Sending inactive')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Activate Sending' })).toBeVisible();

    page.once('dialog', async (dialog) => {
        expect(dialog.message()).toBe('Activate sending for this guest list? QR codes will be generated for all entries, and new guests with an email address will keep receiving invitations automatically.');
        await dialog.accept();
    });

    await page.getByRole('button', { name: 'Activate Sending' }).click();

    await expect(page.getByText('Guest list sending is now active. QR codes are being generated.')).toBeVisible();
    await expect(page.getByText('Sending active')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Activate Sending' })).toHaveCount(0);
});

test('guest invitation emails expose the browser fallback as a CTA button', async ({ page, request }) => {
    const fixtures = await loadFixtures(page);

    const invitation = await waitForSingleMessageForRecipient(request, fixtures.guestListReliabilitySentInviteEmail);
    const guestPassUrls = extractUrls(
        invitation.HTML,
        /http:\/\/localhost\/guest-pass\/\d+\?expires=\d+&signature=[A-Za-z0-9%_-]+/g,
    );

    expect(invitation.Text).toContain('If the QR code is not visible in your email app, open this pass in your browser.');
    expect(invitation.HTML).toContain('Open Guest Pass');
    expect(guestPassUrls.length).toBeGreaterThan(0);
});
