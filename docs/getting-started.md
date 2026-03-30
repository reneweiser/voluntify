# Getting Started

This guide walks you through setting up your first project and event in Voluntify.

## Prerequisites

- **Invite only**: Voluntify is invite-only. You need an invitation from an existing Organizer or a system administrator to get started.
- **Modern browser**: Chrome, Firefox, Safari, or Edge (latest versions).

## Your First 15 Minutes

### 1. Accept Your Invitation

You'll receive an email with a temporary password. Log in at your organisation's Voluntify URL, then set a new password when prompted.

### 2. Create a Project

Projects are the top-level container for everything -- events, volunteers, gear, custom fields, and scanner configurations all live here.

1. From the **Dashboard**, click **Neues Projekt**.
2. Fill in the project name (e.g. "Hochschulball 2026") and an optional description.
3. Click **Create**.

> Example: "Hochschulball 2026" is a project containing events like "Aufbautag", "Hauptabend", and "Abbautag". All volunteers and shared resources belong to this project.

### 3. Create an Event

1. Inside your project, click **Neues Event**.
2. Fill in the event name, date, and location.
3. Click **Create**. Your event is created in **Draft** status.

### 4. Add Jobs and Shifts

1. From the event page, go to **Jobs & Schichten**.
2. Click **Job hinzufügen** and enter a name (e.g. "Einlass"), description, and instructions.
3. Within the job, click **Schicht hinzufügen** and set the date, optional start/end times, and capacity.
4. Repeat for each job and shift.

> Note: Start and end times are optional. For flexible jobs, use custom display text like "nach Bedarf" instead of fixed hours.

### 5. Publish and Share

1. Go back to the event **Übersicht**.
2. Click **Veröffentlichen**. This activates the project website and makes the event visible.
3. Copy the **project website URL** (`/p/{token}`) and share it with potential volunteers.

Publishing is blocked until at least one shift is created -- this prevents publishing an empty event by accident.

> Why share the project URL, not the event URL? The project website lists all published events. Volunteers can discover and sign up for multiple events in one place.

That's it -- volunteers can now sign up for shifts via the project website, verify their email, and receive a QR code for the entire project.

## Key Concepts

### Project > Event > Job > Shift

```text
Organisation
└── Projekt (z.B. "Hochschulball 2026")
    ├── Projektwebsite (/p/{token})
    ├── Shared resources (Gear, Custom Fields, Volunteers, Scanner)
    └── Events
        ├── "Aufbautag"
        │   └── Jobs → Schichten
        ├── "Hauptabend"
        │   └── Jobs → Schichten
        └── "Abbautag"
            └── Jobs → Schichten
```

- A **Project** groups related events and holds shared resources.
- An **Event** is a specific occasion with a date, location, and volunteer needs.
- A **Job** is a role volunteers fill (e.g. "Bar", "Einlass").
- A **Shift** is a time slot within a job with a capacity.

Volunteers sign up for specific shifts and belong to the project -- not to individual events.

### Event Lifecycle (4 Stages)

```text
Draft --> Published Open <--> Published Closed --> Archived
```

- **Draft** -- Being set up. Not visible on the project website.
- **Published Open** -- Live and accepting signups.
- **Published Closed** -- Visible but no new signups (deadline passed or manually closed).
- **Archived** -- Completed and read-only.

> Why "Published Open" and "Published Closed"? An event may still be useful for informational purposes (schedule, location) even after signups close. Splitting the two states lets the project website show closed events with an "Anmeldung abgelaufen" label.

### Passwordless Volunteers

Volunteers don't need accounts. They sign up with their email, verify it, and receive a magic link. The magic link gives access to the **Helfer-Portal** where they see their QR code, shifts, gear status, and personal data.

One QR code per project -- valid for all events.

### Three Roles, One Account Type

| Role | Account | How They Access |
|---|---|---|
| **Organizer** | Permanent account | Email + password login |
| **Volunteer Admin** | No account | Temporary scanner link |
| **Entry Staff** | No account | Temporary scanner link |
| **Volunteer** | No account | Magic link |

> Why no accounts for Volunteer Admin and Entry Staff? These roles are typically short-term helpers working a single event. A temporary scanner link is faster to set up and automatically expires.

### Two Types of Check-In

- **Event Arrival** (Entry Staff Scanner): Confirms a volunteer is physically at the venue. Full-screen green/yellow/red result.
- **Shift Attendance** (Volunteer Admin Scanner): Records whether a volunteer reported to their specific shift on time.

## Navigation Overview

The sidebar contains the main navigation:

| Sidebar Item | What It Does |
|---|---|
| **Organisation switcher** | Switch between organisations (top of sidebar) |
| **Dashboard** | Project tiles, quick actions, volunteer search |
| **Projekte** | List and manage projects |
| **Activity Log** | Organisation-wide audit trail (Organizer only) |

Within a project:

| Section | What It Shows |
|---|---|
| **Events** | Create and manage events within this project |
| **Volunteers** | Project-wide volunteer list, search, manual creation |
| **Gear** | Define and manage gear items for the project |
| **Custom Fields** | Project-level registration fields |
| **Scanner** | Configure Entry Staff and Volunteer Admin scanners |
| **Mitglieder** | Organizer invitations for this project |
| **Einstellungen** | Project settings (name, email config, website) |

Within an event:

| Section | What It Shows |
|---|---|
| **Übersicht** | Event details, lifecycle actions, metrics |
| **Jobs & Schichten** | Manage jobs and shifts |
| **Volunteers** | Event-specific volunteer list and attendance |
| **E-Mail-Vorlagen** | Customize email templates for this event |
| **Einstellungen** | Event settings (name, dates, location, deadline, grace period) |

Settings are accessible from the user menu. See [Settings and Account](settings-and-account.md) for details.