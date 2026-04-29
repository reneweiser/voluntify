import { getOutboxEntries, deleteOutboxEntries } from './idb-store';

export class ScannerContractVersionError extends Error {
    constructor(message: string) {
        super(message);
        this.name = 'ScannerContractVersionError';
    }
}

function getHeaders(scannerToken: string): Record<string, string> {
    return {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Scanner-Token': scannerToken,
    };
}

export async function syncOutbox(
    scannerId: number,
    syncUrl: string,
    scannerToken: string,
    guestSyncUrl?: string,
): Promise<void> {
    const entries = await getOutboxEntries(scannerId);

    if (entries.length === 0) {
        return;
    }

    const headers = getHeaders(scannerToken);

    // Sync arrivals
    const arrivals = entries.filter((e) => !e.type || e.type === 'arrival');
    if (arrivals.length > 0) {
        const contractVersion = getArrivalContractVersion(arrivals);

        try {
            const response = await fetch(syncUrl, {
                method: 'POST',
                headers,
                body: JSON.stringify({
                    contract_version: contractVersion,
                    arrivals: arrivals.map((e) => ({
                        ticket_id: e.ticket_id,
                        event_id: e.entry_event_id ?? e.event_id,
                        method: e.method,
                        scanned_at: e.scanned_at,
                    })),
                }),
            });

            if (response.ok) {
                await deleteOutboxEntries(arrivals.map((e) => e.localId));
            }
        } catch {
            // Network error — will retry next sync
        }
    }

    // Sync guest check-ins
    const guestCheckins = entries.filter((e) => e.type === 'guest_checkin');
    if (guestCheckins.length > 0 && guestSyncUrl) {
        try {
            const response = await fetch(guestSyncUrl, {
                method: 'POST',
                headers,
                body: JSON.stringify({
                    guest_checkins: guestCheckins.map((e) => ({
                        guest_entry_id: e.guest_entry_id,
                        checked_in_at: e.scanned_at,
                    })),
                }),
            });

            if (response.ok) {
                await deleteOutboxEntries(guestCheckins.map((e) => e.localId));
            }
        } catch {
            // Network error — will retry next sync
        }
    }
}

function getArrivalContractVersion(entries: Array<{ contract_version?: number; entry_event_id?: number | null }>): number {
    const contractVersions = new Set<number>();

    for (const entry of entries) {
        if (typeof entry.contract_version !== 'number' || typeof entry.entry_event_id !== 'number') {
            throw new ScannerContractVersionError('Queued arrivals require fresh scanner data before they can be synced.');
        }

        contractVersions.add(entry.contract_version);
    }

    if (contractVersions.size !== 1) {
        throw new ScannerContractVersionError('Queued arrivals were created with different scanner contracts.');
    }

    return [...contractVersions][0];
}
