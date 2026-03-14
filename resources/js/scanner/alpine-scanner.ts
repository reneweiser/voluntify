/**
 * Alpine.js scannerApp component.
 *
 * Wires together: camera → JWT validation → IndexedDB lookup → result display → confirm → outbox → sync.
 *
 * State machine: idle → loading → scanning → result (new/duplicate/invalid) → confirmed
 *
 * Role-based post-scan behavior:
 * - entrance_staff: arrival confirmation only
 * - volunteer_admin: shift attendance panel
 * - organizer: both arrival + attendance
 */
import { startCamera, stopCamera } from './camera';
import {
    openScannerDb,
    storeVolunteers,
    storeKeys,
    storeAttendanceRecords,
    getKeys,
    getVolunteers,
    addOutboxEntry,
    getOutboxCount,
} from './idb-store';
import { validateJwt } from './jwt-validator';
import { classifyShifts, type ClassifiedShift } from './shift-context';
import { syncOutbox } from './sync';
import type { Volunteer, ArrivalRecord, AttendanceRecord } from './types';

type ScannerState = 'idle' | 'loading' | 'scanning' | 'result' | 'duplicate' | 'invalid' | 'confirmed';

type UserRole = 'organizer' | 'entrance_staff' | 'volunteer_admin' | null;

interface ScannerResult {
    name: string;
    email: string;
    volunteerId: number;
    ticketId: number;
    shifts: ClassifiedShift[];
    shiftSignups: { id: number; shiftId: number; startsAt: string }[];
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
        _attendanceRecords: [] as AttendanceRecord[],
        _userRole: null as UserRole,
        _graceMinutes: null as number | null,
        _syncUrl: '',
        _attendanceSyncUrl: '',
        _dataUrl: '',
        _processing: false,

        get canConfirmArrival(): boolean {
            return this._userRole === 'organizer' || this._userRole === 'entrance_staff';
        },

        get canMarkAttendance(): boolean {
            return this._userRole === 'organizer' || this._userRole === 'volunteer_admin';
        },

        async init() {
            await openScannerDb();

            // Compute API URLs
            this._dataUrl = `/admin/scanner/api/events/${this._eventId}/data`;
            this._syncUrl = `/admin/scanner/api/events/${this._eventId}/sync`;
            this._attendanceSyncUrl = `/admin/scanner/api/events/${this._eventId}/attendance-sync`;

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
                        this._attendanceRecords = data.attendance_records ?? [];
                        this._userRole = data.user_role ?? null;
                        this._graceMinutes = data.event?.attendance_grace_minutes ?? null;

                        // Persist to IndexedDB for offline use
                        await storeVolunteers(this._eventId, data.volunteers);
                        await storeKeys(this._eventId, data.keys);
                        if (data.attendance_records) {
                            await storeAttendanceRecords(this._eventId, data.attendance_records);
                        }
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

                if (!jwtResult.valid || !jwtResult.volunteerId) {
                    this.state = 'invalid';
                    this.errorMessage = jwtResult.error ?? 'Invalid QR code';
                    return;
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
                    shifts: classifyShifts(volunteer.shift_signups, new Date()),
                    shiftSignups: volunteer.shift_signups.map((s) => ({
                        id: s.id,
                        shiftId: s.shift.id,
                        startsAt: s.shift.starts_at,
                    })),
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
                type: 'arrival' as const,
                ticket_id: this.result.ticketId,
                volunteer_id: this.result.volunteerId,
                method: 'qr_scan' as const,
                scanned_at: new Date().toISOString().replace('T', ' ').substring(0, 19),
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

        async confirmAttendance(shiftSignupId: number) {
            if (!this.result) {
                return;
            }

            // Determine on-time vs late
            const signup = this.result.shiftSignups.find((s) => s.id === shiftSignupId);
            if (!signup) {
                return;
            }

            const now = new Date();
            const shiftStart = new Date(signup.startsAt);
            const deadline = this._graceMinutes !== null
                ? new Date(shiftStart.getTime() + this._graceMinutes * 60000)
                : shiftStart;

            const status = now <= deadline ? 'on_time' : 'late';

            const entry = {
                type: 'attendance' as const,
                shift_signup_id: shiftSignupId,
                status: status as 'on_time' | 'late',
                scanned_at: now.toISOString().replace('T', ' ').substring(0, 19),
            };

            // Track locally
            this._attendanceRecords.push({
                id: 0,
                shift_signup_id: shiftSignupId,
                status,
            });

            // Update the shift's classified status
            if (this.result) {
                const shift = this.result.shifts.find((s) => s.signupId === shiftSignupId);
                if (shift) {
                    shift.status = 'attended';
                }
            }

            await addOutboxEntry(this._eventId, entry);
            this.outboxCount = await getOutboxCount(this._eventId);

            if (this.isOnline) {
                await this._sync();
            }
        },

        isAttendanceRecorded(shiftSignupId: number): boolean {
            return this._attendanceRecords.some((r) => r.shift_signup_id === shiftSignupId);
        },

        async _sync() {
            await syncOutbox(this._eventId, this._syncUrl, this._attendanceSyncUrl);
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
