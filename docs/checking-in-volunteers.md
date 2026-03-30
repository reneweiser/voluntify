# Checking In Volunteers

This guide covers the two scanner types -- Entry Staff Scanner and Volunteer Admin Scanner -- including setup, operation, offline mode, and gear pickup.

## Scanner Types

Voluntify has two scanner types, each designed for a specific role:

| Scanner | Purpose | Operated By |
|---|---|---|
| **Entry Staff Scanner** | Entrance control via QR code | Entry Staff |
| **Volunteer Admin Scanner** | Check-in, attendance, gear pickup | Volunteer Admin |

Both scanner types are configured at **project level** under Projekt > Scanner. Each scanner has a type, scope (event or project), time window, assigned operators, and optional hint text.

> Why two scanner types? Entry Staff only need a fast, clear yes/no for entrance. Volunteer Admins need detailed volunteer views for attendance and gear management. Combining both into one would clutter the Entry Staff's workflow.

## Scanner Setup (Organizer)

1. Go to Projekt > **Scanner**.
2. Click **Scanner hinzufügen**.
3. Configure:
   - **Typ** -- Entry Staff or Volunteer Admin
   - **Scope** -- Which event(s) this scanner covers
   - **Zeitfenster** -- Start and end time
   - **Modi** (Volunteer Admin only) -- Check-in, Gear Pickup, or both
   - **Gear Items** (if Gear Pickup mode) -- Which items this scanner can distribute
   - **Zugewiesene Personen** -- Email addresses of operators
   - **Hinweistext** -- Optional text shown to the operator (e.g. "Bitte Ausweis kontrollieren")
4. Save.

Operators receive their scanner link **30 minutes before the time window** (or immediately if < 30 minutes remain). On first open, they enter a one-time authentication code.

**Scanner Status:** Geplant / Aktiv / Abgelaufen

### Extending the Time Window

If gear pickup or check-in runs longer than planned, Organizers can **extend the time window** after it expires:

1. Go to Projekt > Scanner.
2. Find the expired scanner.
3. Edit the time window end time.
4. Save -- the scanner becomes active again.

> Example: T-shirt pickup was scheduled until 22:00 but volunteers are still in line at 22:15. The Organizer extends the window to 23:00 from the admin panel. No scanner restart needed.

## Entry Staff Scanner

The Entry Staff Scanner provides a fast, full-screen QR scanning experience optimized for high-throughput entrance control.

### Scan Flow

1. The camera viewfinder fills the screen.
2. Point at the volunteer's QR code.
3. The scanner validates automatically -- no button press needed.
4. A full-screen result appears.

### Result Screens

| Color | Meaning | Shows |
|---|---|---|
| 🟢 **Grün** | Zugriff erlaubt | Volunteer name |
| 🟡 **Gelb** | Bereits eingecheckt | Name + wann/wo letzter Scan |
| 🔴 **Rot** | Kein Zugriff | Grund + "Nächsten scannen" Button |

**After every result: the screen stays until the operator taps "Nächsten scannen".** There is no auto-dismiss and no "tap anywhere" to clear.

> Why no auto-dismiss? At a crowded entrance, accidental touches or bumps could dismiss a result before the operator reads it. The manual button ensures every result is consciously acknowledged. This applies to all three states -- green, yellow, and red.

### Eligibility

A volunteer's QR code is valid if they have at least one shift in the past or future. A volunteer with zero shifts (e.g. all shifts cancelled) gets a red screen.

### Tabs

1. **QR-Scanner** -- Camera viewfinder
2. **Gastliste** (optional) -- Browse all eligible volunteers

## Volunteer Admin Scanner

The Volunteer Admin Scanner is designed for on-the-ground shift management: check-in, attendance marking, and gear pickup.

### Modes

The Organizer configures which modes are active for each scanner:

- **Check-in only** -- Mark volunteers as arrived for their shifts
- **Gear Pickup only** -- Distribute gear items
- **Both** -- Check-in first, then gear pickup (sequential flow)

