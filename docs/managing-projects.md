# Managing Projects

Projects are the top-level container in Voluntify. Every event, volunteer, gear item, custom field, and scanner configuration belongs to a project. This guide covers creating, configuring, and managing projects.

> Example: "Hochschulball 2026" is a project. It contains events like "Aufbautag", "Hauptabend", and "Abbautag". All volunteers, T-shirts, and registration fields are shared across these events.

## Create a Project

1. From the **Dashboard**, click **Neues Projekt**.
2. Fill in:
   - **Name** -- e.g. "Hochschulball 2026"
   - **Beschreibung** (optional) -- Internal description
3. Click **Erstellen**.

**Who can do this**: Org-Level Organizer only.

## Project Settings

Access via Projekt > **Einstellungen**.

### Allgemein

- **Name** -- Project name (also used as default for the project website title)
- **Beschreibung** -- Rich text / Markdown description
- **Titelbild** -- Header image for the project website
- **Kontakthinweis** -- Optional contact info shown on the project website

### E-Mail

- **Absendername** -- Display name in the "From" field of all outgoing volunteer-facing emails
- **Kontakt-E-Mail** -- Reply-to address, available as `{{kontakt_email}}` in templates
- **SMTP-Server** (optional) -- Project-specific SMTP config. If not set, falls back to the organisation's SMTP settings.

> Why SMTP at project level? Different projects within an organisation may send emails from different addresses (e.g. "ball@skhc.de" vs "sommerfest@skhc.de"). The project-level override makes this possible without changing the org default.

### Custom Fields

Project-wide registration fields. Asked once per volunteer per project. Pre-filled on return.

See [Custom Registration Fields](#custom-registration-fields) below.

### Gear

Project-wide gear items (Typ 1 and Typ 2). See [Gear](#gear) below.

### Scanner

All scanner configurations for Entry Staff and Volunteer Admin. See [Checking In Volunteers](checking-in-volunteers.md).

### Mitglieder

Only **Organizer** invitations happen here. Volunteer Admins and Entry Staff are assigned through scanner configurations.

## Project Website

Every project has a public website at `/p/{token}`. It's the main entry point for volunteers.

**Activation:** Automatically activated on the first publish of any event.

**Before first publish:**
- Logged-in Organizers see a **preview** with a "Vorschau" banner.
- Everyone else gets a **404**.

**Content:** Configured in Projekt > Einstellungen > Allgemein. Shows title, description, header image, and contact info.

**Event Listing:**

| Event Status | Shown? | Display |
|---|---|---|
| Published Open | Yes | With signup button + deadline |
| Published Closed | Yes | "Anmeldung abgelaufen" label |
| Draft | No | Hidden |
| Archived | No | Removed |

Event cards show: name, date, location, deadline, status badge.

## Custom Registration Fields

Two levels:

| Level | When Asked | Pre-filled on Return? |
|---|---|---|
| **Projektfelder** | Once per volunteer per project | Yes |
| **Eventfelder** | At each event signup | No |

### Field Types

- **Text** -- Free-text answer (optional: multiline)
- **Select** -- Dropdown with predefined options
- **Checkbox** -- Yes/No toggle

### Managing Project Fields

1. Go to Projekt > **Custom Fields**.
2. Click **Feld hinzufügen**.
3. Configure: Label, Type, Required toggle.
4. Save.

Removing a field preserves existing responses. Adding a required field to a project with existing volunteers: those volunteers see the field as "Keine Angabe" until they update via the portal.

Event-level fields are managed in Event > Einstellungen > Anmeldung.

## Gear

Gear is defined at **project level** -- a volunteer receives e.g. one T-shirt for the entire project, not one per event.

### Typ 1 -- Größenauswahl

- Volunteer selects from predefined options during signup (e.g. T-shirt size: S, M, L, XL)
- One entitlement per volunteer
- **States are configurable** by the Organizer -- not hardcoded
- All state changes happen via scanner only

> Example: An Organizer defines a "T-Shirt" item with states "Ausstehend", "Abgeholt", "Nicht vorrätig". Another Organizer uses "Bereit", "Ausgegeben", "Rückgabe ausstehend" for walkie-talkies.

> Why configurable states? Different organisations have different gear workflows. A food festival tracking meal vouchers has different states than a music festival tracking radios.

### Typ 2 -- Mengenausgabe

- Quantity set by organizer per gear item (e.g. 3 drink tokens)
- Multiple pickups tracked individually
- No volunteer selection needed during signup
- Scanner shows: "2 / 3 abgeholt"

### Managing Gear

1. Go to Projekt > **Gear**.
2. Click **Item hinzufügen**.
3. Configure:
   - **Name** -- e.g. "Volunteer T-Shirt"
   - **Typ** -- Größenauswahl or Mengenausgabe
   - **Auswahl-Optionen** (Typ 1) -- e.g. "XS, S, M, L, XL"
   - **Zustands-Optionen** (Typ 1) -- e.g. "Ausstehend, Abgeholt, Nicht vorrätig"
   - **Menge pro Volunteer** (Typ 2) -- e.g. 3
   - **Job-Einschränkung** (optional) -- Only show to volunteers in specific jobs
4. Save.

### Gear Summary

Read-only project overview for Organizers:
- Per item: status counts (Typ 1) or pickups vs. total (Typ 2)
- Missing gear report: filterable by item, event, status
- "Nicht vorrätig" summary
- CSV export
- Gear Tracker Mode in the Volunteer Admin Scanner

## Clone a Project

1. From the Dashboard, click the project tile's quick action menu.
2. Click **Projekt duplizieren**.
3. Optional: set a **date offset** (shifts all dates forward).

**Cloned:** Events, jobs, shifts, gear definitions, custom fields, email templates, scanner configurations (assignees cleared).
**Not cloned:** Volunteers, signups, announcements, attendance records.

## Delete a Project

1. Go to Projekt > Einstellungen.
2. Click **Projekt löschen**.
3. Enter your password to confirm.

**Cascade:** All events, volunteers, signups, gear, custom fields, and scanner configs are deleted.

Published events must be archived first. 30-day soft-delete grace period.

**Who can do this**: Organizer only.