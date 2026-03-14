import { getOutboxEntries, clearOutbox } from './idb-store';

function getHeaders(): Record<string, string> {
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const headers: Record<string, string> = {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };
    if (csrfMeta) {
        headers['X-CSRF-TOKEN'] = csrfMeta.getAttribute('content') ?? '';
    }
    return headers;
}

export async function syncOutbox(eventId: number, syncUrl: string, attendanceSyncUrl?: string): Promise<void> {
    const entries = await getOutboxEntries(eventId);

    if (entries.length === 0) {
        return;
    }

    const arrivals = entries.filter((e) => !e.type || e.type === 'arrival');
    const attendance = entries.filter((e) => e.type === 'attendance');

    const headers = getHeaders();
    let allSynced = true;

    if (arrivals.length > 0) {
        try {
            const response = await fetch(syncUrl, {
                method: 'POST',
                headers,
                credentials: 'same-origin',
                body: JSON.stringify({
                    arrivals: arrivals.map((e) => ({
                        ticket_id: e.ticket_id,
                        method: e.method,
                        scanned_at: e.scanned_at,
                    })),
                }),
            });

            if (!response.ok) {
                allSynced = false;
            }
        } catch {
            allSynced = false;
        }
    }

    if (attendance.length > 0 && attendanceSyncUrl) {
        try {
            const response = await fetch(attendanceSyncUrl, {
                method: 'POST',
                headers,
                credentials: 'same-origin',
                body: JSON.stringify({
                    attendance: attendance.map((e) => ({
                        shift_signup_id: e.shift_signup_id,
                        status: e.status,
                        scanned_at: e.scanned_at,
                    })),
                }),
            });

            if (!response.ok) {
                allSynced = false;
            }
        } catch {
            allSynced = false;
        }
    }

    if (allSynced) {
        await clearOutbox(eventId);
    }
}
