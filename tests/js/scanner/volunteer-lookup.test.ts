import { describe, expect, it } from 'vitest';
import {
    filterVolunteers,
    hasVolunteerArrivalForEvent,
    hasVolunteerArrivalInScope,
    resolveManualArrivalTarget,
} from '../../../resources/js/scanner/volunteer-lookup';
import type { ArrivalRecord, ScannerEvent, Volunteer, ShiftSignup } from '../../../resources/js/scanner/types';

function makeSignup(overrides: Partial<ShiftSignup> & { eventId?: number | null } = {}): ShiftSignup {
    const eventId = Object.prototype.hasOwnProperty.call(overrides, 'eventId')
        ? overrides.eventId ?? null
        : 101;

    return {
        id: overrides.id ?? 1,
        shift: {
            id: overrides.shift?.id ?? 11,
            event_id: eventId,
            shift_date: overrides.shift?.shift_date ?? '2026-07-01',
            starts_at: overrides.shift?.starts_at ?? '2026-07-01T10:00:00Z',
            ends_at: overrides.shift?.ends_at ?? '2026-07-01T12:00:00Z',
            display_text: overrides.shift?.display_text ?? 'Jul 01, 10:00 - 12:00',
            volunteer_job: overrides.shift?.volunteer_job ?? { id: 1, name: 'Entry' },
        },
        attendance_record: overrides.attendance_record ?? null,
    };
}

function makeVolunteer(overrides: Partial<Volunteer> = {}): Volunteer {
    return {
        id: overrides.id ?? 1,
        first_name: overrides.first_name ?? 'Lisa',
        last_name: overrides.last_name ?? 'Mueller',
        name: overrides.name ?? 'Lisa Mueller',
        email: overrides.email ?? 'lisa@example.com',
        phone: overrides.phone ?? '+491701234567',
        ticket: overrides.ticket ?? {
            id: 10,
            jwt_token: 'jwt-token',
            volunteer_id: overrides.id ?? 1,
            project_id: 1,
        },
        shift_signups: overrides.shift_signups ?? [],
    };
}

function makeEvent(overrides: Partial<ScannerEvent> = {}): ScannerEvent {
    return {
        id: overrides.id ?? 101,
        name: overrides.name ?? 'Sommerfest 2026',
        attendance_grace_minutes: overrides.attendance_grace_minutes ?? 15,
    };
}

describe('filterVolunteers', () => {
    const volunteers = [
        makeVolunteer(),
        makeVolunteer({
            id: 2,
            first_name: 'Tom',
            last_name: 'Schneider',
            name: 'Tom Schneider',
            email: 'tom@example.com',
        }),
    ];

    it('returns no volunteers for queries shorter than two characters', () => {
        expect(filterVolunteers(volunteers, 'L')).toEqual([]);
    });

    it('filters by first name, last name, and email case-insensitively', () => {
        expect(filterVolunteers(volunteers, 'lisa').map((volunteer) => volunteer.id)).toEqual([1]);
        expect(filterVolunteers(volunteers, 'SCHNEI').map((volunteer) => volunteer.id)).toEqual([2]);
        expect(filterVolunteers(volunteers, 'tom@example').map((volunteer) => volunteer.id)).toEqual([2]);
    });

    it('keeps volunteers without shift signups searchable', () => {
        const volunteer = makeVolunteer({ id: 3, first_name: 'NoShift', last_name: 'Volunteer', shift_signups: [] });

        expect(filterVolunteers([...volunteers, volunteer], 'NoShift').map((entry) => entry.id)).toContain(3);
    });
});

describe('resolveManualArrivalTarget', () => {
    it('resolves directly when the volunteer has exactly one signup event', () => {
        const volunteer = makeVolunteer({ shift_signups: [makeSignup({ eventId: 101 })] });

        expect(resolveManualArrivalTarget(volunteer, [makeEvent({ id: 101 })])).toEqual({
            kind: 'resolved',
            eventId: 101,
            eventIds: [101],
        });
    });

    it('requires operator choice when the volunteer has signups across multiple events', () => {
        const volunteer = makeVolunteer({
            shift_signups: [
                makeSignup({ id: 1, eventId: 101 }),
                makeSignup({ id: 2, eventId: 202 }),
            ],
        });

        expect(resolveManualArrivalTarget(volunteer, [makeEvent({ id: 101 }), makeEvent({ id: 202 })])).toEqual({
            kind: 'choice_required',
            eventIds: [101, 202],
        });
    });

    it('falls back to the only scanner event when a volunteer has no shifts and only one event is loaded', () => {
        const volunteer = makeVolunteer({ shift_signups: [] });

        expect(resolveManualArrivalTarget(volunteer, [makeEvent({ id: 101 })])).toEqual({
            kind: 'resolved',
            eventId: 101,
            eventIds: [101],
        });
    });

    it('requires operator choice when a volunteer has no shifts and multiple project events exist', () => {
        const volunteer = makeVolunteer({ shift_signups: [] });

        expect(resolveManualArrivalTarget(volunteer, [makeEvent({ id: 101 }), makeEvent({ id: 202 })])).toEqual({
            kind: 'choice_required',
            eventIds: [101, 202],
        });
    });

    it('blocks confirmation when stale shift data is missing event ids', () => {
        const volunteer = makeVolunteer({
            shift_signups: [makeSignup({ eventId: null })],
        });

        expect(resolveManualArrivalTarget(volunteer, [makeEvent({ id: 101 })])).toEqual({
            kind: 'unavailable',
            reason: 'missing_event_metadata',
        });
    });
});

describe('arrival helpers', () => {
    const arrivals: ArrivalRecord[] = [
        {
            id: 1,
            ticket_id: 10,
            volunteer_id: 1,
            event_id: 101,
            scanned_by: null,
            scanned_at: '2026-07-01 10:00:00',
            method: 'manual_lookup',
            flagged: false,
            flag_reason: null,
        },
    ];

    it('detects arrivals within scanner scope', () => {
        expect(hasVolunteerArrivalInScope(arrivals, 1, [101, 202])).toBe(true);
        expect(hasVolunteerArrivalInScope(arrivals, 1, [202])).toBe(false);
    });

    it('detects arrivals for a single resolved event', () => {
        expect(hasVolunteerArrivalForEvent(arrivals, 1, 101)).toBe(true);
        expect(hasVolunteerArrivalForEvent(arrivals, 1, 202)).toBe(false);
    });
});
