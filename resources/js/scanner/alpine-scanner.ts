/**
 * Alpine.js scannerApp component.
 *
 * Wires together: camera → JWT validation → IndexedDB lookup → result display → confirm → outbox → sync.
 *
 * State machine: idle → loading → scanning → result (new/duplicate/invalid) → confirmed
 */
import { startCamera, stopCamera } from './camera';
import {
    openScannerDb,
    storeVolunteers,
    storeKeys,
    getKeys,
    getVolunteers,
    addOutboxEntry,
    getOutboxCount,
} from './idb-store';
import { validateJwt } from './jwt-validator';
import { syncOutbox } from './sync';
import type { Volunteer, ArrivalRecord } from './types';

type ScannerState = 'idle' | 'loading' | 'scanning' | 'result' | 'duplicate' | 'invalid' | 'confirmed';

interface ScannerResult {
    name: string;
    email: string;
    volunteerId: number;
    ticketId: number;
}

export function scannerApp(config: { eventId: number }) {
    return {
        state: 'idle' as ScannerState,
        result: null as ScannerResult | null,
        errorMessage: '' as string,
        isOnline: navigator.onLine,
        outboxCount: 0,

        // Internal state
        _eventId: config.eventId,
        _volunteers: [] as Volunteer[],
        _arrivals: [] as ArrivalRecord[],
        _syncUrl: '',
        _dataUrl: '',
        _processing: false,
        _lastJwtToken: '' as string,

        async init() {
            await openScannerDb();

            // Compute API URLs
            this._dataUrl = `/admin/scanner/api/events/${this._eventId}/data`;
            this._syncUrl = `/admin/scanner/api/events/${this._eventId}/sync`;

            // Online/offline listeners
            window.addEventListener('online', () => {
                this.isOnline = true;
                this._sync();
            });
            window.addEventListener('offline', () => {
                this.isOnline = false;
            });

            // Load data
            this.state = 'loading';
            await this._loadEventData();

            // Start camera
            const video = (this as unknown as { $refs: Record<string, HTMLVideoElement | HTMLCanvasElement> }).$refs
                .video as HTMLVideoElement;
            const canvas = (this as unknown as { $refs: Record<string, HTMLVideoElement | HTMLCanvasElement> }).$refs
                .canvas as HTMLCanvasElement;

            if (video && canvas) {
                await startCamera(video, canvas, (data: string) => this._onQrDetected(data), (error: Error) => {
                    this.state = 'invalid';
                    this.errorMessage = `Camera error: ${error.message}`;
                });
                this.state = 'scanning';
            }
        },

        async _loadEventData() {
            if (this.isOnline) {
                try {
                    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                    const headers: Record<string, string> = {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    };
                    if (csrfMeta) {
                        headers['X-CSRF-TOKEN'] = csrfMeta.getAttribute('content') ?? '';
                    }

                    const response = await fetch(this._dataUrl, { headers, credentials: 'same-origin' });
                    if (response.ok) {
                        const data = await response.json();
                        this._volunteers = data.volunteers;
                        this._arrivals = data.arrivals;

                        // Persist to IndexedDB for offline use
                        await storeVolunteers(this._eventId, data.volunteers);
                        await storeKeys(this._eventId, data.keys);
                    }
                } catch {
                    // Network error — fall through to IDB cache
                }
            }

            // Fallback: load from IndexedDB
            if (this._volunteers.length === 0) {
                this._volunteers = await getVolunteers(this._eventId);
            }

            this.outboxCount = await getOutboxCount(this._eventId);
        },

        async _onQrDetected(jwtToken: string) {
            if (this._processing || this.state !== 'scanning') {
                return;
            }
            this._processing = true;

            try {
                // Get keys from IndexedDB
                const keys = await getKeys(this._eventId);
                if (!keys) {
                    this.state = 'invalid';
                    this.errorMessage = 'No signing keys available. Go online to sync.';
                    return;
                }

                // Validate JWT
                const jwtResult = await validateJwt(jwtToken, keys);

                // Legacy HS256 tokens: extract volunteerId from payload, queue for server verification
                if (jwtResult.error === 'legacy_token' && jwtResult.volunteerId) {
                    this._lastJwtToken = jwtToken;
                    // Fall through to volunteer lookup with extracted volunteerId
                } else if (!jwtResult.valid || !jwtResult.volunteerId) {
                    this.state = 'invalid';
                    this.errorMessage = jwtResult.error ?? 'Invalid QR code';
                    return;
                } else {
                    this._lastJwtToken = jwtToken;
                }

                // Look up volunteer
                const volunteer = this._volunteers.find((v) => v.id === jwtResult.volunteerId);
                if (!volunteer) {
                    this.state = 'invalid';
                    this.errorMessage = 'Volunteer not found for this event';
                    return;
                }

                // Check for existing arrival
                const alreadyArrived = this._arrivals.some((a) => a.volunteer_id === volunteer.id);

                this.result = {
                    name: volunteer.name,
                    email: volunteer.email,
                    volunteerId: volunteer.id,
                    ticketId: volunteer.ticket.id,
                };

                this.state = alreadyArrived ? 'duplicate' : 'result';
            } finally {
                // Brief cooldown to prevent rapid re-scans
                setTimeout(() => {
                    this._processing = false;
                }, 2000);
            }
        },

        async confirmArrival() {
            if (!this.result) {
                return;
            }

            const entry = {
                ticket_id: this.result.ticketId,
                volunteer_id: this.result.volunteerId,
                method: 'qr_scan' as const,
                scanned_at: new Date().toISOString().replace('T', ' ').substring(0, 19),
                ...(this._lastJwtToken ? { jwt_token: this._lastJwtToken } : {}),
            };

            // Add to local arrivals tracking
            this._arrivals.push({
                id: 0,
                ticket_id: entry.ticket_id,
                volunteer_id: entry.volunteer_id,
                event_id: this._eventId,
                scanned_by: 0,
                scanned_at: entry.scanned_at,
                method: entry.method,
                flagged: false,
                flag_reason: null,
            });

            // Save to outbox
            await addOutboxEntry(this._eventId, entry);
            this.outboxCount = await getOutboxCount(this._eventId);

            this.state = 'confirmed';

            // Try to sync immediately if online
            if (this.isOnline) {
                await this._sync();
            }

            // Auto-dismiss after 2 seconds
            setTimeout(() => {
                this.dismiss();
            }, 2000);
        },

        async _sync() {
            await syncOutbox(this._eventId, this._syncUrl);
            this.outboxCount = await getOutboxCount(this._eventId);
        },

        dismiss() {
            this.state = 'scanning';
            this.result = null;
            this.errorMessage = '';
        },

        destroy() {
            const video = (this as unknown as { $refs: Record<string, HTMLVideoElement> }).$refs.video;
            if (video) {
                stopCamera(video);
            }
        },
    };
}
