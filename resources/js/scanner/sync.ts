import { getOutboxEntries, clearOutbox } from './idb-store';

function getHeaders(scannerToken: string): Record<string, string> {
    return {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Scanner-Token': scannerToken,
    };
}

export async function syncOutbox(scannerId: number, syncUrl: string, scannerToken: string): Promise<void> {
    const entries = await getOutboxEntries(scannerId);

    if (entries.length === 0) {
        return;
    }

    const arrivals = entries.filter((e) => !e.type || e.type === 'arrival');

    const headers = getHeaders(scannerToken);
    let allSynced = true;

    if (arrivals.length > 0) {
        try {
            const response = await fetch(syncUrl, {
                method: 'POST',
                headers,
                body: JSON.stringify({
                    arrivals: arrivals.map((e) => ({
                        ticket_id: e.ticket_id,
                        event_id: e.event_id,
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

    if (allSynced) {
        await clearOutbox(scannerId);
    }
}
