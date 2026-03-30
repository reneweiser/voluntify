# Ubiquitous Language

This glossary defines the shared vocabulary used throughout Voluntify. Consistent terminology across conversations, documentation, and the product itself reduces confusion and helps everyone -- from new team members to long-time organizers -- stay on the same page.

## People & Roles

**Organisation** -- The group, nonprofit, or club that creates and runs projects. Every project, event, team member, and volunteer exists within an organisation. A person can belong to multiple organisations.

> Example: "SKHC e.V." is an organisation that manages the "Hochschulball 2026" project.

**Organizer** -- A staff role with full administrative access. Organizers have permanent accounts and can create projects, events, manage team members, configure settings, and access all data. Organizers exist at two levels:
- **Org-Level Organizer** -- automatic access to all projects and events in the organisation.
- **Project-Level Organizer** -- access to all events within that project only.

> Why two levels? A club president needs access to everything (org-level), while a hired event coordinator only needs access to the events they manage (project-level).

**Volunteer Admin** -- A scanner-based role responsible for on-the-ground shift management. Volunteer Admins **do not have permanent accounts** -- they receive a temporary scanner link via email that is valid for a configured time window. They can check in volunteers, mark attendance, and track gear pickup via the Volunteer Admin Scanner.

> Example: A trusted volunteer is promoted to Volunteer Admin for the evening shift. They receive a scanner link 30 minutes before their time window starts.

**Entry Staff** -- A scanner-based role responsible for scanning tickets at the venue entrance. Entry Staff **do not have permanent accounts** -- like Volunteer Admins, they receive a temporary scanner link. Their view is limited to QR scanning and a guest list.

> Why no permanent accounts for Volunteer Admin and Entry Staff? These roles are typically filled by volunteers or short-term helpers who work one event. Giving them scanner-only access via a temporary link keeps security tight and onboarding frictionless.

**Member** -- Any user who belongs to an organisation in a staff role with a permanent account. Currently, only Organizers are permanent members. Members log into the admin interface with email and password.

**Volunteer** -- A person who signs up to help at an event. Volunteers do not need an account -- they interact via magic links and a public signup flow. A volunteer belongs to a **project** (not to a single event) and can sign up for shifts across multiple events in that project.

> Example: A volunteer signs up for "Hochschulball 2026" and selects shifts at both the "Aufbautag" and "Hauptabend" events -- they remain one volunteer record in the project.

## Projects & Events

**Project** -- The mandatory top-level container for organising events. Every event belongs to a project. A project groups related events (e.g. all events for a festival), holds shared resources (Gear, Custom Fields, Scanner configs, Volunteers), and has its own public website.

> Example: The project "Hochschulball 2026" contains the events "Aufbautag", "Hauptabend", and "Abbautag". All volunteers, T-Shirt gear, and custom fields are shared across these events.

> Why mandatory? Without a project, shared resources like gear or custom fields would need to be duplicated per event. The project is the natural home for cross-event data.

**Project Website** -- A permanent public page at `/p/{token}` that lists all published events of a project. Activated on the first publish of any event. Before first publish, logged-in Organizers see a preview; everyone else gets a 404.

**Event** -- A specific occasion within a project (e.g. "Aufbautag", "Hauptabend") with a date, location, and volunteer needs.

**Event Lifecycle** -- Events move through four stages:

```text
Draft --> Published Open <--> Published Closed --> Archived
```

- **Draft** -- Being set up. Not visible on the project website. Can be taken back to Draft from Published for maintenance.
- **Published Open** -- Live on the project website with a signup CTA. Volunteers can sign up.
- **Published Closed** -- Visible on the project website with an "Anmeldung abgelaufen" label. No new signups. Triggered by a deadline or manually.
- **Archived** -- Completed. Read-only. Removed from the project website. Must be archived before the project can be fully cleaned up.

> Why four stages instead of three? "Published Open" and "Published Closed" are distinct because an event may still be visible for informational purposes (location, schedule) even after signups close. The old model conflated "visible" with "accepting signups".

**Volunteer Job** -- A named function at an event, such as "Einlass", "Bar", or "Bühne". Jobs carry descriptions and optional instructions (cheat sheets).

**Shift** -- A time slot within a job that volunteers sign up for. Each shift has a date (always required), optional start and end times, optional custom display text, and a capacity.

> Example: A shift can be "10:00--14:00 Uhr" (exact times), "ab 10:00 Uhr" (start only), "nach Bedarf" (custom text, no times), or "10:00 Uhr -- bis Veranstaltungsende" (start time with custom end text).

