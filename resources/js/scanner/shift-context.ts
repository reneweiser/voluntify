import type { ShiftSignup, Volunteer } from './types';

export type ShiftStatus = 'attended' | 'missed' | 'active' | 'upcoming';
export type ShiftGroupStatus = 'past' | 'active' | 'upcoming';

export interface ClassifiedShift {
    signupId: number;
    jobName: string;
    startsAt: string;
    endsAt: string;
    status: ShiftStatus;
}

export interface ShiftGroupVolunteerRow {
    volunteerId: number;
    firstName: string;
    lastName: string;
    name: string;
    email: string;
    phone: string | null;
    signupId: number;
    shiftId: number;
    status: ShiftStatus;
}

export interface ShiftGroup {
    shiftId: number;
    jobName: string;
    displayText: string;
    startsAt: string;
    endsAt: string;
    groupStatus: ShiftGroupStatus;
    isNextUpcoming: boolean;
    volunteers: ShiftGroupVolunteerRow[];
}

function classifySignup(signup: ShiftSignup, now: Date): ClassifiedShift {
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
}

function getShiftGroupStatus(startsAt: string, endsAt: string, now: Date): ShiftGroupStatus {
    const startsAtDate = new Date(startsAt);
    const endsAtDate = new Date(endsAt);

    if (now >= endsAtDate) {
        return 'past';
    }

    if (now >= startsAtDate) {
        return 'active';
    }

    return 'upcoming';
}

export function classifyShifts(signups: ShiftSignup[], now: Date): ClassifiedShift[] {
    return signups.map((signup) => classifySignup(signup, now));
}

export function groupSignupsByShift(volunteers: Volunteer[], now: Date): ShiftGroup[] {
    const groups = new Map<number, ShiftGroup>();

    for (const volunteer of volunteers) {
        const shiftsBySignupId = new Map(
            classifyShifts(volunteer.shift_signups, now).map((shift) => [shift.signupId, shift])
        );

        for (const signup of volunteer.shift_signups) {
            const classifiedShift = shiftsBySignupId.get(signup.id);
            if (!classifiedShift) {
                continue;
            }

            const shiftId = signup.shift.id;
            if (!groups.has(shiftId)) {
                groups.set(shiftId, {
                    shiftId,
                    jobName: signup.shift.volunteer_job.name,
                    displayText: signup.shift.display_text,
                    startsAt: signup.shift.starts_at,
                    endsAt: signup.shift.ends_at,
                    groupStatus: getShiftGroupStatus(signup.shift.starts_at, signup.shift.ends_at, now),
                    isNextUpcoming: false,
                    volunteers: [],
                });
            }

            groups.get(shiftId)?.volunteers.push({
                volunteerId: volunteer.id,
                firstName: volunteer.first_name,
                lastName: volunteer.last_name,
                name: volunteer.name,
                email: volunteer.email,
                phone: volunteer.phone,
                signupId: signup.id,
                shiftId,
                status: classifiedShift.status,
            });
        }
    }

    const sortedGroups = Array.from(groups.values())
        .map((group) => ({
            ...group,
            volunteers: [...group.volunteers].sort((left, right) => {
                return left.name.localeCompare(right.name)
                    || left.email.localeCompare(right.email)
                    || left.signupId - right.signupId;
            }),
        }))
        .sort((left, right) => {
            return new Date(left.startsAt).getTime() - new Date(right.startsAt).getTime()
                || new Date(left.endsAt).getTime() - new Date(right.endsAt).getTime()
                || left.jobName.localeCompare(right.jobName)
                || left.shiftId - right.shiftId;
        });

    const nextUpcomingShift = findNextUpcomingShiftGroup(sortedGroups, now);

    return sortedGroups.map((group) => ({
        ...group,
        isNextUpcoming: nextUpcomingShift?.shiftId === group.shiftId,
    }));
}

export function requiresShiftSearchHint(query: string): boolean {
    const trimmedQuery = query.trim();

    return trimmedQuery.length > 0 && trimmedQuery.length < 2;
}

export function filterShiftGroups(groups: ShiftGroup[], query: string): ShiftGroup[] {
    const trimmedQuery = query.trim().toLowerCase();

    if (trimmedQuery.length === 0) {
        return groups;
    }

    if (requiresShiftSearchHint(query)) {
        return [];
    }

    return groups
        .map((group) => ({
            ...group,
            volunteers: group.volunteers.filter((volunteer) => {
                return volunteer.firstName.toLowerCase().includes(trimmedQuery)
                    || volunteer.lastName.toLowerCase().includes(trimmedQuery)
                    || volunteer.email.toLowerCase().includes(trimmedQuery);
            }),
        }))
        .filter((group) => group.volunteers.length > 0);
}

export function findNextUpcomingShiftGroup(groups: ShiftGroup[], now: Date): ShiftGroup | null {
    return groups.find((group) => new Date(group.startsAt) > now) ?? null;
}
