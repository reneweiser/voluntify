# App Concept: Volunteer Management for Events

**Date**: 2026-02-28
**Domain**: Volunteer management software for small/mid-sized event-running organizations

## Product Identity

### Name Candidates

| Name | Rationale | Domain Available | Verdict |
|---|---|---|---|
| **Voluntify** | Combines "volunteer" + "-ify" (to make). Action-oriented, memorable, easy to spell. | Already the project name | Top pick |

### Value Proposition

**One-liner**: For event organizers who struggle with volunteer coordination, Voluntify is a volunteer management platform that lets volunteers sign up without an account, delivers QR-coded event tickets, and validates them offline at the entrance. Unlike SignUpGenius or VolunteerHub, it combines the entire volunteer lifecycle in one affordable tool.

### Target Personas

#### Persona 1: Community Organizer Clara

**Role**: Event organizer at a community nonprofit
**Context**: Small team (2–5 staff), runs 4–12 events per year, 20–100 volunteers per event
**Goals**: Fill all volunteer shifts, run smooth event-day operations, retain reliable volunteers
**Frustrations**: (1) Can't afford VolunteerHub/Rosterfy, (2) Juggles 3 tools for signup/tickets/check-in, (3) Spends hours helping volunteers navigate complex platforms
**Current tools**: SignUpGenius for signup, Google Sheets for tracking, paper lists at the door
**Willingness to pay**: $20–$50/month for a tool that replaces all three

#### Persona 2: Festival Volunteer Admin Victor

**Role**: Volunteer coordinator for a mid-sized annual festival
**Context**: Manages 50–200 volunteers across 10–20 jobs, works on-the-ground during events
**Goals**: Know who's on-site, who's at their station, and who's a no-show — in real-time
**Frustrations**: (1) Phone-unfriendly admin tools, (2) No way to distinguish event arrival from shift attendance, (3) No-show data lost after each event
**Current tools**: Spreadsheets, walkie-talkies, paper clipboards
**Willingness to pay**: Part of org's decision; advocates for tools under $100/month

## Core Features

### Feature 1: Passwordless Volunteer Signup with Public Event Pages

**Addresses**: SP-1 (account-creation friction), PP-3 (coordinator tech support), PR-1 (no unified workflow)
**Priority**: Must Have

**User stories**:
- As a volunteer, I want to sign up for an event shift with only my name and email, so I don't have to create an account for a one-time event
- As an organizer, I want to publish a public event page with jobs and shifts, so volunteers can browse and self-serve signup without my help
- As a returning volunteer, I want the system to recognize my email and pre-fill my name, so I can sign up faster

**Key capabilities**:
- Public event page accessible via a shareable URL (uses `public_token`, not slug/ID, to prevent enumeration)
- Job and shift browsing with real-time capacity display
- Name + email signup form — no password, no account creation
- Returning volunteer recognition by email address
- Confirmation email with event details sent immediately upon signup

### Feature 2: QR Ticket Generation and Distribution

**Addresses**: FP-1 (affordable tools lack QR), PR-1 (no unified workflow)
**Priority**: Must Have

**User stories**:
- As a volunteer, I want to receive a QR-coded event ticket via email after signing up, so I can present it at the entrance without printing anything
- As a volunteer, I want to access my ticket anytime via a magic link in my email, so I don't need to create an account or remember a password
- As an organizer, I want tickets generated automatically upon signup, so I don't have to manually create and distribute them

**Key capabilities**:
- One ticket per volunteer per event (covers all their shifts at that event)
- QR code encodes a JWT with volunteer ID, event ID, and shift IDs — self-contained for offline validation
- Ticket page accessible via magic link (SHA-256 hashed token stored in DB, no account needed)
- Ticket email sent as part of the signup confirmation flow
- Ticket page shows volunteer name, event details, assigned shifts, and the QR code

### Feature 3: Offline QR Scanning at Entrance

**Addresses**: PP-1 (manual paper check-in), FP-1 (affordable scanning), PR-1 (unified workflow), SP-2 (mobile experience)
**Priority**: Must Have

