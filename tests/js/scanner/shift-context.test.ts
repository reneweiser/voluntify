import { describe, it, expect } from 'vitest';
import {
    classifyShifts,
    filterShiftGroups,
    findNextUpcomingShiftGroup,
    groupSignupsByShift,
    requiresShiftSearchHint,
} from '../../../resources/js/scanner/shift-context';
import type { ShiftSignup, Volunteer } from '../../../resources/js/scanner/types';

function makeSignup(overrides: Partial<{
    id: number;
    shiftId: number;
    startsAt: string;
    endsAt: string;
    jobName: string;
    displayText: string;
    attendanceRecord: ShiftSignup['attendance_record'];
}>): ShiftSignup {
    return {
        id: overrides.id ?? 1,
        shift: {
            id: overrides.shiftId ?? 1,
            shift_date: '2026-03-14',
            starts_at: overrides.startsAt ?? '2026-03-14T10:00:00Z',
            ends_at: overrides.endsAt ?? '2026-03-14T12:00:00Z',
            display_text: overrides.displayText ?? 'Mar 14, 10:00 - 12:00',
            volunteer_job: { id: 1, name: overrides.jobName ?? 'Gate Watch' },
        },
        attendance_record: overrides.attendanceRecord ?? null,
    };
}

function makeVolunteer(overrides: Partial<Volunteer> = {}): Volunteer {
    return {
        id: overrides.id ?? 1,
        first_name: overrides.first_name ?? 'Alice',
        last_name: overrides.last_name ?? 'Johnson',
        name: overrides.name ?? 'Alice Johnson',
        email: overrides.email ?? 'alice@example.com',
        phone: overrides.phone ?? null,
        ticket: overrides.ticket ?? {
            id: 10,
            jwt_token: 'jwt-token',
            volunteer_id: overrides.id ?? 1,
            project_id: 1,
        },
        shift_signups: overrides.shift_signups ?? [],
    };
}

describe('classifyShifts', () => {
    it('classifies upcoming shifts', () => {
        const now = new Date('2026-03-14T08:00:00Z');
        const signup = makeSignup({ startsAt: '2026-03-14T10:00:00Z', endsAt: '2026-03-14T12:00:00Z' });

        const result = classifyShifts([signup], now);

        expect(result[0].status).toBe('upcoming');
    });

    it('classifies active shifts', () => {
        const now = new Date('2026-03-14T11:00:00Z');
        const signup = makeSignup({ startsAt: '2026-03-14T10:00:00Z', endsAt: '2026-03-14T12:00:00Z' });

        const result = classifyShifts([signup], now);

        expect(result[0].status).toBe('active');
    });

    it('classifies missed shifts (past, no attendance)', () => {
        const now = new Date('2026-03-14T14:00:00Z');
        const signup = makeSignup({ startsAt: '2026-03-14T10:00:00Z', endsAt: '2026-03-14T12:00:00Z' });

        const result = classifyShifts([signup], now);

        expect(result[0].status).toBe('missed');
    });

    it('classifies attended shifts (on_time record)', () => {
        const now = new Date('2026-03-14T11:00:00Z');
        const signup = makeSignup({
            attendanceRecord: { id: 1, shift_signup_id: 1, status: 'on_time' },
        });

        const result = classifyShifts([signup], now);

        expect(result[0].status).toBe('attended');
    });

    it('classifies attended shifts (late record)', () => {
        const now = new Date('2026-03-14T11:00:00Z');
        const signup = makeSignup({
            attendanceRecord: { id: 2, shift_signup_id: 1, status: 'late' },
        });

        const result = classifyShifts([signup], now);

        expect(result[0].status).toBe('attended');
    });

    it('classifies missed shifts (no_show record)', () => {
        const now = new Date('2026-03-14T14:00:00Z');
        const signup = makeSignup({
            attendanceRecord: { id: 3, shift_signup_id: 1, status: 'no_show' },
        });

        const result = classifyShifts([signup], now);

        expect(result[0].status).toBe('missed');
    });

    it('classifies shift at exact start time as active', () => {
        const now = new Date('2026-03-14T10:00:00Z');
        const signup = makeSignup({ startsAt: '2026-03-14T10:00:00Z', endsAt: '2026-03-14T12:00:00Z' });

        const result = classifyShifts([signup], now);

        expect(result[0].status).toBe('active');
    });

    it('classifies shift at exact end time as missed', () => {
        const now = new Date('2026-03-14T12:00:00Z');
        const signup = makeSignup({ startsAt: '2026-03-14T10:00:00Z', endsAt: '2026-03-14T12:00:00Z' });

        const result = classifyShifts([signup], now);

        expect(result[0].status).toBe('missed');
    });

    it('includes job name and times in result', () => {
        const now = new Date('2026-03-14T08:00:00Z');
        const signup = makeSignup({ id: 42, jobName: 'Bar Tender', startsAt: '2026-03-14T18:00:00Z', endsAt: '2026-03-14T22:00:00Z' });

        const result = classifyShifts([signup], now);

        expect(result[0]).toMatchObject({
            signupId: 42,
            jobName: 'Bar Tender',
            startsAt: '2026-03-14T18:00:00Z',
            endsAt: '2026-03-14T22:00:00Z',
        });
    });

    it('classifies multiple shifts correctly', () => {
        const now = new Date('2026-03-14T11:00:00Z');
        const signups = [
            makeSignup({ id: 1, startsAt: '2026-03-14T08:00:00Z', endsAt: '2026-03-14T10:00:00Z' }),
            makeSignup({ id: 2, startsAt: '2026-03-14T10:00:00Z', endsAt: '2026-03-14T12:00:00Z' }),
            makeSignup({ id: 3, startsAt: '2026-03-14T14:00:00Z', endsAt: '2026-03-14T16:00:00Z' }),
        ];

        const result = classifyShifts(signups, now);

        expect(result.map((r) => r.status)).toEqual(['missed', 'active', 'upcoming']);
    });
});

