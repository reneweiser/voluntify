import { expect, test } from '@playwright/test';

type MailpitAddress = {
    Address: string;
};

type MailpitMessage = {
    Subject?: string;
    Snippet?: string;
    To?: MailpitAddress[];
};

type MailpitInbox = {
    messages?: MailpitMessage[];
};

const mailpitBaseUrl = (globalThis as typeof globalThis & {
    process?: { env?: Record<string, string | undefined> };
}).process?.env?.MAILPIT_BASE_URL ?? 'http://localhost:8025';

test('24-hour pre-shift reminders use today and tomorrow wording', async ({ request }) => {
    await expect.poll(async () => {
        const response = await request.get(`${mailpitBaseUrl}/api/v1/messages`);
        const inbox = await response.json() as MailpitInbox;

        return inbox.messages?.length ?? 0;
    }).toBeGreaterThanOrEqual(2);

    const response = await request.get(`${mailpitBaseUrl}/api/v1/messages`);
    const inbox = await response.json() as MailpitInbox;

    const todayReminder = inbox.messages?.find((message) => {
        return message.To?.some((recipient) => recipient.Address === 'reminder-today@example.com');
    });

    const tomorrowReminder = inbox.messages?.find((message) => {
        return message.To?.some((recipient) => recipient.Address === 'reminder-tomorrow@example.com');
    });

    expect(todayReminder?.Subject).toContain('ist heute');
    expect(todayReminder?.Snippet).toContain('heute stattfindet');

    expect(tomorrowReminder?.Subject).toContain('ist morgen');
    expect(tomorrowReminder?.Snippet).toContain('morgen stattfindet');
});
