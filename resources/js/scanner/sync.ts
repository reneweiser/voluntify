import { getOutboxEntries, clearOutbox } from './idb-store';

export async function syncOutbox(eventId: number, syncUrl: string): Promise<void> {
    const entries = await getOutboxEntries(eventId);

    if (entries.length === 0) {
        return;
    }

    try {
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        const headers: Record<string, string> = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        };
        if (csrfMeta) {
            headers['X-CSRF-TOKEN'] = csrfMeta.getAttribute('content') ?? '';
        }

        const response = await fetch(syncUrl, {
            method: 'POST',
            headers,
            credentials: 'same-origin',
            body: JSON.stringify({
                arrivals: entries.map((e) => ({
                    ticket_id: e.ticket_id,
                    method: e.method,
                    scanned_at: e.scanned_at,
                    ...(e.jwt_token ? { jwt_token: e.jwt_token } : {}),
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
