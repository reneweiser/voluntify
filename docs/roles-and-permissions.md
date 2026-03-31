# Roles and Permissions

Voluntify has three staff roles and one external role. Only Organizers have permanent accounts -- Volunteer Admins and Entry Staff operate via temporary scanner links.

## Role Overview

| Role | Account | Access Method | Assigned Via |
|---|---|---|---|
| **Organizer** | Permanent | Email + password | Invitation per E-Mail |
| **Volunteer Admin** | None | Temporary scanner link | Volunteer Admin Scanner config |
| **Entry Staff** | None | Temporary scanner link | Entry Staff Scanner config |
| **Volunteer** | None | Magic link | Public signup flow |

> Why no accounts for Volunteer Admin and Entry Staff? These roles are typically short-term helpers assigned to a specific time window. A temporary scanner link eliminates onboarding friction (no password setup, no login) and automatically expires after the configured time window. Security is maintained through one-time authentication codes and encrypted local data.

## Organizer

Organizers are the only role with permanent accounts. They exist at two levels:

**Org-Level Organizer** -- Automatic access to all projects and events in the organisation. Typically: club presidents, board members, or permanent staff.

**Project-Level Organizer** -- Access to all events within a specific project. Typically: event coordinators or project leads hired for a specific occasion.

> Example: The SKHC board president is an Org-Level Organizer with access to all projects. A hired coordinator for "Hochschulball 2026" is a Project-Level Organizer who only sees that project.

Resolution order when both levels apply: Scanner config > Project membership > Organisation membership.

## Volunteer Admin

Volunteer Admins manage shift attendance and gear pickup on-site, using the **Volunteer Admin Scanner**.

- No permanent account -- access via temporary scanner link only
- Link sent 30 minutes before the configured scanner time window
- One-time authentication code on first open
- Can: check-in volunteers, mark attendance, distribute gear, view shift lists, manual volunteer search
- Cannot: access settings, statistics, or other events

> Example: A reliable volunteer is promoted to Volunteer Admin for the "Hauptabend" event, 18:00--02:00. They receive a scanner link at 17:30 and can manage check-in and T-shirt pickup during that window.

## Entry Staff

Entry Staff handle entrance control using the **Entry Staff Scanner**.

- No permanent account -- access via temporary scanner link only
- Link sent 30 minutes before the configured time window
- Can: scan QR codes, view green/yellow/red result screens, access guest list
- Cannot: manual volunteer search, attendance marking, volunteer details, gear

> Example: Three friends of the organizer volunteer as Entry Staff at the main entrance. Each receives a scanner link valid from 19:00 to 23:00. They scan QR codes and see full-screen color results -- nothing else.

## Volunteer (External)

Volunteers are external participants -- they interact via magic links and the public signup flow.

Volunteers can:
- Sign up for shifts via the project website (`/p/{token}`) or direct event link (`/event/{token}`)
- Verify their email and complete the multi-step signup flow
- Access the **Helfer-Portal** via magic link to view shifts, QR code, gear, and personal data
- Cancel their own shift signups (if enabled by the Organizer and within the cutoff period)
- Receive system emails: confirmation, reminders, announcements

Volunteers **cannot**:
- Access the admin interface (Dashboard, Events, Scanner, Settings)
- View other volunteers' information
- Pick up gear without at least one shift (past or future) -- the eligibility check protects this automatically

A volunteer without any active shifts is a **valid state** (not an error) -- they may have verified but not yet chosen shifts, or all their shifts were cancelled.

> Example: A volunteer signs up, selects a shift, then cancels it two days later. They still exist in the project but have no shifts. The dashboard shows "Keine Schicht". They cannot pick up gear or enter via scanner until they sign up for a new shift.

## Permission Matrix

### Organisation Level

| Action | Org-Organizer | Proj-Organizer | Vol. Admin | Entry Staff |
|---|:---:|:---:|:---:|:---:|
| Organisation verwalten | Yes | -- | -- | -- |
| Projekt erstellen | Yes | -- | -- | -- |
| Mitglieder einladen (Org) | Yes | -- | -- | -- |

### Project Level

| Action | Org-Organizer | Proj-Organizer | Vol. Admin | Entry Staff |
|---|:---:|:---:|:---:|:---:|
| Projekt bearbeiten | Yes | Yes | -- | -- |
| Mitglieder einladen (Projekt) | Yes | Yes | -- | -- |
| Event erstellen / bearbeiten | Yes | Yes | -- | -- |
| Event veröffentlichen | Yes | Yes | -- | -- |
| Gear definieren | Yes | Yes | -- | -- |
| Custom Fields verwalten | Yes | Yes | -- | -- |
| Scanner konfigurieren | Yes | Yes | -- | -- |
| Volunteer manuell anlegen | Yes | Yes | -- | -- |
| Announcements senden | Yes | Yes | -- | -- |
| Gästelisten verwalten | Yes | Yes | -- | -- |
| Dashboard | Yes | Yes | -- | -- |

### Scanner Level

| Action | Org-Organizer | Proj-Organizer | Vol. Admin | Entry Staff |
|---|:---:|:---:|:---:|:---:|
| QR-Scanner (Einlass) | Yes | Yes | -- | Yes |
| Gastliste einsehen | Yes | Yes | -- | Yes |
| Volunteer-Liste einsehen | Yes | Yes | Yes | -- |
| Check-in durchführen | Yes | Yes | Yes | -- |
| Anwesenheit markieren | Yes | Yes | Yes | -- |
| Gear ausgeben | Yes | Yes | Yes | -- |
| Manuelle Suche | Yes | Yes | Yes | -- |

> Note: Organizers access scanner functions through the admin interface. Volunteer Admins and Entry Staff access them exclusively through their temporary scanner links.

### Account & Settings

| Action | Org-Organizer | Proj-Organizer | Vol. Admin | Entry Staff |
|---|:---:|:---:|:---:|:---:|
| Profil bearbeiten | Yes | Yes | -- | -- |
| Passwort ändern | Yes | Yes | -- | -- |
| 2FA einrichten | Yes | Yes | -- | -- |
| SMTP konfigurieren | Yes | -- | -- | -- |
| Activity Log einsehen | Yes | -- | -- | -- |
| Account löschen | Yes | Yes | -- | -- |