> Why are times optional? Some jobs don't have fixed hours -- setup crews may work "as needed". Custom display text handles these cases without forcing organizers to invent fake times.

**Capacity** -- The maximum number of volunteers for a shift. Once full, the shift shows a "Voll" badge and blocks further signups.

## Signup & Verification

**Signup Flow** -- A multi-step process for volunteer registration:

1. **Schritt 1 -- E-Mail:** Volunteer enters their email. New users verify via email; returning users receive a magic link.
2. **Schritt 2 -- Daten + Schichtauswahl:** Personal data (Vorname, Nachname, optional Telefon) followed by shift selection. A 20-minute reservation timer starts.
3. **Schritt 3 -- Custom Fields + Gear:** Project-level and event-level custom fields, plus Typ-1 gear selection (e.g. T-shirt size).
4. **Schritt 4 -- Zusammenfassung:** Review and confirm with "Verbindlich anmelden".

> Why data before shifts? Collecting personal data first means the volunteer's identity is known before they occupy capacity. If the timer expires, their reservation is released cleanly.

**Email Verification (Double Opt-In)** -- New volunteers must confirm their email before proceeding. The verification link/code is valid for 24 hours. No shifts are reserved during verification -- the 20-minute timer only starts after verification is complete.

**Shift Reservation** -- When a volunteer begins Schritt 2, selected shifts are temporarily held for 20 minutes. If the timer expires, spots are released for others. The countdown is visible during Schritt 2--4.

**Overlap Check** -- The system prevents a volunteer from signing up for shifts with overlapping times, even across different jobs. Directly adjacent shifts (12:00--14:00 and 14:00--16:00) are allowed. Shifts without times skip the overlap check silently.

**Cancellation** -- Self-service shift cancellation by volunteers, if the Organizer has enabled it at project level. Can include a deadline (e.g. "not later than 24 hours before shift start"). Freed capacity is immediately available.

**Manual Volunteer Creation** -- An Organizer can create a volunteer record directly (bypassing the signup flow). Only email is required; all other fields are optional. The volunteer receives an email with a portal link to complete their registration.

> Example: An Organizer adds a volunteer who was recruited in person. They enter only the email -- the volunteer completes name, shift selection, and T-shirt size via the portal later.

## Tickets & Scanning

**QR Code** -- A scannable code issued to each volunteer, valid for an entire project. One QR code per project membership -- it covers all events. The code is never regenerated.

> Why per project, not per event? A volunteer helping at both "Aufbautag" and "Hauptabend" needs only one code. The scanner checks shift eligibility per event at scan time.

**Magic Link** -- A unique URL sent to a volunteer's email that grants access to the Helfer-Portal without a password. Used for both signup continuation and portal access. Expires after a configured period; volunteers can request a new one.

**Scanner** -- A mobile-first web interface used by Entry Staff and Volunteer Admins to process volunteers. Scanners are configured at project level, each with a type, scope, time window, and assigned operators.

**Entry Staff Scanner** -- Scanner type for entrance control. Shows full-screen color-coded results:
- **Green** -- valid ticket, access granted (shows volunteer name).
- **Yellow** -- already checked in (shows name + last scan time/location).
- **Red** -- no access (shows reason + "Nächsten scannen" button).

After each result, a **manual "Nächsten scannen" button** must be tapped to proceed. No auto-dismiss.

> Why no auto-dismiss? At crowded entrances, accidental taps could dismiss results before the operator reads them. The manual button ensures every result is consciously acknowledged.

**Volunteer Admin Scanner** -- Scanner type for shift management and gear pickup. Modes: check-in only, gear pickup only, or both. Shows a detailed volunteer view (name, shifts, gear status). Includes a **Schichtliste** tab for browsing all volunteers per shift.

**Scanner Time Window** -- Each scanner has a configured start and end time. The scanner is locked outside this window. A 10-minute countdown warning appears before expiry. Organizers can extend the time window after it expires (e.g. for late gear pickup).

**Offline Scanning** -- Scanners cache volunteer data in encrypted IndexedDB for offline use. Data auto-expires at the end of the time window or after 3 days. Online/offline sync is automatic.

**Eligibility** -- A volunteer can use their QR code (for entry or gear pickup) only if they have at least one shift in the past or future. A volunteer with zero shifts is not eligible.

## Attendance

**Event Arrival** -- The record that a volunteer physically showed up at the venue. Recorded by Entry Staff via QR scan or manual lookup. Distinct from shift attendance.

**Attendance Record** -- Whether a volunteer reported to their assigned shift. Recorded by Volunteer Admin. Statuses: **On Time**, **Late**, **No Show**.

