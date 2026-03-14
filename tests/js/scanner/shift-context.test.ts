import { describe, it, expect } from 'vitest';
import { classifyShifts, type ClassifiedShift } from '../../../resources/js/scanner/shift-context';
import type { ShiftSignup } from '../../../resources/js/scanner/types';

function makeSignup(overrides: Partial<{
    id: number;
    startsAt: string;
    endsAt: string;
    jobName: string;
    attendanceRecord: ShiftSignup['attendance_record'];
}>): ShiftSignup {
    return {
        id: overrides.id ?? 1,
        shift: {
            id: 1,
            starts_at: overrides.startsAt ?? '2026-03-14T10:00:00Z',
            ends_at: overrides.endsAt ?? '2026-03-14T12:00:00Z',
            volunteer_job: { id: 1, name: overrides.jobName ?? 'Gate Watch' },
        },
        attendance_record: overrides.attendanceRecord ?? null,
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
