/**
 * Alpine.js scannerApp component — M11 rewrite.
 *
 * Wires together: camera -> JWT validation -> IndexedDB lookup -> result display -> confirm -> outbox -> sync.
 *
 * State machine: idle -> loading -> scanning -> result (new/duplicate/invalid) -> confirmed
 *
 * Type-based post-scan behavior:
 * - entry_staff: arrival confirmation only
 * - volunteer_admin: shift attendance + gear pickup (gear is online-only)
 */
import { startCamera, stopCamera } from './camera';
import {
    openScannerDb,
    storeVolunteers,
    storeKeys,
    storeAttendanceRecords,
    storeGuestEntries,
    getKeys,
    getVolunteers,
    getGuestEntries,
    addOutboxEntry,
    getOutboxCount,
} from './idb-store';
import { validateJwt } from './jwt-validator';
import { classifyShifts, type ClassifiedShift } from './shift-context';
import { syncOutbox } from './sync';
import type { Volunteer, ArrivalRecord, AttendanceRecord, GearItem, VolunteerGear, VolunteerGearPickup, GuestEntry } from './types';

type ScannerState = 'idle' | 'loading' | 'scanning' | 'result' | 'duplicate' | 'invalid' | 'confirmed';

interface ScannerResult {
    name: string;
    email: string;
    volunteerId: number;
    ticketId: number;
    shifts: ClassifiedShift[];
    shiftSignups: { id: number; shiftId: number; startsAt: string }[];
}

interface ScannerAppConfig {
    scannerId: number;
    scannerType: 'entry_staff' | 'volunteer_admin';
    modes: string[];
    scannerToken: string;
    dataUrl: string;
    syncUrl: string;
    gearPickupUrl: string;
    guestSyncUrl: string;
    guestGearPickupUrl: string;
}

