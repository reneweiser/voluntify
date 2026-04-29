import type { ArrivalRecord, ScannerEvent, Volunteer } from './types';

export type ManualArrivalTarget =
    | { kind: 'resolved'; eventId: number; eventIds: number[] }
    | { kind: 'choice_required'; eventIds: number[] }
    | { kind: 'unavailable'; reason: 'missing_event_metadata' | 'no_events' };

function uniqueSorted(values: number[]): number[] {
    return [...new Set(values)].sort((left, right) => left - right);
}

export function filterVolunteers(volunteers: Volunteer[], query: string): Volunteer[] {
    const trimmedQuery = query.trim().toLowerCase();

    if (trimmedQuery.length < 2) {
        return [];
    }

    return volunteers
        .filter((volunteer) => {
            return volunteer.first_name.toLowerCase().includes(trimmedQuery)
                || volunteer.last_name.toLowerCase().includes(trimmedQuery)
                || volunteer.email.toLowerCase().includes(trimmedQuery);
        })
        .sort((left, right) => {
            return left.name.localeCompare(right.name)
                || left.email.localeCompare(right.email)
                || left.id - right.id;
        });
}

export function resolveManualArrivalTarget(volunteer: Volunteer, scannerEvents: ScannerEvent[]): ManualArrivalTarget {
    const scannerEventIds = uniqueSorted(scannerEvents.map((event) => event.id));
    const signupEventIds = uniqueSorted(
        volunteer.shift_signups
            .map((signup) => signup.shift.event_id)
            .filter((eventId): eventId is number => typeof eventId === 'number')
    );

    if (volunteer.shift_signups.length > 0 && signupEventIds.length === 0) {
        return { kind: 'unavailable', reason: 'missing_event_metadata' };
    }

    if (signupEventIds.length === 1) {
        return { kind: 'resolved', eventId: signupEventIds[0], eventIds: signupEventIds };
    }

    if (signupEventIds.length > 1) {
        return { kind: 'choice_required', eventIds: signupEventIds };
    }

    if (scannerEventIds.length === 0) {
        return { kind: 'unavailable', reason: 'no_events' };
    }

    if (scannerEventIds.length === 1) {
        return { kind: 'resolved', eventId: scannerEventIds[0], eventIds: scannerEventIds };
    }

    return { kind: 'choice_required', eventIds: scannerEventIds };
}

export function hasVolunteerArrivalInScope(arrivals: ArrivalRecord[], volunteerId: number, eventIds: number[]): boolean {
    return arrivals.some((arrival) => arrival.volunteer_id === volunteerId && eventIds.includes(arrival.event_id));
}

export function hasVolunteerArrivalForEvent(arrivals: ArrivalRecord[], volunteerId: number, eventId: number | null): boolean {
    if (eventId === null) {
        return false;
    }

    return arrivals.some((arrival) => arrival.volunteer_id === volunteerId && arrival.event_id === eventId);
}
