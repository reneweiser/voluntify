import type { Volunteer, ScannerKeys, OutboxEntry, AttendanceRecord, GuestEntry } from './types';

const DB_NAME = 'voluntify-scanner';
const DB_VERSION = 4;

let dbInstance: IDBDatabase | null = null;

export function openScannerDb(): Promise<IDBDatabase> {
    return new Promise((resolve, reject) => {
        if (dbInstance) {
            resolve(dbInstance);
            return;
        }

        const request = indexedDB.open(DB_NAME, DB_VERSION);

        request.onupgradeneeded = (event) => {
            const db = request.result;
            const oldVersion = (event as IDBVersionChangeEvent).oldVersion;

            if (oldVersion < 3) {
                // Full rebuild for versions before 3
                for (const name of Array.from(db.objectStoreNames)) {
                    db.deleteObjectStore(name);
                }

                const volStore = db.createObjectStore('volunteers', { keyPath: ['scannerId', 'id'] });
                volStore.createIndex('byScanner', 'scannerId', { unique: false });

                const outboxStore = db.createObjectStore('outbox', { keyPath: 'localId', autoIncrement: true });
                outboxStore.createIndex('byScanner', 'scannerId', { unique: false });

                db.createObjectStore('keys', { keyPath: 'scannerId' });

                const attStore = db.createObjectStore('attendance', { keyPath: ['scannerId', 'id'] });
                attStore.createIndex('byScanner', 'scannerId', { unique: false });
            }

            if (oldVersion < 4) {
                if (!db.objectStoreNames.contains('guest_entries')) {
                    const guestStore = db.createObjectStore('guest_entries', { keyPath: ['scannerId', 'id'] });
                    guestStore.createIndex('byScanner', 'scannerId', { unique: false });
                }
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

export async function storeVolunteers(scannerId: number, volunteers: Volunteer[]): Promise<void> {
    const store = await tx('volunteers', 'readwrite');

    // Clear existing volunteers for this scanner
    const index = store.index('byScanner');
    const existingKeys = await reqToPromise(index.getAllKeys(scannerId));
    for (const key of existingKeys) {
        store.delete(key);
    }

    // Add new volunteers
    for (const v of volunteers) {
        store.put({ ...v, scannerId });
    }
}

export async function getVolunteers(scannerId: number): Promise<Volunteer[]> {
    const store = await tx('volunteers', 'readonly');
    const index = store.index('byScanner');
    const results = await reqToPromise(index.getAll(scannerId));
    return results.map(({ scannerId: _, ...volunteer }) => volunteer as Volunteer);
}

export async function searchVolunteers(scannerId: number, query: string): Promise<Volunteer[]> {
    const volunteers = await getVolunteers(scannerId);
    const lowerQuery = query.toLowerCase();
    return volunteers.filter((v) => v.name.toLowerCase().includes(lowerQuery));
}

export async function storeKeys(scannerId: number, keys: ScannerKeys): Promise<void> {
    const store = await tx('keys', 'readwrite');
    await reqToPromise(store.put({ scannerId, ...keys }));
}

export async function getKeys(scannerId: number): Promise<ScannerKeys | null> {
    const store = await tx('keys', 'readonly');
    const result = await reqToPromise(store.get(scannerId));
    if (!result) {
        return null;
    }
    return { current: result.current, previous: result.previous };
}

export async function addOutboxEntry(scannerId: number, entry: OutboxEntry): Promise<void> {
    const store = await tx('outbox', 'readwrite');
    await reqToPromise(store.add({ ...entry, scannerId }));
}

export async function getOutboxEntries(scannerId: number): Promise<(OutboxEntry & { localId: number })[]> {
    const store = await tx('outbox', 'readonly');
    const index = store.index('byScanner');
    return reqToPromise(index.getAll(scannerId));
}

export async function clearOutbox(scannerId: number): Promise<void> {
    const store = await tx('outbox', 'readwrite');
    const index = store.index('byScanner');
    const keys = await reqToPromise(index.getAllKeys(scannerId));
    for (const key of keys) {
        store.delete(key);
    }
}

export async function getOutboxCount(scannerId: number): Promise<number> {
    const store = await tx('outbox', 'readonly');
    const index = store.index('byScanner');
    return reqToPromise(index.count(scannerId));
}

export async function storeAttendanceRecords(scannerId: number, records: AttendanceRecord[]): Promise<void> {
    const store = await tx('attendance', 'readwrite');

    const index = store.index('byScanner');
    const existingKeys = await reqToPromise(index.getAllKeys(scannerId));
    for (const key of existingKeys) {
        store.delete(key);
    }

    for (const r of records) {
        store.put({ ...r, scannerId });
    }
}

export async function getAttendanceRecords(scannerId: number): Promise<AttendanceRecord[]> {
    const store = await tx('attendance', 'readonly');
    const index = store.index('byScanner');
    const results = await reqToPromise(index.getAll(scannerId));

    return results.map(({ scannerId: _, ...record }) => record as AttendanceRecord);
}

export async function storeGuestEntries(scannerId: number, entries: GuestEntry[]): Promise<void> {
    const store = await tx('guest_entries', 'readwrite');
    const index = store.index('byScanner');
    const existingKeys = await reqToPromise(index.getAllKeys(scannerId));
    for (const key of existingKeys) {
        store.delete(key);
    }
    for (const entry of entries) {
        store.put({ ...entry, scannerId });
    }
}

export async function getGuestEntries(scannerId: number): Promise<GuestEntry[]> {
    const store = await tx('guest_entries', 'readonly');
    const index = store.index('byScanner');
    const results = await reqToPromise(index.getAll(scannerId));
    return results.map(({ scannerId: _, ...entry }) => entry as GuestEntry);
}

export async function searchGuestEntries(scannerId: number, query: string): Promise<GuestEntry[]> {
    const entries = await getGuestEntries(scannerId);
    const lowerQuery = query.toLowerCase();
    return entries.filter(
        (e) =>
            (e.name && e.name.toLowerCase().includes(lowerQuery)) ||
            e.group_label.toLowerCase().includes(lowerQuery)
    );
}

export async function deleteOutboxEntries(localIds: number[]): Promise<void> {
    const store = await tx('outbox', 'readwrite');
    for (const id of localIds) {
        store.delete(id);
    }
}

/** Reset the cached db instance (for testing). */
export function resetDb(): void {
    if (dbInstance) {
        dbInstance.close();
        dbInstance = null;
    }
}
