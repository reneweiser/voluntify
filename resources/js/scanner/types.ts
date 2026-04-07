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
    project_id: number;
}

export interface Volunteer {
    id: number;
    first_name: string;
    last_name: string;
    name: string;
    email: string;
    ticket: Ticket;
    shift_signups: ShiftSignup[];
}

export interface GearItem {
    id: number;
    name: string;
    type: 'size_selection' | 'quantity';
    available_sizes: string[] | null;
    available_states: string[] | null;
}

export interface VolunteerGearPickup {
    state: string | null;
    quantity: number;
    picked_up_at: string | null;
}

export interface VolunteerGear {
    id: number;
    project_gear_item_id: number;
    size: string | null;
    picked_up: boolean;
    pickups: VolunteerGearPickup[];
}

export interface ArrivalRecord {
    id: number;
    ticket_id: number;
    volunteer_id: number;
    event_id: number;
    scanned_by: number | null;
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

export interface GuestEntryGearItem {
    id: number;
    gear_item_name: string;
    gear_item_type: 'size_selection' | 'quantity';
    available_sizes?: string[] | null;
    available_states?: string[] | null;
    quantity: number;
    picked_up_count: number;
    selection: string | null;
    status: string | null;
}

export interface GuestEntry {
    id: number;
    guest_group_id: number;
    group_label: string;
    group_guest_count: number;
    number: number;
    name: string | null;
    qr_token?: string | null;
    checked_in_at: string | null;
    gear: GuestEntryGearItem[];
}

export interface OutboxEntry {
    id?: number;
    type: 'arrival' | 'attendance' | 'guest_checkin';
    ticket_id?: number;
    volunteer_id?: number;
    event_id?: number;
    method?: 'qr_scan' | 'manual_lookup';
    scanned_at: string;
    shift_signup_id?: number;
    status?: 'on_time' | 'late' | 'no_show';
    guest_entry_id?: number;
}