**User stories**:
- As entrance staff, I want to scan volunteer QR codes on my phone, so I can process arrivals quickly without paper lists
- As entrance staff, I want the scanner to work without internet, so I can operate at venues with poor connectivity
- As entrance staff, I want to look up a volunteer by name when they can't present a QR code, so no one is turned away for a technical issue
- As entrance staff, I want to see flags (late, no-show history) when scanning a volunteer, so I can note any issues

**Key capabilities**:
- PWA-based scanner that works offline via Service Worker
- JWT validation on-device (HS256 with per-event, per-day derived HMAC key — limits exposure if IndexedDB is compromised)
- Pre-downloaded volunteer list cached in IndexedDB for manual lookup
- Arrival records queued in IndexedDB and synced when connectivity returns
- Duplicate scan detection (already marked as arrived)
- Flag display: volunteer has a no-show on another shift, shift hasn't started yet

### Feature 4: Pre-Shift Notifications

**Addresses**: PR-1 (unified workflow), PP-3 (coordinator tech support)
**Priority**: Should Have

**User stories**:
- As a volunteer, I want to receive a reminder 24 hours and 4 hours before my shift with job-specific instructions, so I know exactly where to go and what to bring
- As an organizer, I want to attach job-specific instructions (dress code, parking, check-in location) to each job, so they're automatically included in reminders

**Key capabilities**:
- Scheduled email notifications at 24h and 4h before shift start
- Notifications include: event name, job name, shift time, job-specific instructions
- Instructions are attached to the job entity, not the notification — single source of truth
- Notification tracking flags on signup records (24h_sent, 4h_sent)

### Feature 5: Shift Attendance Verification

**Addresses**: PR-2 (event arrival vs shift attendance), PR-3 (no-show detection)
**Priority**: Should Have

**User stories**:
- As a volunteer admin, I want to mark volunteers as on-time, late, or no-show at their shift, so I have an accurate record separate from entrance arrival
- As an organizer, I want to see no-show rates per event and per volunteer, so I can make better recruitment decisions

**Key capabilities**:
- Shift roster view for Volunteer Admin with mark-as: on_time, late, no_show
- Separate from entrance arrival records (different entity, different actor)
- Historical attendance data aggregated per volunteer
- Real-time shift fill status visible to organizer

### Feature 6: AI-Powered Event Creation

**Addresses**: PP-3 (coordinator tech support), PR-1 (unified workflow)
**Priority**: Should Have

**User stories**:
- As an organizer, I want to describe an event in natural language and have the system create it with jobs and shifts, so I can skip filling out multiple forms
- As an organizer, I want to iteratively refine the event through conversation ("add another shift to the setup crew"), so I can adjust without navigating back and forth
- As an organizer, I want to review what the AI created before publishing, so I stay in control

**Key capabilities**:
- In-app chat interface for organizers (browser-based, no external tools)
- Natural language → creates event, volunteer jobs, and shifts via the same Actions as the form UI
- Conversational — supports follow-up requests (add/modify/remove jobs and shifts)
- Events created as draft — organizer explicitly publishes
- Powered by Claude API (via Vercel AI Gateway), using the organization's own API key
- Optional — organizers without a configured API key use the traditional form UI
- The AI chat and form UI produce identical data (same Actions, same domain model)

## Pain Point Traceability

| Feature | Pain Points Addressed | JTBD Fulfilled |
|---|---|---|
| Passwordless Volunteer Signup | SP-1, PP-3, PR-1 | Sign up quickly without account creation; organizers free from tech support |
| QR Ticket Generation | FP-1, PR-1 | Affordable QR capability; unified workflow from signup to ticket |
| Offline QR Scanning | PP-1, FP-1, PR-1, SP-2 | Digital entrance check-in that works offline; affordable scanning |
| Pre-Shift Notifications | PR-1, PP-3 | Job-specific info reaches volunteers automatically |
| Shift Attendance Verification | PR-2, PR-3 | Separate event arrival from shift attendance; detect no-shows |
| AI-Powered Event Creation | PP-3, PR-1 | Organizer creates events through conversation; no form fatigue |

