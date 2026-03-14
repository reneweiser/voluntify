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

1. Go to **Overview** in the event sidebar.
2. Click **Edit**.
3. Update the name, dates, location, or description.
4. Optionally upload a **title image** (JPG, PNG, or WebP, max 2 MB). This appears as a hero image on the public signup page.
5. Optionally assign the event to an **Event Group** using the dropdown. See [Organizing Event Groups](managing-event-groups.md) for details.
6. Optionally set a **Cancellation Cutoff (hours)** -- This allows volunteers to cancel their own signups up to the specified number of hours before a shift starts. Leave empty to disable volunteer self-cancellation.
7. Optionally set an **Attendance Grace Period (minutes)** -- Defines how many minutes after a shift starts a scan is still considered "On Time." Scans after the grace window are marked "Late." Leave empty for no grace period (any scan after shift start is Late). See [Attendance Grace Period](tracking-attendance.md#attendance-grace-period) for details.
8. Click **Save**.

To remove a title image, click **Delete Image** while editing.

**Who can do this**: Organizer only.

## Define Volunteer Jobs

Jobs describe the roles volunteers will fill at your event.

1. Go to **Jobs & Shifts** in the event sidebar.
2. Click **Add Job**.
3. Enter:
   - **Name** -- The job title (e.g., "Registration Desk").
   - **Description** -- What the job involves. Shown on the public signup page.
   - **Instructions** -- Detailed info for volunteers (e.g., where to report, what to bring). Instructions are published as a standalone cheat sheet page linked from the public signup page and included in pre-shift reminder emails.
4. Save the job.

To edit or delete a job, use the controls on the job section. Deleting a job with existing signups will ask for confirmation.

**Who can do this**: Organizer only. Volunteer Admins can view jobs but not edit them.

## Create Shifts

Shifts are time slots within a job that volunteers sign up for.

1. Within a job on the **Jobs & Shifts** page, click **Add Shift**.
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

1. Go to **Emails** in the event sidebar.
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
   - `{{cheat_sheet_url}}` -- Link to the job's cheat sheet page (pre-shift reminders only)
5. Click **Save**.

Use **Preview** to see how the email will look with sample data. To revert to the default template, click **Reset to Default**.

A badge shows whether you're using a **Customized** or **Default** template.

**Who can do this**: Organizer only.

## Send Announcements

Announcements let you send messages to all volunteers signed up for an event. Use them to communicate changes like updated parking instructions, schedule adjustments, or last-minute reminders.

1. Go to **Announcements** in the event sidebar.
2. Write the announcement **Subject** and **Body**.
3. The recipient count shows how many volunteers will receive the message.
4. Click **Send**.

Sent announcements are also visible to volunteers in their [Volunteer Portal](recruiting-volunteers.md#volunteer-portal).

The **Announcements** page shows a history of all sent announcements with their subject, send date, and recipient count.

**Who can do this**: Organizer only.

## Set Up Event Gear

If your event involves handing out gear to volunteers (e.g., t-shirts, badges, lanyards), you can define gear items and optionally require size selection during signup.

1. Go to **Gear** in the event sidebar.
2. Click **Add Item**.
3. Enter a **Name** for the gear item (e.g., "Volunteer T-Shirt").
4. If the item comes in sizes, toggle **Requires Size** and enter the available sizes as comma-separated values (e.g., "S, M, L, XL").
5. Repeat for each gear item.

When gear items are configured, volunteers see a gear selection form during signup. Sized items show a size dropdown; non-sized items are assigned automatically.

To remove a gear item, click the delete icon next to it. This deletes all volunteer gear assignments for that item.

**Who can do this**: Organizer only. Gear pickup tracking is available to Organizer and Volunteer Admin (see the **Gear Pickup** page).

## Set Up Custom Registration Fields

If you need to collect additional information from volunteers during signup -- like dietary restrictions, t-shirt sizes, or emergency contacts -- you can add custom registration fields to your event.

1. Go to **Custom Fields** in the event sidebar.
2. Click **Add Field**.
3. Configure the field:
   - **Label** -- The question or field name (e.g., "Do you have any dietary restrictions?").
   - **Type** -- Choose from:
     - **Text** -- A single-line text input. Toggle **Multiline** for longer answers.
     - **Select** -- A dropdown with predefined choices. Enter choices as comma-separated values (e.g., "Vegetarian, Vegan, Gluten-Free, None").
     - **Checkbox** -- A simple yes/no toggle.
   - **Required** -- Toggle on if volunteers must fill in this field to complete their signup.
4. Repeat for each field you need.

**Quick templates**: Click a template button to instantly add a commonly used field -- **Emergency Contact**, **Dietary Restrictions**, **T-Shirt Size**, **First Aid Certificate**, **Previous Experience**, or **Photo Release**. You can customize the field after adding it.

**Removing fields**: Click the delete icon next to a field to remove it. Existing responses from volunteers who already signed up are preserved -- the field simply no longer appears on the signup form for new signups.

**Adding required fields to events with existing signups**: If you add a required field to an event that already has volunteers signed up, a confirmation dialog warns you that existing volunteers won't have filled in this field.

**Who can do this**: Organizer only.

## Publish an Event

Publishing makes your event visible to volunteers and enables signups.

1. Go to **Overview** in the event sidebar.
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

Cloning creates a copy of an event with all its jobs, shifts, gear items, custom registration fields, and email templates, but no volunteer signups, gear assignments, or custom field responses.

1. Go to **Overview** in the event sidebar.
2. Click **Clone Event**.
3. You'll be redirected to the new cloned event in Draft status.
4. Update the name, dates, and any other details as needed.

This is useful for recurring events where the job structure stays the same.

**Who can do this**: Organizer only.

## Enroll Volunteers Manually

If you need to add a volunteer to additional shifts after they've already signed up -- for example, reassigning them or filling a gap -- you can enroll them manually without going through the public signup page.

1. Go to **Enroll** in the event sidebar.
2. Search for an existing event volunteer by name or email.
3. Select the shifts you want to enroll them in. Shifts are grouped by job and show capacity and remaining spots.
4. Toggle **Send notification email** on or off depending on whether the volunteer should be notified.
5. Click **Enroll**.

After enrollment, a result summary shows how many shifts were enrolled, how many were skipped because they're full, and how many were skipped because the volunteer was already signed up.

**Who can do this**: Organizer only.

## Archive an Event

Archiving marks an event as completed. Archived events are read-only.

1. Go to **Overview** in the event sidebar.
2. Click **Archive**.

Archived events:
- No longer accept new signups.
- Are still visible in the events list (filter by "Archived").
- Cannot be edited.

**Who can do this**: Organizer only.
