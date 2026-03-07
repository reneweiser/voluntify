# Creating Events

This guide covers creating and configuring events, including jobs, shifts, email templates, and event lifecycle actions.

## Create a New Event

1. Click **Events** in the sidebar.
2. Click **Create Event**.
3. Fill in the required fields:
   - **Name** -- The event name (e.g., "Spring Community Cleanup").
   - **Starts At** -- When the event begins.
   - **Ends At** -- When the event ends (must be after the start date).
4. Optionally fill in:
   - **Location** -- Where the event takes place.
   - **Description** -- Details about the event shown on the public signup page.
5. Click **Create**.

The event is created in **Draft** status. You'll be taken to the event detail page where you can add jobs and shifts.

**Who can do this**: Organizer only.

## Edit Event Details

1. Go to the event's **Overview** tab.
2. Click **Edit**.
3. Update the name, dates, location, or description.
4. Optionally upload a **title image** (JPG, PNG, or WebP, max 2 MB). This appears as a hero image on the public signup page.
5. Click **Save**.

To remove a title image, click **Delete Image** while editing.

**Who can do this**: Organizer only.

## Define Volunteer Jobs

Jobs describe the roles volunteers will fill at your event.

1. Go to the event's **Jobs & Shifts** tab.
2. Click **Add Job**.
3. Enter:
   - **Name** -- The job title (e.g., "Registration Desk").
   - **Description** -- What the job involves. Shown on the public signup page.
   - **Instructions** -- Detailed info for volunteers (e.g., where to report, what to bring). Included in pre-shift reminder emails.
4. Save the job.

To edit or delete a job, use the controls on the job section. Deleting a job with existing signups will ask for confirmation.

**Who can do this**: Organizer only. Volunteer Admins can view jobs but not edit them.

## Create Shifts

Shifts are time slots within a job that volunteers sign up for.

1. Within a job on the **Jobs & Shifts** tab, click **Add Shift**.
2. Set:
   - **Start Time** -- When the shift begins.
   - **End Time** -- When the shift ends.
   - **Capacity** -- Maximum number of volunteers for this shift.
3. Save the shift.

The capacity display shows "X / Y signed up" so you can see how full each shift is. When a shift is full, it shows a "Full" badge on the public signup page and volunteers can't sign up for it.

To edit or delete a shift, use the inline controls. Deleting a shift with signups will ask for confirmation.

**Who can do this**: Organizer only.

## Customize Email Templates

Voluntify sends automated emails to volunteers. You can customize these per event.

1. Go to the event's **Emails** tab.
2. Select the template type:
   - **Signup Confirmation** -- Sent when a volunteer signs up.
   - **Pre-Shift Reminder (24h)** -- Sent 24 hours before a shift.
   - **Pre-Shift Reminder (4h)** -- Sent 4 hours before a shift.
3. Edit the **Subject** and **Body** fields.
4. Use placeholders to insert dynamic content:
   - `{{volunteer_name}}` -- The volunteer's name
   - `{{event_name}}` -- The event name
   - `{{job_name}}` -- The volunteer job name
   - `{{shift_date}}` -- The shift date
   - `{{shift_time}}` -- The shift start time
   - `{{event_location}}` -- The event location
5. Click **Save**.

Use **Preview** to see how the email will look with sample data. To revert to the default template, click **Reset to Default**.

A badge shows whether you're using a **Customized** or **Default** template.

**Who can do this**: Organizer only.

## Publish an Event

Publishing makes your event visible to volunteers and enables signups.

1. Go to the event's **Overview** tab.
2. Click **Publish**.

Before publishing, make sure:
- The event has at least one job with at least one shift.
- Event dates and details are correct.

Once published:
- A **public event URL** becomes available. Copy it with the **Copy Link** button.
- Volunteers can access the public signup page and register for shifts.
- The event status badge changes to **Published**.

**Who can do this**: Organizer only.

## Clone an Event

Cloning creates a copy of an event with all its jobs and shifts, but no volunteer signups.

1. Go to the event's **Overview** tab.
2. Click **Clone Event**.
3. You'll be redirected to the new cloned event in Draft status.
4. Update the name, dates, and any other details as needed.

This is useful for recurring events where the job structure stays the same.

**Who can do this**: Organizer only.

## Archive an Event

Archiving marks an event as completed. Archived events are read-only.

1. Go to the event's **Overview** tab.
2. Click **Archive**.

Archived events:
- No longer accept new signups.
- Are still visible in the events list (filter by "Archived").
- Cannot be edited.

**Who can do this**: Organizer only.
