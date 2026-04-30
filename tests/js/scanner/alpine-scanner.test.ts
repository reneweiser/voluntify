import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { Volunteer } from '@/scanner/types';

const cameraMocks = vi.hoisted(() => ({
    startCamera: vi.fn(),
    stopCamera: vi.fn(),
}));

const idbStoreMocks = vi.hoisted(() => ({
    openScannerDb: vi.fn(),
    storeVolunteers: vi.fn(),
    storeKeys: vi.fn(),
    storeAttendanceRecords: vi.fn(),
    storeGuestEntries: vi.fn(),
    getKeys: vi.fn(),
    getVolunteers: vi.fn(),
    getGuestEntries: vi.fn(),
    getAttendanceRecords: vi.fn(),
    addOutboxEntry: vi.fn(),
    getOutboxCount: vi.fn(),
}));

const syncMocks = vi.hoisted(() => {
    class ScannerContractVersionError extends Error {}

    return {
        ScannerContractVersionError,
        syncOutbox: vi.fn(),
    };
});

vi.mock('@/scanner/camera', () => cameraMocks);
vi.mock('@/scanner/idb-store', () => idbStoreMocks);
vi.mock('@/scanner/jwt-validator', () => ({
    validateJwt: vi.fn(),
}));
vi.mock('@/scanner/sync', () => syncMocks);

import { scannerApp } from '@/scanner/alpine-scanner';

function makeVolunteer(overrides: Partial<Volunteer> = {}): Volunteer {
    const id = overrides.id ?? 1;

    return {
        id,
        first_name: 'Alice',
        last_name: 'Johnson',
        name: 'Alice Johnson',
        email: 'alice@example.com',
        phone: '+49123456789',
        ticket: { id: id + 100, jwt_token: 'token', volunteer_id: id, project_id: 1 },
        shift_signups: [],
        ...overrides,
    };
}

function createApp(scannerType: 'entry_staff' | 'gear' | 'volunteer_admin' = 'entry_staff') {
    return scannerApp({
        scannerId: 7,
        scannerType,
        modes: ['checkin'],
        entryEventId: 5,
        contractVersion: 1,
        requiresConfigurationReview: false,
        scannerToken: 'scanner-token',
        dataUrl: '/scanner/data',
        syncUrl: '/scanner/sync',
        gearPickupUrl: '/scanner/gear',
        guestSyncUrl: '/scanner/guests',
        guestGearPickupUrl: '/scanner/guests/gear',
    });
}

describe('alpine-scanner manual volunteer lookup', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        idbStoreMocks.getOutboxCount.mockResolvedValue(1);
        Object.defineProperty(window.navigator, 'onLine', {
            configurable: true,
            value: true,
        });
    });

    it('filters volunteers by query and keeps checked-in volunteers last', () => {
        const app = createApp();
        const uncheckedVolunteer = makeVolunteer();
        const checkedInVolunteer = makeVolunteer({
            id: 2,
            first_name: 'Bob',
            last_name: 'Example',
            name: 'Bob Example',
            email: 'bob@example.com',
        });

        app._volunteers = [checkedInVolunteer, uncheckedVolunteer];
        app._arrivals = [{
            id: 1,
            ticket_id: checkedInVolunteer.ticket.id,
            volunteer_id: checkedInVolunteer.id,
            event_id: 5,
            scanned_by: null,
            scanned_at: '2026-03-02 10:00:00',
            method: 'qr_scan',
            flagged: false,
            flag_reason: null,
        }];

        app.volunteerSearchQuery = 'a';
        expect(app.filteredVolunteers).toEqual([]);

        app.volunteerSearchQuery = 'EXAMPLE.COM';
        expect(app.filteredVolunteers.map((volunteer) => volunteer.id)).toEqual([
            uncheckedVolunteer.id,
            checkedInVolunteer.id,
        ]);
    });

    it('selects a volunteer from manual search without leaving the volunteers tab', () => {
        const app = createApp();
        const volunteer = makeVolunteer();

        app.activeTab = 'volunteers';
        app.selectVolunteerFromSearch(volunteer);

        expect(app.activeTab).toBe('volunteers');
        expect(app.selectedVolunteer?.id).toBe(volunteer.id);
        expect(app.selectedVolunteerSource).toBe('manual');
        expect(app.state).toBe('scanning');
        expect(app.result?.volunteerId).toBe(volunteer.id);
    });

    it('queues manual arrivals with the manual lookup method', async () => {
        const app = createApp();
        const volunteer = makeVolunteer();

        app.isOnline = false;
        app.selectVolunteerFromSearch(volunteer);

        await app.confirmArrival();

        expect(idbStoreMocks.addOutboxEntry).toHaveBeenCalledWith(7, expect.objectContaining({
            volunteer_id: volunteer.id,
            ticket_id: volunteer.ticket.id,
            event_id: 5,
            entry_event_id: 5,
            contract_version: 1,
            method: 'manual_lookup',
        }));
        expect(app._arrivals).toContainEqual(expect.objectContaining({
            volunteer_id: volunteer.id,
            event_id: 5,
            method: 'manual_lookup',
        }));
        expect(app.hasArrivalForEntryEvent(volunteer.id)).toBe(true);
        expect(app.state).toBe('scanning');
    });

    it('does not queue a duplicate manual arrival for an already checked-in volunteer', async () => {
        const app = createApp();
        const volunteer = makeVolunteer();

        app._arrivals = [{
            id: 1,
            ticket_id: volunteer.ticket.id,
            volunteer_id: volunteer.id,
            event_id: 5,
            scanned_by: null,
            scanned_at: '2026-03-02 10:00:00',
            method: 'manual_lookup',
            flagged: false,
            flag_reason: null,
        }];
        app.selectVolunteerFromSearch(volunteer);

        await app.confirmArrival();

        expect(app.volunteerDetailState).toBe('checked_in');
        expect(idbStoreMocks.addOutboxEntry).not.toHaveBeenCalled();
        expect(app._arrivals).toHaveLength(1);
    });

    it('preserves manual lookup state when scanner data reloads', async () => {
        const app = createApp();
        const volunteer = makeVolunteer();

        app._volunteers = [volunteer];
        app.activeTab = 'volunteers';
        app.volunteerSearchQuery = 'ali';
        app.selectVolunteerFromSearch(volunteer);
        app._loadScannerData = vi.fn(async function (this: typeof app) {
            this._volunteers = [volunteer];
            this._guestEntries = [];
            this._attendanceRecords = [];
            this.outboxCount = 1;
        });

        await app.reloadScannerData({ preserveUiState: true });

        expect(app.activeTab).toBe('volunteers');
        expect(app.volunteerSearchQuery).toBe('ali');
        expect(app.selectedVolunteer?.id).toBe(volunteer.id);
        expect(app.selectedVolunteerSource).toBe('manual');
    });
});

