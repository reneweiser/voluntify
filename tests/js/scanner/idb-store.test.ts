import 'fake-indexeddb/auto';
import { describe, it, expect, beforeEach } from 'vitest';
import {
    openScannerDb,
    storeVolunteers,
    getVolunteers,
    searchVolunteers,
    storeKeys,
    getKeys,
    storeAttendanceRecords,
    getAttendanceRecords,
    addOutboxEntry,
    getOutboxEntries,
    clearOutbox,
    getOutboxCount,
    resetDb,
} from '@/scanner/idb-store';
import type { Volunteer, ScannerKeys, OutboxEntry } from '@/scanner/types';

const makeVolunteer = (overrides: Partial<Volunteer> = {}): Volunteer => ({
    id: 1,
    first_name: 'Alice',
    last_name: 'Johnson',
    name: 'Alice Johnson',
    email: 'alice@example.com',
    phone: null,
    ticket: { id: 10, jwt_token: 'eyJabc', volunteer_id: 1, project_id: 1 },
    shift_signups: [],
    ...overrides,
});

describe('idb-store (DB_VERSION 3 — scannerId keys)', () => {
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

    it('stores and retrieves volunteers by scannerId', async () => {
        const volunteers = [makeVolunteer(), makeVolunteer({ id: 2, first_name: 'Bob', last_name: 'Smith', name: 'Bob Smith', email: 'bob@example.com' })];
        await storeVolunteers(1, volunteers);

        const result = await getVolunteers(1);
        expect(result).toHaveLength(2);
        expect(result[0].name).toBe('Alice Johnson');
        expect(result[1].name).toBe('Bob Smith');
    });

    it('isolates volunteers by scannerId', async () => {
        await storeVolunteers(1, [makeVolunteer()]);
        await storeVolunteers(2, [makeVolunteer({ id: 2, first_name: 'Bob', last_name: 'Smith', name: 'Bob Smith', email: 'bob@example.com' })]);

        const scanner1 = await getVolunteers(1);
        const scanner2 = await getVolunteers(2);
        expect(scanner1).toHaveLength(1);
        expect(scanner1[0].name).toBe('Alice Johnson');
        expect(scanner2).toHaveLength(1);
        expect(scanner2[0].name).toBe('Bob Smith');
    });

    it('searches volunteers by first and last name substrings', async () => {
        await storeVolunteers(1, [
            makeVolunteer(),
            makeVolunteer({ id: 2, first_name: 'Bob', last_name: 'Smith', name: 'Bob Smith', email: 'bob@example.com' }),
        ]);

        await expect(searchVolunteers(1, 'ali')).resolves.toMatchObject([
            expect.objectContaining({ name: 'Alice Johnson' }),
        ]);

        await expect(searchVolunteers(1, 'SMI')).resolves.toMatchObject([
            expect.objectContaining({ name: 'Bob Smith' }),
        ]);
    });

    it('searches volunteers by email substring', async () => {
        await storeVolunteers(1, [
            makeVolunteer(),
            makeVolunteer({ id: 2, first_name: 'Bob', last_name: 'Smith', name: 'Bob Smith', email: 'bob@example.com' }),
        ]);

        const result = await searchVolunteers(1, 'EXAMPLE.COM');

        expect(result).toHaveLength(2);
        expect(result.map((volunteer) => volunteer.name)).toEqual(['Alice Johnson', 'Bob Smith']);
    });

    it('adds and retrieves outbox entries by scannerId', async () => {
        const entry: OutboxEntry = {
            type: 'arrival',
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

    it('clears outbox entries for a specific scannerId', async () => {
        await addOutboxEntry(1, {
            type: 'arrival',
            ticket_id: 10,
            volunteer_id: 1,
            method: 'qr_scan',
            scanned_at: '2026-03-02 10:00:00',
        });
        await addOutboxEntry(1, {
            type: 'arrival',
            ticket_id: 11,
            volunteer_id: 2,
            method: 'manual_lookup',
            scanned_at: '2026-03-02 10:01:00',
        });

        await clearOutbox(1);
        const entries = await getOutboxEntries(1);
        expect(entries).toHaveLength(0);
    });

    it('stores and retrieves HMAC keys by scannerId', async () => {
        const keys: ScannerKeys = { current: 'key-current', previous: 'key-previous' };
        await storeKeys(1, keys);

        const result = await getKeys(1);
        expect(result).toEqual(keys);
    });

    it('tracks outbox count per scannerId', async () => {
        expect(await getOutboxCount(1)).toBe(0);

        await addOutboxEntry(1, {
            type: 'arrival',
            ticket_id: 10,
            volunteer_id: 1,
            method: 'qr_scan',
            scanned_at: '2026-03-02 10:00:00',
        });

        expect(await getOutboxCount(1)).toBe(1);

        await addOutboxEntry(1, {
            type: 'arrival',
            ticket_id: 11,
            volunteer_id: 2,
            method: 'qr_scan',
            scanned_at: '2026-03-02 10:01:00',
        });

        expect(await getOutboxCount(1)).toBe(2);
    });

    it('stores and retrieves attendance records by scannerId', async () => {
        await storeAttendanceRecords(1, [
            { id: 1, shift_signup_id: 10, status: 'on_time' },
            { id: 2, shift_signup_id: 11, status: 'late' },
        ]);

        const records = await getAttendanceRecords(1);

        expect(records).toHaveLength(2);
        expect(records[0].shift_signup_id).toBe(10);
        expect(records[1].status).toBe('late');
    });
});
