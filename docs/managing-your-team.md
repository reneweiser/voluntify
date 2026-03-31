# Managing Your Team

This guide covers inviting Organizers, assigning scanner-based staff, and managing members at organisation and project level.

## Who Has Permanent Accounts?

Only **Organizers** have permanent accounts with email + password login. Volunteer Admins and Entry Staff are assigned through scanner configurations and access the system via temporary links.

| Role | Account | Assigned Via |
|---|---|---|
| Organizer | Permanent | Invitation per email |
| Volunteer Admin | None | Scanner config (temporary link) |
| Entry Staff | None | Scanner config (temporary link) |

> Why this distinction? Organizers manage the system long-term and need persistent access. Volunteer Admins and Entry Staff typically work a single event -- a temporary link eliminates onboarding friction and automatically expires.

## Invite an Organizer

### At Organisation Level

1. Go to Organisation > **Mitglieder**.
2. Enter name and email.
3. Click **Einladen**.

Org-Level Organizers have automatic access to all projects and events.

### At Project Level

1. Go to Projekt > **Mitglieder**.
2. Enter name and email.
3. Click **Einladen**.

Project-Level Organizers have access to all events within that project only.

### What Happens After Invitation

- **New user:** Account created with temporary password. Invitation email sent.
- **Existing user:** Added to the organisation/project directly. Notification email sent.

**Who can do this**: Organizer only (Org-Level for org invitations, Project-Level or higher for project invitations).

## Assign Volunteer Admins

Volunteer Admins are assigned through the **Volunteer Admin Scanner** configuration:

1. Go to Projekt > **Scanner**.
2. Create or edit a Volunteer Admin Scanner.
3. Add email addresses under **Zugewiesene Personen**.
4. Save.

Each assigned person receives a scanner link 30 minutes before the time window. No account creation needed.

> Example: You want three trusted volunteers to manage check-in during "Hauptabend" from 18:00 to 02:00. Add their emails to a Volunteer Admin Scanner with that time window. They receive links at 17:30.

You can also promote an existing volunteer to Volunteer Admin from their detail page -- this assigns them to a specific scanner.

## Assign Entry Staff

Entry Staff are assigned through the **Entry Staff Scanner** configuration:

1. Go to Projekt > **Scanner**.
2. Create or edit an Entry Staff Scanner.
3. Add email addresses under **Zugewiesene Personen**.
4. Save.

> Note: Entry Staff are **not** assigned via "Promote to Staff" -- only through scanner configuration.

## Change a Member's Role

For Organizers (the only role with persistent accounts):

1. Go to Organisation > **Mitglieder** or Projekt > **Mitglieder**.
2. Find the member.
3. Change their role using the dropdown.

For Volunteer Admins and Entry Staff: edit the scanner configuration to add/remove assignees.

## Remove a Member

### Remove an Organizer

1. Go to Organisation/Projekt > **Mitglieder**.
2. Click the remove icon next to the member.
3. Confirm with their email address.

### Remove Scanner Staff

Edit the scanner configuration and remove their email from the assignee list.

## Leave an Organisation

Any Organizer can leave an organisation they belong to:

1. Go to Organisation > **Mitglieder**.
2. Click **Organisation verlassen**.
3. Confirm.

Restrictions:
- You cannot leave your **personal organisation**.
- You cannot leave if you are the **sole Organizer** -- transfer the role first.

Leaving removes access to all projects and events. You're switched to another organisation automatically.

## Onboarding Checklist: Entry Staff

Share these steps with new Entry Staff:

1. **Check your email** -- Look for the scanner link (sent 30 minutes before the time window).
2. **Open the link** -- Enter the one-time authentication code.
3. **Install as PWA** (recommended) -- Mobile browser > "Install app" / "Add to Home Screen" for fullscreen experience.
4. **Test the scanner** -- Point the camera at any QR code to verify it works.
5. **Learn the colors** -- Green = access granted, Yellow = already scanned, Red = no access. Tap "Nächsten scannen" after each result.

## Onboarding Checklist: Volunteer Admins

Share these steps with new Volunteer Admins:

1. **Check your email** -- Look for the scanner link (sent 30 minutes before the time window).
2. **Open the link** -- Enter the one-time authentication code.
3. **Familiarize with the modes** -- Check-in, Gear Pickup, or both (configured by the Organizer).
4. **Check the Schichtliste tab** -- Browse volunteers per shift, use "Jump to current time".
5. **Practice gear pickup** -- Tap the current state, select the new state from the list.