### Scan Flow

1. Scan QR code or use manual search.
2. **Eligibility check** -- Does the volunteer have a shift?
3. **Check-in** (if mode active) -- Shows the volunteer's shifts. Mark attendance per shift.
4. **Gear Pickup** (if mode active) -- Shows the volunteer's gear items with current status.

**After each action, the result screen stays until the operator taps "Nächsten scannen".**

### Volunteer View After Scan

- Name (Vorname + Nachname), masked phone number
- Shifts with status indicators
- Gear status per item
- No gear assigned → neutral message "Kein Gear zugewiesen" (no error/red -- this is not an error state)

> Why a neutral message instead of an error? Not every volunteer has gear. A gear-only scanner should not alarm the operator when a volunteer simply has no gear items. Neutral grey, not red.

### Gear Pickup via Scanner

**All gear status changes happen exclusively through the scanner -- no web UI override.**

**Typ 1 (Größenauswahl, e.g. T-Shirt):**
1. Current status shown (from the project's configurable state list).
2. Operator taps to select the next state from the full list.
3. Status updated, feedback shown, "Nächsten scannen" to continue.

> Example: The Organizer defined states "Ausstehend", "Abgeholt", "Nicht vorrätig" for T-shirts. The scanner shows "Ausstehend" and offers all three options. The operator taps "Abgeholt".

**Typ 1 -- "Auswahl ausstehend":** If the volunteer hasn't selected their option yet (e.g. Organizer created them manually without a T-shirt size), the scanner shows "Auswahl ausstehend" and blocks pickup. The volunteer must select via the portal first.

**Typ 2 (Mengenausgabe, e.g. Getränkemarken):**
- Shows "2 / 3 abgeholt" with a tap to record each pickup.
- **Requires internet connection** -- to prevent double-counting via race conditions when multiple scanners operate simultaneously.

### Tabs

1. **Scanner** -- QR scanning and manual search
2. **Schichtliste** -- Chronological list of all shifts. "Jump to current time" button. Shows volunteers per shift.

### Touch Targets

All check-in and gear buttons are designed for comfortable tapping on mobile -- full width, especially important when a volunteer has multiple shifts or gear items.

## Time Window Enforcement

- **Within window:** Scanner operates normally.
- **10 minutes before end:** Warning banner with countdown: "Der Scanner wird in 10 Minuten gesperrt".
- **After expiry:** Scanner locked. Message: "Der Scanner ist außerhalb des Scan-Zeitraums nicht verfügbar". IndexedDB data automatically deleted.

## Offline Mode

Both scanner types work without internet. Here's how:

### Data Caching

When the scanner is opened while online, it downloads and encrypts:
- **Entry Staff:** Name, ticket auth, check-in status (minimal)
- **Volunteer Admin:** Name, shifts, check-in, gear, attendance (extended)

Data is stored in **encrypted IndexedDB** with a session key from the server.

### Offline Operation

- **QR validation:** On-device using cached cryptographic keys. No server call needed.
- **Volunteer lookup:** Searches the locally cached list.
- **Check-in / attendance:** Queued locally, synced automatically when online.

### Data Expiry

- Hard expiry: end of time window OR 3 days (whichever is sooner)
- Auto-deleted on expiry
- Auto-updated when online

### Conflict Handling

If the same volunteer is scanned at two stations while both are offline, the conflict is recorded in the Activity Log when both sync.

## Tips

- **Download data while online:** Always open the scanner while connected before the event starts.
- **Multiple scanners:** Multiple Entry Staff can scan at different entrances simultaneously. Duplicate scans show yellow ("already checked in").
- **Install as PWA:** For the best mobile experience, install as a Progressive Web App (Android: browser menu > "Install app"; iOS: Share > "Add to Home Screen").
- **Stale data:** If a volunteer signed up after data was cached, their QR still validates (JWT is self-contained), but they may not appear in the cached list. Tap "Daten aktualisieren" to refresh.