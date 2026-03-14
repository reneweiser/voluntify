import type { ShiftSignup } from './types';

export type ShiftStatus = 'attended' | 'missed' | 'active' | 'upcoming';

export interface ClassifiedShift {
    signupId: number;
    jobName: string;
    startsAt: string;
    endsAt: string;
    status: ShiftStatus;
}

export function classifyShifts(signups: ShiftSignup[], now: Date): ClassifiedShift[] {
    return signups.map((signup) => {
        const startsAt = new Date(signup.shift.starts_at);
        const endsAt = new Date(signup.shift.ends_at);

        let status: ShiftStatus;

        if (signup.attendance_record) {
            status = signup.attendance_record.status === 'no_show' ? 'missed' : 'attended';
        } else if (now >= endsAt) {
            status = 'missed';
        } else if (now >= startsAt) {
            status = 'active';
        } else {
            status = 'upcoming';
        }

        return {
            signupId: signup.id,
            jobName: signup.shift.volunteer_job.name,
            startsAt: signup.shift.starts_at,
            endsAt: signup.shift.ends_at,
            status,
        };
    });
}