**Attendance Grace Period** -- An optional per-event setting defining how many minutes after a shift starts a scan is still "On Time". After the grace window: "Late". If not set, any arrival after shift start is "Late".

**Automatic No-Show Detection** -- Volunteers are auto-marked "No Show" if their shift ended 2+ hours ago with no attendance record.

## Gear

**Gear** -- Equipment or materials provided to volunteers. Defined at **project level** (not per event) so a volunteer receives e.g. one T-shirt for the entire project, not one per event.

**Typ 1 (Größenauswahl)** -- Gear with a selection option (e.g. T-shirt size, jacket color). One entitlement per volunteer. The volunteer chooses during signup (Schritt 3). States are **configurable by the Organizer** -- not hardcoded.

> Example: An Organizer defines a "T-Shirt" gear item with states "Ausstehend", "Abgeholt", "Nicht vorrätig". Another Organizer might use "Bereit", "Ausgegeben", "Rückgabe ausstehend" for a walkie-talkie.

> Why configurable states? Different organisations have different workflows. A food festival tracking meal vouchers has different states than a music festival tracking radios. Hardcoded enums would force one-size-fits-all.

**Typ 2 (Mengenausgabe)** -- Gear tracked by quantity (e.g. drink tokens, meal vouchers). Multiple pickups tracked individually. Scanner shows "2 / 3 abgeholt".

**"Auswahl ausstehend"** -- A scanner status shown for Typ-1 gear when the volunteer hasn't made their selection yet (e.g. Organizer created the volunteer manually without choosing a T-shirt size). Gear pickup is blocked until the volunteer completes the selection via the portal.

> Why generic "Auswahl ausstehend" instead of "Größe ausstehend"? Typ-1 options aren't always sizes -- they could be colors, variants, or preferences. The label must be universal.

**Gear Pickup** -- Always recorded via scanner (Volunteer Admin Scanner). No web UI override. All Typ-1 state changes happen on the scanner. Typ-2 pickups require an internet connection (to prevent double-counting via race conditions).

## Communication

**Email Template** -- A customizable email template configured per event. Falls back to system defaults if no custom template is set. Templates support placeholders like `{{vorname}}`, `{{nachname}}`, `{{portal_link}}`, `{{kontakt_email}}`.

**System Email Types:**

| Type | Trigger |
|---|---|
| `signup_confirmation` | Signup completed |
| `email_verification` | New user verifies email |
| `volunteer_welcome` | After verification -- portal link |
| `volunteer_added_by_organizer` | Organizer manually creates volunteer |
| `pre_shift_reminder_24h` | 24h before shift |
| `pre_shift_reminder_4h` | 4h before shift |
| `event_updated` | Re-publish after maintenance |
| `event_announcement` | Manual announcement by Organizer |
| `staff_invitation` | Invitation as team member |
| `volunteer_promoted` | Volunteer promoted to staff |
| `added_to_org` | User added to organisation |

**Announcement** -- A manual, free-form email sent by an Organizer to a filtered group of volunteers at project level. Recipients are selected via combinable filters (Event > Job > Shift). Can be sent immediately or scheduled. Announcements are email-only (no portal banner). Reusable Announcement Templates can be saved at organisation level.

> Example: "Schicht Kuchenausgabe: Bitte keine Teller mitbringen" -- sent only to volunteers in that specific shift.

**Email Sender Configuration** -- SMTP can be configured at organisation level (default for all projects) and optionally overridden per project. Event-level config is limited to a notification email address for organizer-facing alerts (e.g. cancellation digests).

## Organisation & Settings

**Public Token** -- A random, unguessable string used in URLs (`/p/{token}` for project websites, `/event/{token}` for direct event links). Prevents URL guessing.

**Volunteer Promotion** -- Elevating a volunteer to a staff role. Promote to Volunteer Admin: assigned to a specific scanner. Promote to Organizer: a user account is created, invitation email sent.

> Note: Entry Staff is **not** promoted via "Promote to Staff" -- they are assigned directly through the Entry Staff Scanner configuration.

**Activity Log** -- An audit trail of actions within an organisation. Tracks invitations, role changes, member removals, brute-force lockouts, and more. Visible to Organizers.

**Helfer-Portal (Volunteer Portal)** -- A self-service page where volunteers view their shifts, QR code, gear status, personal data, and attendance history. Accessed via magic link -- no account needed. Shows all events within the project context.

**Clone** -- Duplicating a project or individual event. Creates a Draft copy with structure (jobs, shifts, gear definitions, custom fields, email templates, scanner configs) but without volunteers, signups, or announcements. Optional date offset shifts all dates forward.