describe('groupSignupsByShift', () => {
    it('groups volunteer signups chronologically by shift', () => {
        const volunteers = [
            makeVolunteer({
                shift_signups: [
                    makeSignup({ id: 1, shiftId: 2, startsAt: '2026-03-14T14:00:00Z', endsAt: '2026-03-14T16:00:00Z', jobName: 'Bar' }),
                    makeSignup({ id: 2, shiftId: 1, startsAt: '2026-03-14T10:00:00Z', endsAt: '2026-03-14T12:00:00Z', jobName: 'Stage' }),
                ],
            }),
        ];

        const groups = groupSignupsByShift(volunteers, new Date('2026-03-14T08:00:00Z'));

        expect(groups.map((group) => group.shiftId)).toEqual([1, 2]);
        expect(groups.map((group) => group.jobName)).toEqual(['Stage', 'Bar']);
    });

    it('keeps the same volunteer in multiple shift groups', () => {
        const volunteer = makeVolunteer({
            shift_signups: [
                makeSignup({ id: 1, shiftId: 10, jobName: 'Setup', startsAt: '2026-03-14T07:00:00Z', endsAt: '2026-03-14T09:00:00Z' }),
                makeSignup({ id: 2, shiftId: 20, jobName: 'Breakdown', startsAt: '2026-03-14T18:00:00Z', endsAt: '2026-03-14T20:00:00Z' }),
            ],
        });

        const groups = groupSignupsByShift([volunteer], new Date('2026-03-14T06:00:00Z'));

        expect(groups).toHaveLength(2);
        expect(groups[0].volunteers[0].name).toBe('Alice Johnson');
        expect(groups[1].volunteers[0].name).toBe('Alice Johnson');
        expect(groups.map((group) => group.volunteers[0].signupId)).toEqual([1, 2]);
    });

    it('marks only the first future group as next upcoming', () => {
        const volunteer = makeVolunteer({
            shift_signups: [
                makeSignup({ id: 1, shiftId: 10, startsAt: '2026-03-14T10:00:00Z', endsAt: '2026-03-14T12:00:00Z' }),
                makeSignup({ id: 2, shiftId: 20, startsAt: '2026-03-14T14:00:00Z', endsAt: '2026-03-14T16:00:00Z' }),
            ],
        });

        const groups = groupSignupsByShift([volunteer], new Date('2026-03-14T09:00:00Z'));

        expect(groups.map((group) => group.isNextUpcoming)).toEqual([true, false]);
    });
});

describe('filterShiftGroups', () => {
    const groups = groupSignupsByShift([
        makeVolunteer({
            shift_signups: [
                makeSignup({ id: 1, shiftId: 10, jobName: 'Setup', startsAt: '2026-03-14T10:00:00Z', endsAt: '2026-03-14T12:00:00Z' }),
            ],
        }),
        makeVolunteer({
            id: 2,
            first_name: 'Lisa',
            last_name: 'Mueller',
            name: 'Lisa Mueller',
            email: 'lisa@example.com',
            shift_signups: [
                makeSignup({ id: 2, shiftId: 20, jobName: 'Bar', startsAt: '2026-03-14T14:00:00Z', endsAt: '2026-03-14T16:00:00Z' }),
            ],
        }),
    ], new Date('2026-03-14T08:00:00Z'));

    it('returns all groups for an empty query', () => {
        expect(filterShiftGroups(groups, '')).toHaveLength(2);
    });

    it('requires a search hint for a one-character query', () => {
        expect(requiresShiftSearchHint('L')).toBe(true);
        expect(filterShiftGroups(groups, 'L')).toEqual([]);
    });

    it('filters by volunteer first name', () => {
        const filtered = filterShiftGroups(groups, 'Lisa');

        expect(filtered).toHaveLength(1);
        expect(filtered[0].volunteers[0].name).toBe('Lisa Mueller');
    });

    it('filters by volunteer email', () => {
        const filtered = filterShiftGroups(groups, 'alice@example.com');

        expect(filtered).toHaveLength(1);
        expect(filtered[0].volunteers[0].email).toBe('alice@example.com');
    });
});

describe('findNextUpcomingShiftGroup', () => {
    it('skips active groups and returns the next future shift', () => {
        const groups = groupSignupsByShift([
            makeVolunteer({
                shift_signups: [
                    makeSignup({ id: 1, shiftId: 10, startsAt: '2026-03-14T09:00:00Z', endsAt: '2026-03-14T11:00:00Z' }),
                    makeSignup({ id: 2, shiftId: 20, startsAt: '2026-03-14T13:00:00Z', endsAt: '2026-03-14T15:00:00Z' }),
                ],
            }),
        ], new Date('2026-03-14T10:00:00Z'));

        const nextUpcoming = findNextUpcomingShiftGroup(groups, new Date('2026-03-14T10:00:00Z'));

        expect(nextUpcoming?.shiftId).toBe(20);
    });

    it('returns null when all groups are in the past', () => {
        const groups = groupSignupsByShift([
            makeVolunteer({
                shift_signups: [
                    makeSignup({ id: 1, shiftId: 10, startsAt: '2026-03-14T07:00:00Z', endsAt: '2026-03-14T08:00:00Z' }),
                ],
            }),
        ], new Date('2026-03-14T10:00:00Z'));

        expect(findNextUpcomingShiftGroup(groups, new Date('2026-03-14T10:00:00Z'))).toBeNull();
    });
});
