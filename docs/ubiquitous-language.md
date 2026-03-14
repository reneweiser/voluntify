# Ubiquitous Language

This glossary defines the shared vocabulary used throughout Voluntify. Consistent terminology across conversations, documentation, and the product itself reduces confusion and helps everyone -- from new team members to long-time organizers -- stay on the same page.

## People & Roles

**Organization** -- The group, nonprofit, or club that creates and runs events. Every event, team member, and volunteer exists within an organization. A person can belong to multiple organizations.

**Organizer** -- A staff role with full administrative access. Organizers can create events, manage team members, configure settings, and access all data within their organization.

**Volunteer Admin** -- A staff role responsible for on-the-ground shift management. Volunteer Admins can view volunteers, mark shift attendance, and track gear pickup, but cannot manage events, team members, or organization settings.

**Entrance Staff** -- A staff role responsible for scanning tickets at the venue entrance. Entrance Staff use the QR scanner and manual lookup to record arrivals, but have no access to shift management or settings.

**Member** -- Any user who belongs to an organization in a staff role (Organizer, Volunteer Admin, or Entrance Staff). Members log into the admin interface with an email and password.

**Volunteer** -- A person who signs up to help at an event. Volunteers do not need an account -- they sign up with just their name and email. A volunteer may later be promoted to a staff member.

## Events & Scheduling

**Event** -- A specific occasion (festival, cleanup day, gala) with a date, location, and volunteer needs. Events are the central organizing unit in Voluntify.

**Event Group** -- An optional container for organizing related events (e.g., a festival with multiple days). Event groups have a shared public landing page where volunteers can discover and sign up for individual events.

**Draft** -- An event status meaning the event is still being set up. Draft events are not visible to volunteers.

**Published** -- An event status meaning the event is live. Published events have a public signup page and accept volunteer signups.

**Archived** -- An event status meaning the event is over. Archived events are read-only and no longer accept signups.

**Volunteer Job** -- A named function or role at an event, such as "Catering," "Registration Desk," or "Parking." Jobs carry instructions -- the specific information volunteers need (dress code, location, meeting point).

**Shift** -- A time slot within a job that volunteers sign up for. Each shift has a start time, end time, and capacity (the maximum number of volunteers).

**Capacity** -- The maximum number of volunteers who can sign up for a shift. Once a shift reaches capacity, it is full and no longer accepts signups.

## Signup & Verification

**Signup** -- The act of a volunteer claiming a spot on one or more shifts at an event. Volunteers sign up through the public event page with just their name and email.

**Shift Signup** -- The individual record linking a volunteer to a specific shift. One volunteer can have multiple shift signups at the same event (for different shifts).

**Email Verification (Double Opt-In)** -- After signing up, a volunteer receives a verification email and must click a confirmation link before their signup is finalized. This ensures the email address is valid and the signup is intentional.

**Cancellation Cutoff** -- An optional per-event setting that defines how many hours before a shift a volunteer can still cancel their signup. If no cutoff is set, volunteers cannot self-cancel.

**Manual Enrollment** -- The act of an Organizer manually enrolling an existing event volunteer into additional shifts, bypassing the public signup page. Useful for reassigning or adding shifts after initial signup.

**Custom Registration Field** -- An organizer-defined form field added to an event's signup page. Supports three types: text (single-line or multiline), select (dropdown with predefined choices), and checkbox. Fields can be marked as required. Removing a field preserves existing responses but hides the field from new signups.

**Custom Field Response** -- A volunteer's answer to a custom registration field, recorded during signup. Responses are visible on the volunteer detail page, in the volunteer portal, and in CSV exports.

## Tickets & Scanning

**Ticket** -- A QR-coded credential issued to a volunteer for event entrance. Each volunteer receives one ticket per event, covering all their shifts. Tickets are accessed via a magic link -- no login required.

**QR Code** -- The scannable barcode on a volunteer's ticket. It encodes the information needed to verify the volunteer's identity at the entrance, even without an internet connection.

**Magic Link** -- A unique, time-limited URL sent to a volunteer's email that grants access to their ticket page without needing to log in. Each magic link is single-use and expires after a set period.

**Event Arrival** -- The record that a volunteer physically showed up at the event venue. Recorded by Entrance Staff when they scan a QR code or look up a volunteer manually. This is distinct from shift attendance.

**QR Scan** -- The act of scanning a volunteer's QR code at the entrance to record their event arrival.

**Manual Lookup** -- An alternative to QR scanning where Entrance Staff search for a volunteer by name to record their arrival. Used when a volunteer cannot present their QR code.

**Shift Context** -- The real-time shift status information displayed on a scan result, showing each of the volunteer's shifts classified as attended (green), missed (red), active (blue), or upcoming (gray).

**Offline Scanning** -- The ability to validate QR tickets and record arrivals without an internet connection. Arrival and attendance records are stored on the device and automatically synced when connectivity returns.

## Attendance

**Attendance Record** -- The record of whether a volunteer showed up to their assigned shift. Recorded by a Volunteer Admin or Organizer at the shift level -- separate from the event arrival at the entrance.

**On Time** -- An attendance status indicating the volunteer arrived at their shift station on time.

**Late** -- An attendance status indicating the volunteer arrived at their shift station after the shift started.

**No-Show** -- An attendance status indicating the volunteer did not show up for their shift at all. A volunteer can arrive at the event (recorded as an event arrival) but still be a no-show for their shift if they don't report to their station. Volunteers are automatically marked as No-Show if their shift ended more than 2 hours ago with no attendance record.

**Attendance Grace Period** -- An optional per-event setting defining how many minutes after a shift start a scan is still marked as On Time. Scans after the grace window are marked Late. If not set, any scan after shift start is Late.

## Gear

**Event Gear Item** -- A piece of equipment or material that an event provides to volunteers, such as a t-shirt, walkie-talkie, or name badge. Gear items are defined per event and may optionally have sizes.

**Volunteer Gear** -- The assignment of a gear item to a specific volunteer at an event. Tracks whether the item has been picked up.

**Gear Pickup** -- The act of a volunteer collecting their assigned gear. Recorded by an Organizer or Volunteer Admin, typically at a check-in station.

## Communication

**Email Template** -- A customizable email template for a specific event. Organizers can personalize the subject and body of signup confirmations and pre-shift reminders. Templates support placeholder variables for volunteer and event details.

**Signup Confirmation** -- The email sent to a volunteer after their signup is verified. Contains event details, shift information, and a magic link to their ticket.

**Pre-Shift Reminder** -- An automated email sent to volunteers before their shift (at 24 hours and 4 hours before start). Includes shift time, job name, and any job-specific instructions.

**Announcement** -- A message sent by an Organizer to all volunteers signed up for an event. Volunteers can read announcements in their portal.

## Organization & Settings

**Public Token** -- A random, unguessable string used in the event's public URL (e.g., `/events/a1b2c3`). Prevents anyone from guessing event URLs by trying sequential numbers or common names.

**Volunteer Promotion** -- The process of elevating a volunteer to a staff role. Creates a user account for the volunteer, assigns them a role within the organization, and sends them login credentials.

**Activity Log** -- A record of significant actions within an organization (member invitations, role changes, event updates). Visible to Organizers in the settings area.

**Volunteer Portal** -- A self-service page where volunteers can view their upcoming shifts, read event announcements, see gear assignments, and cancel signups (if allowed). Accessed via a magic link -- no account needed.
