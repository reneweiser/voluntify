# Creating Events

This guide covers creating and configuring events within a project, including jobs, shifts, email templates, announcements, and the event lifecycle.

Events always belong to a **project**. Create the project first (see [Managing Projects](managing-projects.md)), then add events.

## Create a New Event

1. Open your project.
2. Click **Neues Event**.
3. Fill in:
   - **Name** -- e.g. "Hauptabend" or "Aufbautag"
   - **Datum** -- When the event takes place
   - **Ort** (optional) -- Location
   - **Beschreibung** (optional) -- Details shown on the project website
4. Click **Erstellen**.

The event is created in **Draft** status.

**Who can do this**: Organizer only.

## Edit Event Details

1. Go to the event's **Einstellungen > Allgemein**.
2. Update name, date, location, description, or title image.
3. Additional settings:
   - **Sichtbarkeit** -- Öffentlich (default) or Privat. Private events are not shown on the project website and can only be accessed via a secret direct link (`/event/{token}`). Toggle is changeable at any time, even after publishing.
   - **Anmeldefrist** -- Date/time after which signups automatically close (Published Open → Published Closed).
   - **Telefon-Pflichtfeld** -- Toggle to require phone number during signup.
   - **Attendance Grace Period (Minuten)** -- Minutes after shift start where a scan is still "On Time".
4. Click **Speichern**.

**Who can do this**: Organizer only.

## Define Volunteer Jobs

Jobs describe the roles volunteers fill at your event.

1. Go to **Jobs & Schichten**.
2. Click **Job hinzufügen**.
3. Enter:
   - **Name** -- e.g. "Einlass", "Bar", "Bühne"
   - **Beschreibung** -- What the job involves (shown on signup page)
   - **Anweisungen** -- Detailed info for volunteers (published as cheat sheet, linked from signup page, included in reminder emails)
4. Save.

**Who can do this**: Organizer only.

## Create Shifts

Shifts are time slots within a job. **Date is always required; start and end times are optional.**

1. Within a job on **Jobs & Schichten**, click **Schicht hinzufügen**.
2. Set:
   - **Datum** (required) -- The day of the shift
   - **Startzeit** (optional) -- Toggle to set or omit
   - **Endzeit** (optional) -- Toggle to set or omit
   - **Custom Display Text** (optional) -- Overrides the time display
   - **Kapazität** -- Maximum volunteers
3. Save.

### Time Display Examples

| Configuration | Display |
|---|---|
| Date + Start + End | "Aufbau · 10:00--14:00 Uhr" |
| Date + Start only | "Aufbau · ab 10:00 Uhr" |
| Date + Custom Text | "Aufbau · nach Bedarf" |
| Start + Custom End | "Aufbau · 10:00 Uhr -- bis Veranstaltungsende" |

> Why optional times? Some jobs don't have fixed hours. Setup crews may work "as needed", cleanup crews "until everything is done". Custom display text handles these cases without forcing organizers to invent fake times.

A confirmation dialog appears when saving a shift without start or end time, to prevent accidental omission.

### Effects of Missing Times

| Feature | Behavior |
|---|---|
| Reminder emails | With start time: 24h and 4h reminder. Without: reminder at 03:00 on shift day |
| Scanner shift list | Shifts without time sorted first ("always on top") |
| Overlap check | With times: normal check. Without times: silently skipped |
| Volunteer portal | Shows configured time or custom text |

**Who can do this**: Organizer only.

## Event Lifecycle

Events move through four stages:

```text
Draft --> Published Open <--> Published Closed --> Archived
```

### Publish (Draft → Published Open)

1. Go to **Übersicht**.
2. Click **Veröffentlichen**.

**Blocked if no shifts exist.** On first publish of a **public** event in the project, the project website is activated. Private events do not activate the project website.

**Private events** are published the same way but remain invisible on the project website. Share the direct link (`/event/{token}`) with the intended participants.