export function scannerApp(config: ScannerAppConfig) {
    return {
        state: 'idle' as ScannerState,
        result: null as ScannerResult | null,
        resultMessage: '' as string,
        errorMessage: '' as string,
        isOnline: navigator.onLine,
        outboxCount: 0,
        selectedVolunteer: null as Volunteer | null,
        scannerType: config.scannerType,
        cameraPaused: false,

        // Internal state
        _scannerId: config.scannerId,
        _scannerToken: config.scannerToken,
        _volunteers: [] as Volunteer[],
        _arrivals: [] as ArrivalRecord[],
        _attendanceRecords: [] as AttendanceRecord[],
        _gearItems: [] as GearItem[],
        _volunteerGear: {} as Record<number, VolunteerGear[]>,
        _graceMinutes: null as number | null,
        _eventIds: [] as number[],
        _syncUrl: config.syncUrl,
        _dataUrl: config.dataUrl,
        _gearPickupUrl: config.gearPickupUrl,
        _processing: false,
        _guestEntries: [] as GuestEntry[],
        _guestSyncUrl: config.guestSyncUrl,
        _guestGearPickupUrl: config.guestGearPickupUrl,
        _gearCooldowns: {} as Record<number, boolean>,
        _inactivityTimer: null as ReturnType<typeof setTimeout> | null,
        _inactivityTimeout: 120000, // 2 minutes
        _video: null as HTMLVideoElement | null,
        _canvas: null as HTMLCanvasElement | null,
        activeTab: 'scanner' as 'scanner' | 'volunteers' | 'guests',
        guestSearchQuery: '' as string,
        guestResult: null as GuestEntry | null,

        get filteredGuestGroups(): { label: string; guestCount: number; entries: GuestEntry[] }[] {
            const groups = new Map<number, { label: string; guestCount: number; entries: GuestEntry[] }>();
            const lowerQuery = this.guestSearchQuery.toLowerCase();

            for (const entry of this._guestEntries) {
                if (lowerQuery && !(
                    (entry.name && entry.name.toLowerCase().includes(lowerQuery)) ||
                    entry.group_label.toLowerCase().includes(lowerQuery)
                )) {
                    continue;
                }

                if (!groups.has(entry.guest_group_id)) {
                    groups.set(entry.guest_group_id, {
                        label: entry.group_label,
                        guestCount: entry.group_guest_count,
                        entries: [],
                    });
                }
                groups.get(entry.guest_group_id)!.entries.push(entry);
            }

            return Array.from(groups.values());
        },

        get canConfirmArrival(): boolean {
            return config.scannerType === 'entry_staff';
        },

        get canPickupGear(): boolean {
            return config.modes.includes('gear_pickup');
        },

        get canMarkAttendance(): boolean {
            return config.scannerType === 'volunteer_admin';
        },

        async init() {
            await openScannerDb();

            // Online/offline listeners
            window.addEventListener('online', () => {
                this.isOnline = true;
                this._sync();
            });
            window.addEventListener('offline', () => {
                this.isOnline = false;
            });

            // Page Visibility API: pause camera when tab goes to background
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    this._pauseCamera();
                }
            });

            // Load data
            this.state = 'loading';
            await this._loadScannerData();

            // Start camera
            this._video = document.getElementById('scanner-video') as HTMLVideoElement | null;
            this._canvas = document.createElement('canvas');

            if (this._video) {
                await startCamera(this._video, this._canvas, (data: string) => this._onQrDetected(data), (error: Error) => {
                    this.state = 'invalid';
                    this.errorMessage = `Camera error: ${error.message}`;
                });
                this.state = 'scanning';
                this._resetInactivityTimer();
            }
        },

        async _loadScannerData() {
            if (this.isOnline) {
                try {
                    const headers: Record<string, string> = {
                        Accept: 'application/json',
                        'X-Scanner-Token': this._scannerToken,
                    };

                    const response = await fetch(this._dataUrl, { headers });
                    if (response.ok) {
                        const data = await response.json();
                        this._volunteers = data.volunteers;
                        this._arrivals = data.arrivals;
                        this._attendanceRecords = data.attendance_records ?? [];
                        this._gearItems = data.gear_items ?? [];
                        this._volunteerGear = data.volunteer_gear ?? {};
                        this._eventIds = (data.events ?? []).map((e: { id: number }) => e.id);
                        this._graceMinutes = data.events?.[0]?.attendance_grace_minutes ?? null;

                        // Persist to IndexedDB for offline use
                        await storeVolunteers(this._scannerId, data.volunteers);
                        await storeKeys(this._scannerId, data.keys);
                        this._guestEntries = data.guest_entries ?? [];
                        await storeGuestEntries(this._scannerId, this._guestEntries);
                        if (data.attendance_records) {
                            await storeAttendanceRecords(this._scannerId, data.attendance_records);
                        }
                    }
                } catch {
                    // Network error — fall through to IDB cache
                }
            }

            // Fallback: load from IndexedDB
            if (this._volunteers.length === 0) {
                this._volunteers = await getVolunteers(this._scannerId);
            }
            if (this._guestEntries.length === 0) {
                this._guestEntries = await getGuestEntries(this._scannerId);
            }

            this.outboxCount = await getOutboxCount(this._scannerId);
        },

        async _onQrDetected(jwtToken: string) {
            if (this._processing || this.state !== 'scanning') {
                return;
            }
            this._processing = true;
            this._resetInactivityTimer();

            try {
                // Get keys from IndexedDB
                const keys = await getKeys(this._scannerId);
                if (!keys) {
                    this.state = 'invalid';
                    this.errorMessage = 'No signing keys available. Go online to sync.';
                    return;
                }

                // Validate JWT
                const jwtResult = await validateJwt(jwtToken, keys);

                if (!jwtResult.valid || !jwtResult.volunteerId) {
                    // Dual-path: try guest token lookup (D8)
                    const guestEntry = this._guestEntries.find((e) => e.qr_token === jwtToken);
                    if (guestEntry) {
                        this._handleGuestQr(guestEntry);
                        return;
                    }

                    this.state = 'invalid';
                    this.errorMessage = jwtResult.error ?? 'Invalid QR code';
                    return;
                }

                // Look up volunteer
                const volunteer = this._volunteers.find((v) => v.id === jwtResult.volunteerId);
                if (!volunteer) {
                    this.state = 'invalid';
                    this.errorMessage = 'Volunteer not found';
                    return;
                }

                // Check for existing arrival
                const alreadyArrived = this._arrivals.some((a) => a.volunteer_id === volunteer.id);

                this.selectedVolunteer = volunteer;
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

                if (alreadyArrived) {
                    const arrival = this._arrivals.find((a) => a.volunteer_id === volunteer.id);
                    const lastScan = arrival?.scanned_at
                        ? new Date(arrival.scanned_at).toLocaleTimeString()
                        : '';
                    this.state = 'duplicate';
                    this.resultMessage = lastScan
                        ? `Already checked in at ${lastScan}.`
                        : 'Already checked in.';
                } else {
                    this.state = 'result';
                    this.resultMessage = 'Ready to check in.';
                }
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

            const eventId = this._eventIds[0] ?? 0;

            const entry = {
                type: 'arrival' as const,
                ticket_id: this.result.ticketId,
                volunteer_id: this.result.volunteerId,
                event_id: eventId,
                method: 'qr_scan' as const,
                scanned_at: new Date().toISOString().replace('T', ' ').substring(0, 19),
            };

            // Add to local arrivals tracking
            this._arrivals.push({
                id: 0,
                ticket_id: entry.ticket_id,
                volunteer_id: entry.volunteer_id,
                event_id: eventId,
                scanned_by: null,
                scanned_at: entry.scanned_at,
                method: entry.method,
                flagged: false,
                flag_reason: null,
            });

            // Save to outbox
            await addOutboxEntry(this._scannerId, entry);
            this.outboxCount = await getOutboxCount(this._scannerId);

            this.state = 'confirmed';
            this.resultMessage = `${this.result.name} checked in successfully.`;

            // Try to sync immediately if online
            if (this.isOnline) {
                await this._sync();
            }

        },

        async confirmAttendance(shiftSignupId: number) {
            if (!this.result || !this.isOnline) {
                return;
            }

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

            // Online-only: no IDB outbox for attendance (no scanner attendance sync endpoint)
            try {
                const response = await fetch(this._syncUrl.replace('/sync', '/attendance'), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Scanner-Token': this._scannerToken,
                    },
                    body: JSON.stringify({
                        shift_signup_id: shiftSignupId,
                        status,
                        scanned_at: now.toISOString().replace('T', ' ').substring(0, 19),
                    }),
                });

                if (!response.ok) {
                    console.error('Attendance confirmation failed:', await response.text());
                    return;
                }
            } catch (error) {
                console.error('Attendance confirmation network error:', error);
                return;
            }

            this._attendanceRecords.push({
                id: 0,
                shift_signup_id: shiftSignupId,
                status,
            });

            if (this.result) {
                const shift = this.result.shifts.find((s) => s.signupId === shiftSignupId);
                if (shift) {
                    shift.status = 'attended';
                }
            }
        },

        /**
         * Online-only gear pickup (D4/D10: no IDB buffering for gear state changes).
         */
        async selectGearState(volunteerGearId: number, state: string) {
            if (!this.isOnline || this._gearCooldowns[volunteerGearId]) {
                return;
            }

            // Activate cooldown immediately
            this._gearCooldowns[volunteerGearId] = true;
            setTimeout(() => {
                this._gearCooldowns[volunteerGearId] = false;
            }, 2000);

            try {
                const response = await fetch(this._gearPickupUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Scanner-Token': this._scannerToken,
                    },
                    body: JSON.stringify({
                        volunteer_gear_id: volunteerGearId,
                        state,
                    }),
                });

                if (response.ok) {
                    for (const [, gearList] of Object.entries(this._volunteerGear)) {
                        const gear = (gearList as VolunteerGear[]).find((g) => g.id === volunteerGearId);
                        if (gear) {
                            gear.pickups.push({ state, quantity: 1, picked_up_at: new Date().toISOString() });
                            // Update picked_up status based on type
                            if (gear.quantity_entitled === null) {
                                gear.picked_up = true;
                            }
                            break;
                        }
                    }
                } else {
                    console.error('Gear pickup failed:', await response.text());
                }
            } catch (error) {
                console.error('Gear pickup network error:', error);
            }
        },

        isAttendanceRecorded(shiftSignupId: number): boolean {
            return this._attendanceRecords.some((r) => r.shift_signup_id === shiftSignupId);
        },

        getVolunteerGear(volunteerId: number): VolunteerGear[] {
            return this._volunteerGear[volunteerId] ?? [];
        },

        getGearItemName(projectGearItemId: number): string {
            return this._gearItems.find((g) => g.id === projectGearItemId)?.name ?? '';
        },

        getGearItemType(projectGearItemId: number): string {
            return this._gearItems.find((g) => g.id === projectGearItemId)?.type ?? 'size_selection';
        },

        getGearPickedUpCount(gear: VolunteerGear): number {
            return gear.pickups.reduce((sum, p) => sum + p.quantity, 0);
        },

        isGearFullyPickedUp(gear: VolunteerGear): boolean {
            if (gear.quantity_entitled === null) {
                return gear.picked_up;
            }
            return this.getGearPickedUpCount(gear) >= gear.quantity_entitled;
        },

        isGearCooldown(gearId: number): boolean {
            return this._gearCooldowns[gearId] ?? false;
        },

        async _sync() {
            await syncOutbox(this._scannerId, this._syncUrl, this._scannerToken, this._guestSyncUrl);
            this.outboxCount = await getOutboxCount(this._scannerId);
        },

        dismiss() {
            this.state = 'scanning';
            this.result = null;
            this.guestResult = null;
            this.selectedVolunteer = null;
            this.resultMessage = '';
            this.errorMessage = '';
            this._resetInactivityTimer();
        },

        _handleGuestQr(entry: GuestEntry) {
            const alreadyCheckedIn = entry.checked_in_at !== null;
            this.guestResult = entry;

            const label = `${entry.group_label} ${entry.number}/${entry.group_guest_count}`;

            if (alreadyCheckedIn) {
                this.state = 'duplicate';
                this.resultMessage = `Gast — ${label} already checked in.`;
            } else {
                this.state = 'result';
                this.resultMessage = `Gast — ${label}${entry.name ? ` (${entry.name})` : ''} — ready to check in.`;
            }
        },

        async confirmGuestCheckin(guestEntryId: number) {
            const entry = this._guestEntries.find((e) => e.id === guestEntryId);
            if (!entry) {
                return;
            }

            const now = new Date().toISOString().replace('T', ' ').substring(0, 19);

            // Mark locally
            entry.checked_in_at = now;

            // Save to outbox
            await addOutboxEntry(this._scannerId, {
                type: 'guest_checkin',
                guest_entry_id: guestEntryId,
                scanned_at: now,
            });
            this.outboxCount = await getOutboxCount(this._scannerId);

            this.state = 'confirmed';
            this.resultMessage = `${entry.group_label} ${entry.number}/${entry.group_guest_count} checked in.`;

            if (this.isOnline) {
                await this._sync();
            }

        },

        async _postGuestGear(guestEntryGearId: number, payload: Record<string, unknown>) {
            if (!this.isOnline) {
                return;
            }

            try {
                const response = await fetch(this._guestGearPickupUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Scanner-Token': this._scannerToken,
                    },
                    body: JSON.stringify({ guest_entry_gear_id: guestEntryGearId, ...payload }),
                });

                if (!response.ok) {
                    console.error('Guest gear pickup failed:', await response.text());
                }
            } catch (error) {
                console.error('Guest gear pickup network error:', error);
            }
        },

        async selectGuestGearState(guestEntryGearId: number, state: string) {
            await this._postGuestGear(guestEntryGearId, { status: state });
        },

        async selectGuestGearSelection(guestEntryGearId: number, selection: string) {
            await this._postGuestGear(guestEntryGearId, { selection });
        },

        async incrementGuestGearPickup(guestEntryGearId: number) {
            await this._postGuestGear(guestEntryGearId, { quantity: 1 });
        },

        _resetInactivityTimer() {
            if (this._inactivityTimer) {
                clearTimeout(this._inactivityTimer);
            }
            this._inactivityTimer = setTimeout(() => {
                this._pauseCamera();
            }, this._inactivityTimeout);
        },

        _pauseCamera() {
            if (this.cameraPaused || !this._video) {
                return;
            }
            stopCamera(this._video);
            this.cameraPaused = true;
            if (this._inactivityTimer) {
                clearTimeout(this._inactivityTimer);
                this._inactivityTimer = null;
            }
        },

        async resumeCamera() {
            if (!this.cameraPaused || !this._video || !this._canvas) {
                return;
            }
            await startCamera(this._video, this._canvas, (data: string) => this._onQrDetected(data), (error: Error) => {
                this.state = 'invalid';
                this.errorMessage = `Camera error: ${error.message}`;
            });
            this.cameraPaused = false;
            if (this.state === 'idle' || this.state === 'scanning') {
                this.state = 'scanning';
            }
            this._resetInactivityTimer();
        },

        destroy() {
            if (this._inactivityTimer) {
                clearTimeout(this._inactivityTimer);
            }
            if (this._video) {
                stopCamera(this._video);
            }
        },
    };
}
