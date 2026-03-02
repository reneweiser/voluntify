export interface VolunteerJob {
    id: number;
    name: string;
}

export interface Shift {
    id: number;
    starts_at: string;
    ends_at: string;
    volunteer_job: VolunteerJob;
}

export interface ShiftSignup {
    id: number;
    shift: Shift;
}

export interface Ticket {
    id: number;
    jwt_token: string;
    volunteer_id: number;
    event_id: number;
}

export interface Volunteer {
    id: number;
    name: string;
    email: string;
    ticket: Ticket;
    shift_signups: ShiftSignup[];
}

export interface ArrivalRecord {
    id: number;
    ticket_id: number;
    volunteer_id: number;
    event_id: number;
    scanned_by: number;
    scanned_at: string;
    method: 'qr_scan' | 'manual_lookup';
    flagged: boolean;
    flag_reason: string | null;
}

export interface ScannerKeys {
    current: string;
    previous: string;
}

export interface OutboxEntry {
    id?: number;
    ticket_id: number;
    volunteer_id: number;
    method: 'qr_scan' | 'manual_lookup';
    scanned_at: string;
}

export interface ScannerData {
    event: { id: number; name: string };
    volunteers: Volunteer[];
    arrivals: ArrivalRecord[];
    keys: ScannerKeys;
}