> Example: "Workshop Hauptorga" is a private event for core organisers. "Hauptabend" is public. Publishing the workshop does not activate the project website -- only publishing "Hauptabend" does.

> Why block without shifts? An event without shifts has nothing for volunteers to sign up for. Publishing it would create confusion.

### Close Signups (Published Open → Published Closed)

Happens automatically when the **Anmeldefrist** passes, or manually:
1. Go to **Übersicht**.
2. Click **Anmeldung schließen**.

The event remains visible on the project website with an "Anmeldung abgelaufen" label.

### Maintenance Mode (Published → Draft)

Take an event back to Draft for changes. No notification is sent to volunteers during maintenance.

### Re-Publish (Draft → Published Open)

When re-publishing after maintenance:
- The Organizer can add an optional free-text note (e.g. "Schichtzeiten wurden angepasst").
- All volunteers with active signups receive an `event_updated` email with the note and a portal link.

### Archive (Published Closed → Archived)

1. Go to **Übersicht**.
2. Click **Archivieren**.

Archived events are read-only and removed from the project website. Published events must be closed first before archiving.

### Scheduled Publishing

Events can be scheduled to publish at a specific date/time.

**Who can do this**: Organizer only.

## Customize Email Templates

Email templates are configured **per event**. If no custom template is set, system defaults are used.

1. Go to event **Einstellungen > E-Mail-Vorlagen**.
2. Select the template type.
3. Edit Subject and Body.
4. Use placeholders:
   - `{{vorname}}` -- Volunteer's first name
   - `{{nachname}}` -- Volunteer's last name
   - `{{event_name}}` -- Event name
   - `{{job_name}}` -- Job name
   - `{{shift_date}}` -- Shift date
   - `{{shift_time}}` -- Shift time or custom display text
   - `{{event_location}}` -- Location
   - `{{portal_link}}` -- Magic link to Helfer-Portal
   - `{{kontakt_email}}` -- Project contact email
   - `{{gear_zusammenfassung}}` -- Gear assignments summary
   - `{{organizer_note}}` -- Organizer's free-text note (re-publish only)
5. Click **Speichern**.

> Note: The old `{{volunteer_name}}` placeholder has been replaced by `{{vorname}}` and `{{nachname}}` (separate first/last name fields).

**Who can do this**: Organizer only.

## Send Announcements

Announcements are manual, free-form emails sent to filtered volunteer groups. This is a **project-level** feature.

1. Go to Projekt > **Announcements** (or use the dashboard quick action).
2. **Empfänger filtern:** Select Event → Job → Shift (all optional, combinable).
3. **Inhalt:** Write Subject + Body as free text, or select a saved Announcement Template.
4. **Zeitpunkt:** Send immediately or schedule for a specific date/time.
5. Click **Senden** (or **Planen**).

The recipient count is shown before sending. Announcements are email-only -- they do not appear in the volunteer portal.

> Example: "Schicht Kuchenausgabe: Bitte keine Teller mitbringen" -- filtered to only that specific shift. Or "Sonnenschutz mitbringen" -- sent to all volunteers of an event.

**Announcement Templates** (Organisation > Einstellungen > Announcement Templates) let you save and reuse common messages across projects and years.

**Who can do this**: Organizer only.

## Clone an Event

Cloning creates a Draft copy of an event with all structure but no volunteer data.

1. Go to **Übersicht**.
2. Click **Event duplizieren**.

**Cloned:** Jobs, shifts, email templates, gear references, custom field references.
**Not cloned:** Volunteers, signups, announcements, attendance records.

Optional **date offset** shifts all dates forward (e.g. +365 days for yearly events).

**Who can do this**: Organizer only.

## Archive an Event

See [Event Lifecycle > Archive](#archive-published-closed--archived) above.

Archived events:
- No longer accept signups.
- Are removed from the project website.
- Remain visible in the event list (filter by "Archiviert").
- Are read-only.

**Who can do this**: Organizer only.