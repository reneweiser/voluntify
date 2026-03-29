# Managing Volunteers

This guide covers viewing volunteer data, exporting lists, and using the dashboard.

**Who can access**: Organizer and Volunteer Admin.

## View Volunteers for an Event

1. Go to the event's **Volunteers** page.
2. You'll see a table of all signed-up volunteers showing:
   - Name
   - Email
   - Job
   - Shift
   - Signup date
   - Arrival status (scanned / not scanned)
   - Attendance status (On Time / Late / No Show / unmarked)

### Search and Filter

- **Search**: Type in the search bar to filter by volunteer name or email.
- **Filter by job**: Use the job dropdown to show only volunteers for a specific job.
- **Filter by shift**: Use the shift dropdown to narrow further to a specific shift.

Click a volunteer row to view their full details.

## View Volunteer Details

The volunteer detail page shows everything about a specific volunteer for this event:

- **Name and email**
- **Shift assignments** -- All shifts they're signed up for, with job names and times.
- **Arrival status** -- Whether they've been scanned in at the event entrance and when.
- **Attendance records** -- Their per-shift attendance status (On Time / Late / No Show).
- **Custom field responses** -- Answers to any custom registration fields the volunteer filled in during signup. Archived (removed) fields are shown with an "(archived)" label.

## Promote a Volunteer to Staff

If a volunteer has proven reliable and you'd like to give them a staff role in your organization, you can promote them directly from their detail page.

1. Go to the event's **Volunteers** page and click the volunteer's row.
2. Click **Promote to Staff**.
3. Select a role:
   - **Volunteer Admin** -- Can view volunteers and mark shift attendance.
   - **Entrance Staff** -- Can use the QR scanner and manual lookup.
   - **Organizer** -- Full administrative access.
4. Click **Promote**.

What happens next depends on whether the volunteer's email already belongs to a Voluntify user:

- **New user**: A user account is created with a temporary password. The volunteer receives a notification email with their login credentials and must set a new password on first login.
- **Existing user**: The user is added to your organization with the selected role directly. No notification email is sent -- the organization will appear in their organization switcher on next login.

Once promoted, the volunteer's detail page shows a **Staff Member** badge instead of the promote button.

For onboarding tips for new staff members, see [Managing Your Members > Onboarding Checklists](managing-your-team.md#onboarding-checklist-entrance-staff).

**Who can do this**: Organizer only.

## Export CSV

To export the volunteer list as a CSV file:

1. Go to the event's **Volunteers** page.
2. Click the **Export** button.
3. A CSV file downloads with the volunteer data.

The export includes volunteer names, emails, job assignments, shift times, status information, a gear column listing assigned gear items with sizes (e.g., "T-Shirt (M); Badge"), and columns for each custom registration field. Checkbox fields export as "Yes" or "No". Archived (removed) fields include an "(archived)" suffix in the column header.

Note: Cancelled signups are excluded from volunteer counts, the volunteer list, and attendance views. Only active signups are shown.

## Dashboard Overview

The **Dashboard** (accessible from the sidebar) provides an at-a-glance overview:

- **Summary cards** -- Upcoming events count, total volunteers across events, shifts needing attention.
- **Upcoming events list** -- Events sorted by date (nearest first), showing key metrics per event.
- **Quick actions** -- Organizers see a "Create Event" button for fast access.

The dashboard adapts to your role:
- **Organizers** see all organization data and the "Create Event" button.
- **Volunteer Admins** see events they're assigned to.
- **Entrance Staff** see events they're assigned to.
