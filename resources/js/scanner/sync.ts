import { getOutboxEntries, clearOutbox } from './idb-store';

export async function syncOutbox(eventId: number, syncUrl: string): Promise<void> {
    const entries = await getOutboxEntries(eventId);

    if (entries.length === 0) {
        return;
    }

    try {
        const response = await fetch(syncUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                arrivals: entries.map((e) => ({
                    ticket_id: e.ticket_id,
                    method: e.method,
                    scanned_at: e.scanned_at,
                })),
            }),
        });

        if (response.ok) {
            await clearOutbox(eventId);
        }
    } catch {
        // Network error — keep entries in outbox for next sync attempt
    }
}
