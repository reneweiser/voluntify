import 'fake-indexeddb/auto';
import { describe, it, expect, beforeEach } from 'vitest';
import {
    openScannerDb,
    storeVolunteers,
    getVolunteers,
    searchVolunteers,
    storeKeys,
    getKeys,
    addOutboxEntry,
    getOutboxEntries,
    clearOutbox,
    getOutboxCount,
    resetDb,
} from '@/scanner/idb-store';
import type { Volunteer, ScannerKeys, OutboxEntry } from '@/scanner/types';

const makeVolunteer = (overrides: Partial<Volunteer> = {}): Volunteer => ({
    id: 1,
    name: 'Alice Johnson',
    email: 'alice@example.com',
    ticket: { id: 10, jwt_token: 'eyJabc', volunteer_id: 1, event_id: 1 },
    shift_signups: [],
    ...overrides,
});

describe('idb-store', () => {
    beforeEach(async () => {
        // Delete database between tests for isolation
        resetDb();
        await new Promise<void>((resolve, reject) => {
            const req = indexedDB.deleteDatabase('voluntify-scanner');
            req.onsuccess = () => resolve();
            req.onerror = () => reject(req.error);
        });
        await openScannerDb();
    });

    it('stores and retrieves volunteers', async () => {
        const volunteers = [makeVolunteer(), makeVolunteer({ id: 2, name: 'Bob Smith', email: 'bob@example.com' })];
        await storeVolunteers(1, volunteers);

        const result = await getVolunteers(1);
        expect(result).toHaveLength(2);
        expect(result[0].name).toBe('Alice Johnson');
        expect(result[1].name).toBe('Bob Smith');
    });

    it('searches volunteers by name substring', async () => {
        await storeVolunteers(1, [
            makeVolunteer(),
            makeVolunteer({ id: 2, name: 'Bob Smith', email: 'bob@example.com' }),
        ]);

        const result = await searchVolunteers(1, 'ali');
        expect(result).toHaveLength(1);
        expect(result[0].name).toBe('Alice Johnson');
    });

    it('adds and retrieves outbox entries', async () => {
        const entry: OutboxEntry = {
            ticket_id: 10,
            volunteer_id: 1,
            method: 'qr_scan',
            scanned_at: '2026-03-02 10:00:00',
        };

        await addOutboxEntry(1, entry);
        const entries = await getOutboxEntries(1);
        expect(entries).toHaveLength(1);
        expect(entries[0].ticket_id).toBe(10);
    });

    it('clears outbox entries', async () => {
        await addOutboxEntry(1, {
            ticket_id: 10,
            volunteer_id: 1,
            method: 'qr_scan',
            scanned_at: '2026-03-02 10:00:00',
        });
        await addOutboxEntry(1, {
            ticket_id: 11,
            volunteer_id: 2,
            method: 'manual_lookup',
            scanned_at: '2026-03-02 10:01:00',
        });

        await clearOutbox(1);
        const entries = await getOutboxEntries(1);
        expect(entries).toHaveLength(0);
    });

    it('stores and retrieves HMAC keys', async () => {
        const keys: ScannerKeys = { current: 'key-current', previous: 'key-previous' };
        await storeKeys(1, keys);

        const result = await getKeys(1);
        expect(result).toEqual(keys);
    });

    it('tracks outbox count', async () => {
        expect(await getOutboxCount(1)).toBe(0);

        await addOutboxEntry(1, {
            ticket_id: 10,
            volunteer_id: 1,
            method: 'qr_scan',
            scanned_at: '2026-03-02 10:00:00',
        });

        expect(await getOutboxCount(1)).toBe(1);

        await addOutboxEntry(1, {
            ticket_id: 11,
            volunteer_id: 2,
            method: 'qr_scan',
            scanned_at: '2026-03-02 10:01:00',
        });

        expect(await getOutboxCount(1)).toBe(2);
    });
});
