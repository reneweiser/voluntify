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

export interface AttendanceRecord {
    id: number;
    shift_signup_id: number;
    status: 'on_time' | 'late' | 'no_show';
}

export interface ShiftSignup {
    id: number;
    shift: Shift;
    attendance_record?: AttendanceRecord | null;
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

/**
 * Ed25519 public keys (base64-encoded, 32 bytes each) for offline JWT verification.
 * These are public verification keys only — they cannot sign/forge tokens.
 */
export interface ScannerKeys {
    current: string;
    previous: string;
}

export interface OutboxEntry {
    id?: number;
    type: 'arrival' | 'attendance';
    ticket_id?: number;
    volunteer_id?: number;
    method?: 'qr_scan' | 'manual_lookup';
    scanned_at: string;
    shift_signup_id?: number;
    status?: 'on_time' | 'late' | 'no_show';
}

export interface ScannerData {
    event: { id: number; name: string; attendance_grace_minutes: number | null };
    user_role: 'organizer' | 'entrance_staff' | 'volunteer_admin' | null;
    volunteers: Volunteer[];
    arrivals: ArrivalRecord[];
    attendance_records: AttendanceRecord[];
    keys: ScannerKeys;
}
