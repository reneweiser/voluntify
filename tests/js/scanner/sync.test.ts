import 'fake-indexeddb/auto';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { syncOutbox } from '@/scanner/sync';
import { openScannerDb, addOutboxEntry, getOutboxEntries, resetDb } from '@/scanner/idb-store';

describe('sync (M11 — scanner token auth)', () => {
    beforeEach(async () => {
        resetDb();
        await new Promise<void>((resolve, reject) => {
            const req = indexedDB.deleteDatabase('voluntify-scanner');
            req.onsuccess = () => resolve();
            req.onerror = () => reject(req.error);
        });
        await openScannerDb();
    });

    it('POSTs outbox entries to sync endpoint with scanner token', async () => {
        await addOutboxEntry(1, {
            type: 'arrival',
            ticket_id: 10,
            volunteer_id: 1,
            method: 'qr_scan',
            scanned_at: '2026-03-02 10:00:00',
        });

        const mockFetch = vi.fn().mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ arrivals: [] }),
        });
        vi.stubGlobal('fetch', mockFetch);

        await syncOutbox(1, '/api/scanner/1/sync', 'test-scanner-token');

        expect(mockFetch).toHaveBeenCalledOnce();
        const [url, options] = mockFetch.mock.calls[0];
        expect(url).toBe('/api/scanner/1/sync');
        expect(options.method).toBe('POST');
        expect(options.headers['X-Scanner-Token']).toBe('test-scanner-token');

        const body = JSON.parse(options.body);
        expect(body.arrivals).toHaveLength(1);
        expect(body.arrivals[0].ticket_id).toBe(10);

        vi.unstubAllGlobals();
    });

    it('clears synced items from outbox', async () => {
        await addOutboxEntry(1, {
            type: 'arrival',
            ticket_id: 10,
            volunteer_id: 1,
            method: 'qr_scan',
            scanned_at: '2026-03-02 10:00:00',
        });

        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ arrivals: [] }),
        }));

        await syncOutbox(1, '/api/scanner/1/sync', 'test-token');

        const remaining = await getOutboxEntries(1);
        expect(remaining).toHaveLength(0);

        vi.unstubAllGlobals();
    });

    it('keeps items on fetch failure', async () => {
        await addOutboxEntry(1, {
            type: 'arrival',
            ticket_id: 10,
            volunteer_id: 1,
            method: 'qr_scan',
            scanned_at: '2026-03-02 10:00:00',
        });

        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('Network error')));

        await syncOutbox(1, '/api/scanner/1/sync', 'test-token');

        const remaining = await getOutboxEntries(1);
        expect(remaining).toHaveLength(1);

        vi.unstubAllGlobals();
    });

    it('includes X-Scanner-Token header (no CSRF)', async () => {
        await addOutboxEntry(1, {
            type: 'arrival',
            ticket_id: 10,
            volunteer_id: 1,
            method: 'qr_scan',
            scanned_at: '2026-03-02 10:00:00',
        });

        const mockFetch = vi.fn().mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ arrivals: [] }),
        });
        vi.stubGlobal('fetch', mockFetch);

        await syncOutbox(1, '/api/scanner/1/sync', 'my-scanner-token');

        const [, options] = mockFetch.mock.calls[0];
        expect(options.headers['X-Scanner-Token']).toBe('my-scanner-token');
        expect(options.headers).not.toHaveProperty('X-CSRF-TOKEN');

        vi.unstubAllGlobals();
    });

    it('handles empty outbox (no-op)', async () => {
        const mockFetch = vi.fn();
        vi.stubGlobal('fetch', mockFetch);

        await syncOutbox(1, '/api/scanner/1/sync', 'test-token');

        expect(mockFetch).not.toHaveBeenCalled();

        vi.unstubAllGlobals();
    });
});
