import { expect, test, type APIRequestContext } from '@playwright/test';

type MailpitAddress = {
    Address: string;
};

type MailpitMessage = {
    ID: string;
    Subject?: string;
    Snippet?: string;
    To?: MailpitAddress[];
};

type MailpitInbox = {
    messages?: MailpitMessage[];
};

type MailpitMessageDetails = {
    Subject?: string;
    Text?: string;
    HTML?: string;
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

function extractFirstPortalUrl(content: string | undefined): string | null {
    if (!content) {
        return null;
    }

    const normalizedContent = content.replace(/&amp;/g, '&');
    const match = normalizedContent.match(/http:\/\/localhost\/my-portal\/[A-Za-z0-9]+/);

    return match ? match[0] : null;
}

test('pre-shift reminders include relative-day wording and portal CTA links', async ({ page, request }) => {
    const todayReminder = await waitForSingleMessageForRecipient(request, 'reminder-today@example.com');
    const tomorrowReminder = await waitForSingleMessageForRecipient(request, 'reminder-tomorrow@example.com');
    const soonReminder = await waitForSingleMessageForRecipient(request, 'reminder-soon@example.com');

    expect(todayReminder.Subject).toContain('ist heute');
    expect(todayReminder.Text).toContain('heute stattfindet');
    expect(todayReminder.HTML).toContain('Portal öffnen');
    expect(todayReminder.HTML).toMatch(/http:\/\/localhost\/my-portal\/[A-Za-z0-9]+/);

    expect(tomorrowReminder.Subject).toContain('ist morgen');
    expect(tomorrowReminder.Text).toContain('morgen stattfindet');
    expect(tomorrowReminder.HTML).toContain('Portal öffnen');
    expect(tomorrowReminder.HTML).toMatch(/http:\/\/localhost\/my-portal\/[A-Za-z0-9]+/);

    expect(soonReminder.Subject).toContain('beginnt bald');
    expect(soonReminder.Text).toContain('beginnt heute in wenigen Stunden');
    expect(soonReminder.HTML).toContain('Portal öffnen');
    expect(soonReminder.HTML).toMatch(/http:\/\/localhost\/my-portal\/[A-Za-z0-9]+/);

    const todayPortalUrl = extractFirstPortalUrl(todayReminder.HTML);

    expect(todayPortalUrl).not.toBeNull();

    await page.goto(todayPortalUrl!);
    await expect(page.getByRole('heading', { name: 'Your Portal' })).toBeVisible();
    await expect(page.getByText('Welcome back, Heute Erinnerung')).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Upcoming Shifts' })).toBeVisible();
    await expect(page.getByText('Today Reminder Job').first()).toBeVisible();
});
