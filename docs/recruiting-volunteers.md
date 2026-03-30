# Recruiting Volunteers

This guide covers publishing events, sharing the project website, and the complete volunteer experience from signup to portal access.

## Publish Your Event

Before volunteers can sign up, your event must be published. Publishing is blocked until at least one shift exists -- this prevents publishing an empty event.

1. Go to the event's **Übersicht** page.
2. Click **Veröffentlichen**. The event moves to **Published Open** status.

On the first publish of any event in a project, the **project website** is permanently activated at `/p/{token}`.

> Why block publishing without shifts? An event without shifts cannot accept signups. Publishing it would only confuse volunteers who arrive at a page with nothing to sign up for.

## Share the Project Website

The project website is the main entry point for volunteers. It lists all published events with their status:

- **Published Open** -- shown with a signup button and deadline
- **Published Closed** -- shown with an "Anmeldung abgelaufen" label
- **Draft / Archived** -- hidden

Share the project URL (`/p/{token}`) via email, social media, your organisation's website, flyers, etc.

> Example: SKHC shares `voluntify.example.com/p/abc123` on their Instagram story. Volunteers see the "Hochschulball 2026" project page with cards for "Aufbautag" and "Hauptabend" -- both with signup buttons.

You can also share a direct event link (`/event/{token}`) to point volunteers to a specific event.

**Before first publish:** Logged-in Organizers see a **preview** of the project website (with a "Vorschau" banner). All other visitors get a 404.

> Why preview for Organizers? It lets you verify the website looks right before going live, without accidentally showing an incomplete page to the public.

## Signup Flow

When a volunteer clicks "Anmelden" on the project website, they enter a multi-step signup flow:

### Schritt 1 -- E-Mail

The volunteer enters their email address.

- **New volunteer:** Receives a verification email (6-digit code or link, valid 24 hours). No shifts are reserved during verification.
- **Returning volunteer:** Receives a magic link that takes them directly to Schritt 2 for the requested event. A brief note shows their existing signups.

> Why separate verification from signup? Verification confirms the email is real. The 20-minute reservation timer only starts after verification to avoid blocking shifts for unverified addresses.

### E-Mail-Verifizierung (neue Volunteers)

After clicking the verification link/code:
1. The volunteer is verified in the system.
2. A **Willkommens-E-Mail** (`volunteer_welcome`) is sent with a magic link to the Helfer-Portal.
3. Clicking the magic link in the signup flow opens Schritt 2 directly.

> Example: Lisa enters her email, receives "Bitte bestätige deine E-Mail", clicks the link, gets a "Du bist jetzt dabei" welcome email, and lands on the shift selection page.

### Schritt 2 -- Persönliche Daten + Schichtauswahl

**Data first, then shifts.** A 20-minute countdown starts at the beginning of this step and is visible throughout.

1. **Persönliche Daten:** Vorname, Nachname (separate fields), E-Mail (pre-filled), optional Telefonnummer (if enabled in event settings).
2. **Schichtauswahl:** Browse available shifts with capacity indicators. Select one or more shifts.

The system checks for **overlapping shifts** in real-time:
- Overlapping shifts across different jobs are blocked with a UX warning + server validation.
- Adjacent shifts (12:00--14:00 and 14:00--16:00) are allowed.
- Shifts without times skip the overlap check silently.

> Why data before shifts? Knowing the volunteer's identity before they occupy capacity allows cleaner reservation handling. If the timer expires, the system knows who to notify.

### Schritt 3 -- Custom Fields + Gear

The countdown continues from Schritt 2.

1. **Projektfelder** -- Project-level custom fields (asked once per project, pre-filled on return).
2. **Typ-1 Gear-Auswahl** -- e.g. T-shirt size selection.
3. **Eventfelder** -- Event-specific custom fields (asked for each event separately).

> Example: The project has a "Diätanforderungen" field (asked once). The event has a "Parkplatz benötigt?" field (asked for each event). The volunteer sees both in this step.

### Schritt 4 -- Zusammenfassung

Overview of all entered data. Final button: **Verbindlich anmelden**.

After confirmation:
- Signup is complete.
- A `signup_confirmation` email is sent with event details, shift info, and a portal link.
- The QR code (one per project, valid for all events) is accessible via the Helfer-Portal.

### Rate Limiting

| Action | Limit |
|---|---|
| E-Mail-Verifizierung / Magic Links | 3× pro Stunde (konfigurierbar) |
| QR / Scanner Resend | 1× alle 5 Minuten (fest) |
| 5 Fehlversuche | 30 Min. Sperrung + Activity Log Eintrag |

## Helfer-Portal (Volunteer Portal)

Access via magic link -- no login required. Shows all information for the volunteer's project membership.

### Sections

1. **Nächste Schicht** -- Banner with date, job, and time of the next upcoming shift.
2. **Schichten** -- Grouped by event. Each shift shows job, time, and status. Cancellation button (if enabled).
3. **Gear** -- Typ-1 status (from the organizer's configurable state list) and Typ-2 pickup counts.
4. **Anmeldedaten** -- Vorname, Nachname, E-Mail, Telefon, and custom field responses.
5. **QR-Code** -- The project-wide QR code. Resend button (1× per 5 minutes). Maintenance banner if any event is in Draft.
6. **Anwesenheitsstatus** -- Per past shift: On Time / Late / No Show.

**Expired magic link?** The portal shows a form to request a new magic link via email.

**Incomplete registration?** If the Organizer created the volunteer manually and data is missing, a banner says "Bitte vervollständige deine Registrierung" with a link to the missing fields.

### Shift Cancellation

Available only if the Organizer has enabled it at project level:
1. Find the shift under **Schichten**.
2. Click **Absagen**.
3. Confirm in the dialog.

The spot is freed immediately. The Organizer receives a cancellation digest (every 6 hours) at the event's notification email.

Cancellation is blocked if:
- The Organizer set a deadline and it has passed (e.g. "not later than 24 hours before shift start").
- Cancellation is disabled for the project.

## Tips for Recruiting

- **Use the project website** -- Share one URL for all events instead of individual links.
- **Customize email templates** -- Add event-specific info to confirmations and reminders (see [Creating Events](creating-events.md)).
- **Add job instructions** -- Published as cheat sheets linked from the signup page and included in reminder emails.
- **Send announcements** -- Use the Announcements feature to send targeted emails to specific shifts or events (see [Creating Events > Send Announcements](creating-events.md#send-announcements)).
- **Monitor signups** -- Check the project's volunteer list to see who signed up and which shifts need people.