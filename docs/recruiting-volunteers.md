# Recruiting Volunteers

This guide covers how to publish your event, share the signup link, and what the volunteer experience looks like.

![Volunteer journey from browsing the event to receiving a QR ticket](figures/volunteer-journey.svg)

## Publish Your Event

Before volunteers can sign up, your event must be published. See [Creating Events > Publish an Event](creating-events.md#publish-an-event) for steps.

Once published, a unique public URL is generated for your event (e.g., `https://your-domain/events/abc123`). This URL uses a random token -- it doesn't expose your event's internal ID.

## Share the Signup Link

1. Go to **Overview** in the event sidebar.
2. Click **Copy Link** to copy the public event URL to your clipboard.

Share this link however you'd like -- email, social media, your organization's website, messaging apps, printed flyers, etc. Anyone with the link can view the event and sign up.

## What Volunteers See

When a volunteer opens your event's public link, they see:

1. **Event header** -- The event name, dates, location, and description. If you uploaded a title image, it appears as a hero banner.
2. **Job listing** -- An accordion of volunteer jobs. Each job shows its name and description. Jobs with instructions also display a "View Instructions" link to a standalone cheat sheet page.
3. **Available shifts** -- Within each job, shift cards show the time slot, capacity, and remaining spots (e.g., "3 of 5 spots filled").
4. **Signup form** -- When a volunteer selects a shift, a form appears asking for:
   - **Name** (required)
   - **Email** (required)
   - **Phone** (optional)
5. **Gear selection** (if applicable) -- If the event has gear items, a gear form appears after the signup fields. Sized items show a size dropdown; non-sized items are listed as informational.

The volunteer clicks **Sign Up** to register. If a shift is full, it shows a "Full" badge and the signup button is disabled.

No account or password is needed. Volunteers sign up with just their name and email.

## Email Verification (Double Opt-In)

After signing up, volunteers receive a verification email to confirm their email address. This is a GDPR-compliant double opt-in process:

1. The volunteer signs up on the public page.
2. They receive a verification email with a confirmation link.
3. They click the link to verify their email.
4. Once verified, their signup is confirmed and they receive their ticket.

## Volunteer Ticket and Magic Link

After verification, the volunteer receives a confirmation email containing:

- Event details (name, date, location)
- Their assigned shift information
- A **magic link** to view their QR ticket

The magic link opens their ticket page, which shows:

- A large **QR code** -- This is their entrance ticket. It contains a signed JWT token that can be validated offline.
- Their name and email.
- Event details.
- All shifts they're signed up for.
- A **Manage Your Shifts** link that opens the volunteer portal (see below).

Volunteers should save this page or screenshot the QR code for easy access on event day. The magic link works without logging in -- no password needed.

If a volunteer signs up for multiple shifts at the same event, they get one ticket covering all their shifts.

## Volunteer Portal

The volunteer portal gives volunteers a self-service view of their shifts and any announcements from organizers. Volunteers access it via the **Manage Your Shifts** link on their ticket page -- no login required.

The portal shows four sections:

- **Upcoming Shifts** -- All future shifts the volunteer is signed up for, sorted by date. Each entry shows the event name, job, and shift time.
- **Past Shifts** -- Completed shifts, sorted most recent first.
- **Event Gear** -- Gear assigned to the volunteer, showing the item name, event name, size (if applicable), and pickup status (Picked Up / Not Picked Up).
- **Announcements** -- Messages sent by the organizer (e.g., parking changes, schedule updates). Only sent announcements appear here.

If the link has expired, volunteers see a message asking them to request a new magic link from the organizer.

## Cancelling a Signup

If the organizer has enabled cancellations for an event (see [Creating Events > Edit Event Details](creating-events.md#edit-event-details)), volunteers can cancel their own shift signups from the portal.

1. Open the volunteer portal via the **Manage Your Shifts** link on the ticket page.
2. Find the shift under **Upcoming Shifts**.
3. Click **Cancel** next to the shift.
4. Confirm the cancellation in the modal that appears.

The spot is freed immediately and becomes available for other volunteers.

Cancellation is only available if:
- The organizer set a **Cancellation Cutoff** on the event.
- The shift starts more than the cutoff number of hours from now.

If cancellation is disabled or the cutoff has passed, the cancel button won't appear.

## Tips for Recruiting

- **Customize your emails**: Use the [Email Template Editor](creating-events.md#customize-email-templates) to add event-specific information to confirmation and reminder emails.
- **Add job instructions**: Fill in the Instructions field for each job. Instructions are published as a cheat sheet page linked from the public signup page, and included in pre-shift reminder emails so volunteers know where to go and what to bring.
- **Monitor signups**: Check the **Volunteers** page to see who has signed up and which shifts still need people.
- **Share widely**: The public URL is safe to share publicly -- it doesn't expose any admin functionality.

## Public Event API

Voluntify provides a JSON API endpoint for external integrations, allowing you to embed event data on your website or build custom signup forms.

**Endpoint**: `GET /api/v1/events/{publicToken}`

The `publicToken` is the same token used in your public event URL. The endpoint is publicly accessible (no authentication required) and returns event details including jobs, shifts, and remaining capacity.

**Rate limit**: 60 requests per minute per IP address. Responses are cached for 60 seconds.

**Example use cases**:
- Embed event details and shift availability on your organization's website.
- Build a custom signup form that checks capacity before submitting.
- Display upcoming volunteer opportunities in a third-party app or intranet.
