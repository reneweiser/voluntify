import type { Volunteer, ScannerKeys, OutboxEntry, AttendanceRecord } from './types';

const DB_NAME = 'voluntify-scanner';
const DB_VERSION = 2;

let dbInstance: IDBDatabase | null = null;

export function openScannerDb(): Promise<IDBDatabase> {
    return new Promise((resolve, reject) => {
        if (dbInstance) {
            resolve(dbInstance);
            return;
        }

        const request = indexedDB.open(DB_NAME, DB_VERSION);

        request.onupgradeneeded = () => {
            const db = request.result;

            if (!db.objectStoreNames.contains('volunteers')) {
                const store = db.createObjectStore('volunteers', { keyPath: ['eventId', 'id'] });
                store.createIndex('byEvent', 'eventId', { unique: false });
            }

            if (!db.objectStoreNames.contains('outbox')) {
                const store = db.createObjectStore('outbox', { keyPath: 'localId', autoIncrement: true });
                store.createIndex('byEvent', 'eventId', { unique: false });
            }

            if (!db.objectStoreNames.contains('keys')) {
                db.createObjectStore('keys', { keyPath: 'eventId' });
            }

            if (!db.objectStoreNames.contains('attendance')) {
                const store = db.createObjectStore('attendance', { keyPath: ['eventId', 'id'] });
                store.createIndex('byEvent', 'eventId', { unique: false });
            }
        };

        request.onsuccess = () => {
            dbInstance = request.result;
            resolve(dbInstance);
        };

        request.onerror = () => reject(request.error);
    });
}

function getDb(): Promise<IDBDatabase> {
    return openScannerDb();
}

function tx(storeName: string, mode: IDBTransactionMode): Promise<IDBObjectStore> {
    return getDb().then((db) => {
        const transaction = db.transaction(storeName, mode);
        return transaction.objectStore(storeName);
    });
}

function reqToPromise<T>(request: IDBRequest<T>): Promise<T> {
    return new Promise((resolve, reject) => {
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

export async function storeVolunteers(eventId: number, volunteers: Volunteer[]): Promise<void> {
    const store = await tx('volunteers', 'readwrite');

    // Clear existing volunteers for this event
    const index = store.index('byEvent');
    const existingKeys = await reqToPromise(index.getAllKeys(eventId));
    for (const key of existingKeys) {
        store.delete(key);
    }

    // Add new volunteers
    for (const v of volunteers) {
        store.put({ ...v, eventId });
    }
}

export async function getVolunteers(eventId: number): Promise<Volunteer[]> {
    const store = await tx('volunteers', 'readonly');
    const index = store.index('byEvent');
    const results = await reqToPromise(index.getAll(eventId));
    return results.map(({ eventId: _, ...volunteer }) => volunteer as Volunteer);
}

export async function searchVolunteers(eventId: number, query: string): Promise<Volunteer[]> {
    const volunteers = await getVolunteers(eventId);
    const lowerQuery = query.toLowerCase();
    return volunteers.filter((v) => v.name.toLowerCase().includes(lowerQuery));
}

export async function storeKeys(eventId: number, keys: ScannerKeys): Promise<void> {
    const store = await tx('keys', 'readwrite');
    await reqToPromise(store.put({ eventId, ...keys }));
}

export async function getKeys(eventId: number): Promise<ScannerKeys | null> {
    const store = await tx('keys', 'readonly');
    const result = await reqToPromise(store.get(eventId));
    if (!result) {
        return null;
    }
    return { current: result.current, previous: result.previous };
}

export async function addOutboxEntry(eventId: number, entry: OutboxEntry): Promise<void> {
    const store = await tx('outbox', 'readwrite');
    await reqToPromise(store.add({ ...entry, eventId }));
}

export async function getOutboxEntries(eventId: number): Promise<OutboxEntry[]> {
    const store = await tx('outbox', 'readonly');
    const index = store.index('byEvent');
    return reqToPromise(index.getAll(eventId));
}

export async function clearOutbox(eventId: number): Promise<void> {
    const store = await tx('outbox', 'readwrite');
    const index = store.index('byEvent');
    const keys = await reqToPromise(index.getAllKeys(eventId));
    for (const key of keys) {
        store.delete(key);
    }
}

export async function getOutboxCount(eventId: number): Promise<number> {
    const store = await tx('outbox', 'readonly');
    const index = store.index('byEvent');
    return reqToPromise(index.count(eventId));
}

export async function storeAttendanceRecords(eventId: number, records: AttendanceRecord[]): Promise<void> {
    const store = await tx('attendance', 'readwrite');

    const index = store.index('byEvent');
    const existingKeys = await reqToPromise(index.getAllKeys(eventId));
    for (const key of existingKeys) {
        store.delete(key);
    }

    for (const r of records) {
        store.put({ ...r, eventId });
    }
}

export async function getAttendanceRecords(eventId: number): Promise<AttendanceRecord[]> {
    const store = await tx('attendance', 'readonly');
    const index = store.index('byEvent');
    const results = await reqToPromise(index.getAll(eventId));
    return results.map(({ eventId: _, ...record }) => record as AttendanceRecord);
}

/** Reset the cached db instance (for testing). */
export function resetDb(): void {
    if (dbInstance) {
        dbInstance.close();
        dbInstance = null;
    }
}