describe('alpine-scanner QR volunteer selection', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('shows a volunteer-admin result for an already-arrived volunteer', () => {
        const app = createApp('volunteer_admin');
        const volunteer = makeVolunteer();

        app._arrivals = [{
            id: 1,
            ticket_id: volunteer.ticket.id,
            volunteer_id: volunteer.id,
            event_id: 5,
            scanned_by: null,
            scanned_at: '2026-03-02 10:00:00',
            method: 'qr_scan',
            flagged: false,
            flag_reason: null,
        }];

        app.selectVolunteer(volunteer, { fromQr: true });

        expect(app.state).toBe('result');
        expect(app.resultMessage).toBe('');
        expect(app.result?.volunteerId).toBe(volunteer.id);
    });

    it('shows a volunteer-admin result for a not-yet-arrived volunteer', () => {
        const app = createApp('volunteer_admin');
        const volunteer = makeVolunteer();

        app.selectVolunteer(volunteer, { fromQr: true });

        expect(app.state).toBe('result');
        expect(app.resultMessage).toBe('');
        expect(app.result?.volunteerId).toBe(volunteer.id);
    });

    it('keeps the entry-staff duplicate state for an already-arrived volunteer', () => {
        const app = createApp('entry_staff');
        const volunteer = makeVolunteer();

        app._arrivals = [{
            id: 1,
            ticket_id: volunteer.ticket.id,
            volunteer_id: volunteer.id,
            event_id: 5,
            scanned_by: null,
            scanned_at: '2026-03-02 10:00:00',
            method: 'qr_scan',
            flagged: false,
            flag_reason: null,
        }];

        app.selectVolunteer(volunteer, { fromQr: true });

        expect(app.state).toBe('duplicate');
        expect(app.resultMessage).toBe('Already checked in at 10:00:00 AM.');
        expect(app.result?.volunteerId).toBe(volunteer.id);
    });

    it('keeps the entry-staff ready message for a not-yet-arrived volunteer', () => {
        const app = createApp('entry_staff');
        const volunteer = makeVolunteer();

        app.selectVolunteer(volunteer, { fromQr: true });

        expect(app.state).toBe('result');
        expect(app.resultMessage).toBe('Ready to check in.');
        expect(app.result?.volunteerId).toBe(volunteer.id);
    });

    it('shows a gear scanner result for volunteer QR scans without arrival copy', () => {
        const app = createApp('gear');
        const volunteer = makeVolunteer();

        app.selectVolunteer(volunteer, { fromQr: true });

        expect(app.state).toBe('result');
        expect(app.resultMessage).toBe('');
        expect(app.result?.volunteerId).toBe(volunteer.id);
    });
});

describe('alpine-scanner gear guest selection', () => {
    it('selects a guest from search in gear mode', () => {
        const app = createApp('gear');

        app._guestEntries = [{
            id: 11,
            guest_group_id: 3,
            group_label: 'Artists',
            group_guest_count: 2,
            number: 1,
            name: 'DJ Test',
            qr_token: 'guest-token',
            checked_in_at: null,
            gear: [],
        }];

        app.selectGuestFromSearch(app._guestEntries[0]);

        expect(app.selectedGuest?.id).toBe(11);
        expect(app.selectedGuestSource).toBe('manual');
        expect(app.selectedVolunteer).toBeNull();
    });

    it('routes guest QR scans into gear mode guest details', () => {
        const app = createApp('gear');
        const entry = {
            id: 12,
            guest_group_id: 4,
            group_label: 'Speakers',
            group_guest_count: 1,
            number: 1,
            name: 'Keynote',
            qr_token: 'guest-qr',
            checked_in_at: null,
            gear: [],
        };

        app._handleGuestQr(entry);

        expect(app.selectedGuest?.id).toBe(12);
        expect(app.selectedGuestSource).toBe('qr');
        expect(app.guestResult).toBeNull();
        expect(app.state).toBe('result');
    });
});
