# Managing Volunteers

This guide covers viewing, creating, and managing volunteers at project level, including manual creation, organizer actions, and the dashboard.

**Who can access**: Organizer only. Volunteer Admins can view volunteers through the scanner but not through the admin interface.

## View Volunteers for a Project

Volunteers belong to the **project** -- not to individual events. The project-level volunteer list shows all volunteers across all events.

1. Go to the project's **Volunteers** page.
2. You'll see a table showing:
   - Vorname, Nachname
   - E-Mail
   - Schichten (count per event)
   - Signup-Datum
   - Status-Badge (aktive Schichten / "Keine Schicht")

### Search and Filter

- **Search**: Type in the search bar to filter by name or email.
- **Filter by event**: Show only volunteers signed up for a specific event.
- **Filter by job**: Narrow to a specific job within an event.
- **Filter by shift**: Narrow further to a specific shift.

Click a volunteer row to view their full details.

## View Volunteer Details

The volunteer detail page shows everything about a volunteer within this project:

- **Persönliche Daten** -- Vorname, Nachname, E-Mail, Telefon
- **Schichten** -- All shift assignments across events, with job names, times, and status
- **Anwesenheit** -- Per-shift: On Time / Late / No Show
- **Gear** -- Typ-1 status (from configurable state list) and Typ-2 pickup count
- **Custom Fields** -- Project-level and event-level field responses. Fields without answers show "Keine Angabe". Archived fields show with an "(archiviert)" label.

## Create a Volunteer Manually

Organizers can create volunteers directly -- without the public signup flow. This is useful for volunteers recruited in person, by phone, or through other channels.

1. Go to the project's **Volunteers** page.
2. Click **Volunteer hinzufügen**.
3. Fill in the form:
   - **E-Mail** (required) -- the only mandatory field
   - **Vorname** (optional)
   - **Nachname** (optional)
   - **Telefon** (optional)
   - **Schichten** (optional) -- select shifts across events
   - **Custom Fields** (optional) -- enter known answers
   - **Gear-Auswahl** (optional) -- e.g. T-shirt size
4. Click **Speichern**.

After saving:
- The volunteer receives a `volunteer_added_by_organizer` email with a magic link to the Helfer-Portal.
- The email says: "Du wurdest als Helfer eingetragen. Vervollständige deine Angaben über deinen Portal-Link."

> Example: The Organizer meets someone at a club event who wants to volunteer. They create a record with just the email. The volunteer receives a portal link and fills in their name, selects shifts, and chooses their T-shirt size at their convenience.

### Handling Incomplete Data

When the Organizer doesn't fill in everything:

| Missing Data | Where It Shows | What Happens |
|---|---|---|
| Typ-1 Gear selection | Scanner | Shows **"Auswahl ausstehend"** -- gear pickup blocked until volunteer selects via portal |
| Custom Fields | Organizer UI | Shows **"Keine Angabe"** |
| Vorname / Nachname | Portal | Banner: "Bitte vervollständige deine Registrierung" |

> Why "Auswahl ausstehend" and not "Größe ausstehend"? Typ-1 gear isn't always about sizes -- it could be colors, variants, or preferences. The label must be generic.

## Organizer Actions on Existing Volunteers

### Assign or Change Shifts

1. Open the volunteer's detail page.
2. Click **Schichten bearbeiten**.
3. Add or remove shifts across events in the project.
4. Save.

The volunteer receives the same confirmation email as if they had changed it themselves.

### Cancel a Volunteer's Signup

**Single shift:**
1. Open the volunteer's detail page.
2. Click the remove icon next to the shift.
3. Confirm.

**Full cancellation (all shifts):**
1. Open the volunteer's detail page.
2. Click **Anmeldung stornieren**.
3. Confirm.

The volunteer record remains in the project (badge: "Keine Schicht"). The volunteer receives a cancellation email.

> Why keep the record? The volunteer might want to sign up again later. Deleting would lose their custom field responses and gear status.

### Edit Personal Data

1. Open the volunteer's detail page.
2. Click **Stammdaten bearbeiten**.
3. Update Vorname, Nachname, E-Mail, or Telefon.
4. Save.

The volunteer receives the same update email as if they had changed it themselves -- no separate "Organizer hat geändert" template.

> Why no separate template? Keeping one set of change-notification templates (regardless of who triggered the change) simplifies the email system and avoids confusing volunteers with different email formats for the same information.

## Promote a Volunteer to Staff

From the volunteer detail page, you can promote a volunteer to a staff role.

1. Click **Zum Staff befördern**.
2. Select a role:
   - **Volunteer Admin** -- Select which scanner to assign them to. Scanner link will be sent automatically before the time window.
   - **Organizer** -- Account is created (if needed), invitation email sent, access to the project/organisation.
3. Confirm.

> Note: **Entry Staff** is not available via "Promote to Staff" -- Entry Staff are assigned directly through the Entry Staff Scanner configuration.

**Who can do this**: Organizer only.

## Export CSV

To export the volunteer list:

1. Go to the project's **Volunteers** page.
2. Click **Export**.
3. A CSV downloads with: Vorname, Nachname, E-Mail, Telefon, shifts per event, gear assignments with selections, and custom field responses.

Archived fields include an "(archiviert)" suffix in the column header. Cancelled signups are excluded.

## Dashboard Overview

The **Dashboard** provides a project-focused overview for Organizers.

**Upper area:**
- Next upcoming event
- **Neues Projekt** button
- Global volunteer search (across all projects)

**Project tiles** -- Each project shows:

| Info | Details |
|---|---|
| Status-Badge | Draft / Open / Closed / Archived (per event) |
| Volunteers | Count of active signups |
| Schichten | Shifts with open capacity |
| No-Show-Rate | % not appeared |
| Anwesenheit | On Time / Late / No Show breakdown |
| Gear ausstehend | Configurable favourite items (Organizer selects per project) |

> Why configurable gear favourites? Not all gear items are equally important to track at a glance. The Organizer picks which ones appear on the dashboard tile -- e.g. T-shirts but not drink tokens.

**Quick Actions per tile:**
- Neues Event anlegen
- Anmelde-Link kopieren
- Event duplizieren
- Scanner öffnen
- Ankündigung senden

**Filters:** By time period, by project.