## Domain Model

### Key Entities

| Entity | Description | Key Attributes |
|---|---|---|
| `organizations` | The group or nonprofit that runs events | `id`, `name`, `slug`, `ai_api_key` (encrypted, nullable — stores the org's AI API key; post-MVP) |
| `users` | Authenticated accounts for staff roles (Organizer, Volunteer Admin, Entrance Staff) | `id`, `name`, `email`, `password`, `must_change_password` |
| `organization_user` | Pivot table: user role per organization | `organization_id`, `user_id`, `role` (enum: organizer, volunteer_admin, entrance_staff) |
| `events` | An event run by an organization | `id`, `organization_id`, `name`, `slug`, `public_token`, `description`, `location`, `starts_at`, `ends_at`, `status` (enum: draft, published, archived), `title_image_path` (nullable — hero image stored on public disk) |
| `volunteer_jobs` | A named role/function at an event (named to avoid collision with Laravel's queue `jobs` table) | `id`, `event_id`, `name`, `description`, `instructions` (job-specific info for notifications) |
| `shifts` | A time slot within a job with capacity | `id`, `volunteer_job_id`, `starts_at`, `ends_at`, `capacity` |
| `volunteers` | People who sign up for events (no account needed) | `id`, `name`, `email` (unique), `phone` (nullable, max 20), `user_id` (nullable FK — set when promoted) |
| `shift_signups` | Volunteer-to-shift assignment | `id`, `volunteer_id`, `shift_id`, `signed_up_at`, `notification_24h_sent`, `notification_4h_sent` |
| `attendance_records` | Shift-level attendance logged by Volunteer Admin | `id`, `shift_signup_id`, `status` (enum: on_time, late, no_show), `recorded_by` (user_id), `recorded_at` |
| `tickets` | One QR-coded ticket per volunteer per event | `id`, `volunteer_id`, `event_id`, `jwt_token`, `created_at` |
| `event_arrivals` | Entrance scan records — separate from shift attendance | `id`, `ticket_id`, `volunteer_id`, `event_id`, `scanned_by` (nullable user_id), `scanned_at`, `method` (enum: qr_scan, manual_lookup), `flagged`, `flag_reason` |
| `magic_link_tokens` | Hashed tokens for passwordless ticket page access | `id`, `volunteer_id`, `token_hash` (SHA-256), `expires_at` |
| `volunteer_promotions` | Audit log when a volunteer is promoted to a staff role | `id`, `volunteer_id`, `user_id` (newly created), `promoted_by` (user_id), `role`, `promoted_at` |
| `email_templates` | Customizable email templates per event (M2.1) | `id`, `event_id`, `type` (enum: signup_confirmation, pre_shift_reminder_24h, pre_shift_reminder_4h), `subject`, `body` (text, supports `{{placeholder}}` variables), unique on `[event_id, type]` |

### Relationships

```
[organizations] 1───N [events]
[organizations] N───M [users] (via organization_user with role)
[events] 1───N [volunteer_jobs]
[volunteer_jobs] 1───N [shifts]
[shifts] N───M [volunteers] (via shift_signups)
[volunteers] 1───N [shift_signups]
[shift_signups] 1───0..1 [attendance_records]
[volunteers] 1───N [tickets] (one per event)
[tickets] 1───N [event_arrivals]
[volunteers] 1───N [event_arrivals]
[volunteers] 0..1───1 [users] (when promoted)
[volunteers] 1───N [magic_link_tokens]
[volunteers] 1───0..1 [volunteer_promotions]
[events] 1───N [email_templates]
```

### Ubiquitous Language Glossary

| Term | Definition | Context |
|---|---|---|
| **Organization** | The group, nonprofit, or club that creates and runs events. The top-level tenant. | Multi-tenancy boundary. All events, users, and volunteers exist within an organization context. |
| **Event** | A specific occurrence (festival, gala, cleanup day) with a date, location, and volunteer needs. | Events have a lifecycle: draft → published → archived. Only published events have public signup pages. |
| **Volunteer Job** | A named function or role at an event (e.g., "Catering," "Registration Desk," "Parking"). Named `volunteer_jobs` in the database to avoid collision with Laravel's `jobs` queue table. | Jobs carry instructions — the specific info volunteers need (dress code, location, supervisor). |
| **Shift** | A time slot within a job that volunteers sign up for. Has a start time, end time, and capacity. | The atomic unit of volunteer scheduling. A volunteer signs up for a specific shift of a specific job. |
| **Volunteer** | A person who signs up to help at an event. Does not require a user account. Identified by email. | Volunteers exist independently of authentication. They may later be promoted to a user (staff) account. |
| **Signup** | The act of a volunteer claiming a spot on a specific shift. Recorded as a `shift_signup`. | One volunteer can have multiple signups (different shifts) at the same event. |
| **Ticket** | A QR-coded credential issued to a volunteer for event entrance. One per volunteer per event. | Contains a JWT for offline validation. Accessed via magic link — no login required. |
| **Event Arrival** | The record that a volunteer physically arrived at the event venue. Recorded by Entrance Staff. | Distinct from shift attendance. A volunteer can arrive at the event but not show up to their shift. |
| **Shift Attendance** | The record that a volunteer reported to their assigned shift station. Recorded by Volunteer Admin. | Tracks on_time, late, or no_show. Different actor (Vol Admin) and different time than event arrival. |
| **Magic Link** | A single-use or time-limited URL that grants access to a volunteer's ticket page without authentication. | SHA-256 hashed tokens stored in DB. Sent via confirmation email. Volunteers click to view/re-download their ticket. |
| **Public Token** | A random, unguessable string in the event URL that serves as the public identifier for an event page. | Prevents event enumeration by sequential ID or predictable slug. Format: `/events/{public_token}`. |
| **Volunteer Promotion** | The process of elevating a volunteer to a staff role (Volunteer Admin, Entrance Staff). | Creates a user account for the volunteer, sends a temporary password, and requires password change at first login. |
| **No-Show** | A volunteer who signed up for a shift but did not attend. Recorded as an attendance status. | Flagged at the entrance (if the volunteer never arrives at the event) and at the shift level (if they arrive but don't report to their station). |
| **Organizer** | A user role with full administrative access to an organization's events, jobs, shifts, volunteers, and team. | The highest-privilege role. Can create events, manage team members, and view all data. |
| **Volunteer Admin** | A user role responsible for on-the-ground shift management. Can mark attendance. | Limited scope: sees their assigned event's volunteers and shifts. Cannot manage team or org settings. |
| **Entrance Staff** | A user role responsible for scanning tickets and recording event arrivals at the venue entrance. | Uses the QR scanner PWA. Can also do manual volunteer lookup. Cannot manage shifts or attendance. |
| **Offline Scanning** | The ability to validate QR tickets and record arrivals without an internet connection. | Achieved via JWT validation on-device + IndexedDB for volunteer data cache and arrival queue. Syncs when connectivity returns. |
| **AI Chat** | An in-app conversational interface that lets organizers create events by describing them in natural language. Uses Claude API tool use to call the same Actions as the form UI. | Optional feature, gated on the organization having an AI API key configured. Only available to the Organizer role. |
| **AI Gateway** | A proxy service (Vercel AI Gateway) that routes AI API requests. Each organization provides its own API key. | The gateway URL is configured at the application level. The API key is stored encrypted per organization. Organizers can configure/remove their key via organization settings. |

## User Validation Notes

Product name "Voluntify" was pre-selected by the user as the project name. The value proposition and feature set directly reflect the user's requirements document, which specified three core workflows: volunteer recruiting (passwordless), ticket distribution (QR), and entrance validation (offline scanning). The domain model's 13 entities were designed to support the explicit requirement of separating "arrived at event" from "arrived at shift" tracking, and the `volunteer_jobs` naming convention addresses the user's Laravel tech stack (avoiding `jobs` table collision).
