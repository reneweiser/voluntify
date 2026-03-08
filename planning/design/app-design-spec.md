# App Design Spec: Voluntify

**Date**: 2026-02-28
**Domain**: Volunteer management software for small/mid-sized event-running organizations
**Version**: 1.0

## Information Architecture

### Sitemap

```
Voluntify
│
├── PUBLIC ROUTES (no auth)
│   ├── /events/{public_token}              — Public Event Page (volunteer signup)
│   └── /my-ticket/{magic_token}            — Volunteer Ticket Page (QR code view)
│
├── AUTH ROUTES
│   ├── /login                              — Login (Fortify)
│   ├── /forgot-password                    — Password reset request
│   ├── /reset-password/{token}             — Password reset form
│   └── /change-password                    — Forced password change (promoted volunteers)
│
├── AUTHENTICATED ROUTES (role-gated)
│   ├── /dashboard                          — Role-adaptive dashboard
│   ├── /events                             — Events list
│   │   ├── /events/create                  — Create Event (modal or page)
│   │   └── /events/{event}                 — Event detail (tabbed)
│   │       ├── /events/{event}/overview     — Event overview tab
│   │       ├── /events/{event}/jobs         — Jobs & Shifts Manager tab
│   │       ├── /events/{event}/volunteers   — Volunteer List tab
│   │       │   └── /events/{event}/volunteers/{volunteer} — Volunteer Detail
│   │       └── /events/{event}/attendance   — Attendance Tracker tab
│   ├── /scanner                            — QR Scanner (PWA, fullscreen)
│   ├── /scanner/lookup                     — Manual Lookup (fallback search)
│   └── /settings
│       └── /settings/team                  — Organization Team Management
│
└── API ROUTES (internal, Livewire-driven)
    ├── POST /api/scanner/sync              — Sync offline arrival records
    └── GET  /api/scanner/data/{event}      — Download volunteer list for offline cache
```

### Navigation Structure

| Location | Type | Items | Visible To |
|---|---|---|---|
| Sidebar | Primary | Dashboard, Events, Scanner, Settings | All authenticated roles |
| Event sub-nav | Secondary (tabs) | Overview, Jobs & Shifts, Volunteers, Attendance | Contextual within event detail |
| User menu | Utility | Profile, Logout | All authenticated users |

**Role-Based Visibility Matrix**:

| Nav Item | Organizer | Volunteer Admin | Entrance Staff |
|---|---|---|---|
| Dashboard | Yes | Yes | Yes |
| Events (list) | Yes | Yes (assigned events only) | Yes (assigned events only) |
| Event > Overview | Yes | Yes | No |
| Event > Jobs & Shifts | Yes (edit) | Yes (read-only) | No |
| Event > Volunteers | Yes | Yes | No |
| Event > Attendance | Yes (read-only) | Yes (edit) | No |
| Scanner | Yes | No | Yes |
| Scanner > Manual Lookup | Yes | No | Yes |
| Settings > Team | Yes | No | No |
### Content Priority

| Priority | Content | Rationale |
|---|---|---|
| 1 | Event operations (jobs, shifts, volunteers) | Core workflow — where organizers spend most time |
| 2 | QR Scanner | Critical for event-day operations — needs instant access |
| 3 | Dashboard summaries | Quick orientation and status overview |

## Page Specifications

### Page 1: Public Event Page

**Route**: `/events/{public_token}`
**Purpose**: Allow volunteers to browse available jobs/shifts and sign up without an account
**Auth required**: No

**Layout**:
- **Structure**: Full-width, single-column, mobile-optimized
- **Key sections**: Event header (name, date, location, description) → Job listing with expandable shifts → Signup form (appears inline when a shift is selected)

**Components**:
| Component | Purpose | Data Source | Key Interactions |
|---|---|---|---|
| Title Image Banner | Display hero image above event header (if uploaded) | `events.title_image_path` via Storage disk | None (display only) |
| Event Header | Display event info | `events` table via `public_token` | None (display only) |
| Job Accordion | List jobs with expandable shift slots | `volunteer_jobs` + `shifts` with signup counts | Expand/collapse, select shift |
| Shift Card | Show shift time, capacity, spots remaining | `shifts` with aggregated `shift_signups` count | Click to select for signup |
| Signup Form | Collect volunteer name, email, and optional phone | Form input → `volunteers` + `shift_signups` | Submit: validate, create records, send confirmation email |
| Capacity Badge | Show "X of Y spots filled" per shift | Real-time count from `shift_signups` | Auto-updates via Livewire polling |

**User Actions**:
1. **Browse jobs**: Expand job accordion to see available shifts
2. **Select shift**: Click a shift card to reveal the signup form
3. **Submit signup**: Enter name, email, and optional phone number, submit form. System creates volunteer (or finds existing by email), creates shift_signup, generates ticket + QR, sends confirmation email

**Data Requirements**:
- **Read**: Event (by public_token), volunteer_jobs (for event), shifts (with signup counts), capacity
- **Write**: volunteers (upsert by email), shift_signups, tickets, magic_link_tokens

**States**:
- **Loading**: Skeleton cards for job list
- **Empty**: "No shifts available yet — check back soon!" if event is published but has no jobs/shifts
- **Full shift**: Shift card shows "Full" badge, signup button disabled for that shift
- **Error**: Inline validation errors on the signup form (invalid email, name too short). Server errors show toast notification.
- **Success**: Confirmation message: "You're signed up! Check your email for your event ticket."
- **Not found**: 404 page if public_token doesn't match any published event

### Page 2: Volunteer Ticket Page

**Route**: `/my-ticket/{magic_token}`
**Purpose**: Display the volunteer's QR-coded event ticket and shift details
**Auth required**: No (magic link provides access)

**Layout**:
- **Structure**: Centered single-column, card-based, optimized for both screen display and phone brightness
- **Key sections**: QR code (large, centered) → Volunteer name → Event details → Assigned shifts list

**Components**:
| Component | Purpose | Data Source | Key Interactions |
|---|---|---|---|
| QR Code Display | Large, high-contrast QR code for scanning | `tickets.jwt_token` rendered as QR | None (display only) |
| Volunteer Info | Name and email | `volunteers` table | None (display only) |
| Event Details | Event name, date, location | `events` table | None (display only) |
| Shift List | All shifts this volunteer is signed up for | `shift_signups` → `shifts` → `volunteer_jobs` | None (display only) |

**User Actions**:
1. **View ticket**: Page loads with QR code prominently displayed — ready to scan
2. **Save/screenshot**: Volunteer can screenshot or save page for offline access

**Data Requirements**:
- **Read**: magic_link_tokens (validate token, find volunteer), tickets (for QR), events, shift_signups, shifts, volunteer_jobs
- **Write**: None

**States**:
- **Loading**: Spinner centered on page
- **Valid**: Full ticket display with QR code
- **Expired**: "This link has expired. Check your email for a new ticket link, or contact the event organizer."
- **Not found**: 404 page if magic_token is invalid

### Page 3: Dashboard

**Route**: `/dashboard`
**Purpose**: Role-adaptive overview of upcoming events and key metrics
**Auth required**: Yes (all authenticated roles)

**Layout**:
- **Structure**: Sidebar + main content area. Main area has summary cards at top, event list below.
- **Key sections**: Welcome message → Summary cards (upcoming events count, total volunteers, shifts needing attention) → Upcoming events list

**Components**:
| Component | Purpose | Data Source | Key Interactions |
|---|---|---|---|
| Summary Cards | Key metrics at a glance | Aggregated queries | Click to navigate to relevant section |
| Upcoming Events List | Events sorted by date, nearest first | `events` (filtered by org, status=published) | Click to navigate to event detail |
| Quick Actions | "Create Event" button (Organizer only) | N/A | Navigate to event creation |

**User Actions**:
1. **View summary**: See at-a-glance metrics for the organization
2. **Navigate to event**: Click an event card to go to its detail page
3. **Create event** (Organizer only): Click "Create Event" to start event setup

**Data Requirements**:
- **Read**: Organizations (current org context), Events (for org, upcoming), aggregated volunteer counts, shift fill rates
- **Write**: None

**States**:
- **Loading**: Skeleton cards
- **Empty**: "No upcoming events. Create your first event to get started." with CTA button (Organizer) or "No events assigned to you yet." (Volunteer Admin, Entrance Staff)
- **Normal**: Cards and event list populated

### Page 4: Events List

**Route**: `/events`
**Purpose**: List all events for the organization with status filtering
**Auth required**: Yes

**Layout**:
- **Structure**: Sidebar + main content with filter bar at top, event cards/rows below
- **Key sections**: Filter bar (status: all/draft/published/archived) → Event list (sortable by date)

**Components**:
| Component | Purpose | Data Source | Key Interactions |
|---|---|---|---|
| Status Filter | Filter events by status | Local state | Click to filter |
| Event Row | Show event name, date, status, volunteer count | `events` with aggregated counts | Click to navigate to event detail |
| Create Event Button | Start new event creation | N/A | Opens create event modal (Organizer only) |
| Create Event Modal | Form: name, dates, location, description | N/A | Submit: validate, create event in draft status, navigate to event detail |

**User Actions**:
1. **Filter by status**: Toggle between All, Draft, Published, Archived
2. **Navigate to event**: Click event row to go to detail page
3. **Create event** (Organizer only): Click "Create Event" → modal with name, dates, location, description → submit creates event in draft status → navigates to `/events/{event}/jobs` to add jobs and shifts

**Data Requirements**:
- **Read**: Events (for org, with status filter), volunteer counts per event
- **Write**: None from this page

**States**:
- **Loading**: Skeleton rows
- **Empty**: "No events yet. Create your first event!" with CTA (Organizer) or "No events assigned to you." (other roles)
- **Filtered empty**: "No [status] events found."

### Page 5: Event Detail / Overview

**Route**: `/events/{event}/overview`
**Purpose**: Event summary with key metrics and management actions
**Auth required**: Yes (Organizer, Volunteer Admin)

**Layout**:
- **Structure**: Sidebar + main content with tab bar at top (Overview, Jobs & Shifts, Volunteers, Attendance)
- **Key sections**: Event header (name, dates, status badge) → Tab bar → Metric cards (total jobs, total shifts, volunteers signed up, fill rate, arrival rate) → Event description → Share link section

**Components**:
| Component | Purpose | Data Source | Key Interactions |
|---|---|---|---|
| Title Image | Hero image displayed above event details (if uploaded) | `events.title_image_path` via Storage disk | Upload/replace/delete in edit mode (Organizer) |
| Event Header | Name, dates, location, status | `events` table | Edit button (Organizer) |
| Tab Bar | Navigate between event sections (Overview, Jobs & Shifts, Emails) | Route-based | Click to switch tabs |
| Metric Cards | Key numbers at a glance | Aggregated queries | None |
| Share Link | Public event URL with copy button | `events.public_token` | Click to copy URL |
| Status Actions | Publish / Archive buttons | `events.status` | Click to change event status |

**User Actions**:
1. **Edit event details** (Organizer): Update name, dates, location, description, title image
2. **Upload/replace title image** (Organizer): Upload a hero image (jpg, png, webp, max 2MB)
3. **Delete title image** (Organizer): Remove the hero image
4. **Publish event** (Organizer): Change status from draft to published, making the public page live
5. **Archive event** (Organizer): Mark event as archived after completion
6. **Copy share link**: Copy the public event URL to clipboard
7. **Navigate tabs**: Switch to Jobs, Emails, Volunteers, or Attendance sections

**Data Requirements**:
- **Read**: Event details, aggregated metrics (job count, shift count, signup count, capacity, arrival count)
- **Write**: Event updates (name, dates, description, status, title_image_path)

**States**:
- **Loading**: Skeleton layout
- **Draft**: Status badge shows "Draft", publish button visible, share link shows "Publish to enable signup"
- **Published**: Status badge shows "Published", archive button visible, share link active
- **Archived**: Status badge shows "Archived", read-only view, no edit actions

### Page 6: Jobs & Shifts Manager

**Route**: `/events/{event}/jobs`
**Purpose**: Create and manage volunteer jobs and their time-based shifts
**Auth required**: Yes (Organizer: full edit; Volunteer Admin: read-only)

**Layout**:
- **Structure**: Tab content area under event tab bar. Job list as expandable sections, shifts inline within each job.
- **Key sections**: "Add Job" button → Job sections (each with name, description, instructions, and a shift table) → Inline shift editing

**Components**:
| Component | Purpose | Data Source | Key Interactions |
|---|---|---|---|
| Add Job Button | Create a new volunteer job | N/A | Opens inline job creation form |
| Job Section | Expandable section for each job | `volunteer_jobs` | Edit name/description/instructions, delete, expand/collapse |
| Instructions Field | Job-specific info for pre-shift notifications | `volunteer_jobs.instructions` | Edit (textarea) |
| Shift Table | List shifts within a job | `shifts` for the job | Add, edit, delete shifts |
| Shift Row | Individual shift with times and capacity | `shifts` table | Inline edit: start time, end time, capacity |
| Capacity Display | "X / Y signed up" per shift | `shift_signups` count vs `shifts.capacity` | None (display only) |

**User Actions**:
1. **Add job**: Create new job with name, description, and instructions
2. **Edit job**: Update job name, description, or instructions inline
3. **Delete job**: Remove a job (confirm if it has signups)
4. **Add shift**: Add a time slot to a job with start time, end time, and capacity
5. **Edit shift**: Modify shift times or capacity inline
6. **Delete shift**: Remove a shift (confirm if it has signups)

**Data Requirements**:
- **Read**: volunteer_jobs (for event), shifts (per job), shift_signups (count per shift)
- **Write**: volunteer_jobs (CRUD), shifts (CRUD)

**States**:
- **Loading**: Skeleton sections
- **Empty**: "No jobs defined yet. Add your first volunteer job to get started."
- **Job with no shifts**: Prompt "Add shifts to this job so volunteers can sign up"
- **Shift at capacity**: Capacity badge shows full in warning color
- **Delete confirmation**: Modal asking "Are you sure? X volunteers are signed up for this shift."

### Page 6b: Email Template Editor (M2.1)

**Route**: `/admin/events/{eventId}/emails`
**Purpose**: Customize automated email templates per event with placeholder variables
**Auth required**: Yes (Organizer only)

**Layout**:
- **Structure**: Tab content area under event tab bar. Template type selector → Subject/body editor → Placeholder reference panel → Preview panel.

**Components**:
| Component | Purpose | Data Source | Key Interactions |
|---|---|---|---|
| Template Type Selector | Choose which email to customize | `EmailTemplateType` enum | Dropdown select, loads template |
| Subject Input | Edit email subject line | `email_templates.subject` or default | Text input with placeholder support |
| Body Textarea | Edit email body content | `email_templates.body` or default | Textarea with placeholder support |
| Placeholder Reference | Show available `{{variables}}` | `EmailTemplateRenderer::availablePlaceholders()` | Display only |
| Preview Panel | Rendered preview with sample data | `EmailTemplateRenderer::render()` | Click "Preview" to render |
| Customization Badge | Shows "Customized" or "Using default" | `email_templates` existence check | Display only |

**User Actions**:
1. **Select template type**: Choose from Signup Confirmation, Pre-Shift Reminder (24h), Pre-Shift Reminder (4h)
2. **Edit subject/body**: Customize the template text with `{{placeholder}}` variables
3. **Save template**: Persist the customized template (upsert by event + type)
4. **Preview**: View rendered email with sample data
5. **Reset to default**: Delete custom template, revert to built-in defaults

**Available Placeholders**: `{{volunteer_name}}`, `{{event_name}}`, `{{job_name}}`, `{{shift_date}}`, `{{shift_time}}`, `{{event_location}}`

**Data Requirements**:
- **Read**: `email_templates` (for event + type), default templates from `EmailTemplateRenderer`
- **Write**: `email_templates` (upsert/delete)

**States**:
- **Default**: Template loaded from built-in defaults, "Using default" badge shown
- **Customized**: Custom template loaded from database, "Customized" badge shown, "Reset to Default" button visible
- **Preview**: Rendered email shown with sample volunteer/event data below the editor

### Page 7: Volunteer List

**Route**: `/events/{event}/volunteers`
**Purpose**: View and manage all volunteers signed up for an event
**Auth required**: Yes (Organizer, Volunteer Admin)

**Layout**:
- **Structure**: Tab content area. Search bar + filter controls at top, volunteer table below.
- **Key sections**: Search + filter bar → Volunteer table (name, email, job, shift, signup date, arrival status)

**Components**:
| Component | Purpose | Data Source | Key Interactions |
|---|---|---|---|
| Search Bar | Search volunteers by name or email | Client-side filter on loaded data | Type to filter |
| Job Filter | Filter by specific job | `volunteer_jobs` for the event | Dropdown select |
| Shift Filter | Filter by specific shift | `shifts` for selected job | Dropdown select |
| Volunteer Table | List all signed-up volunteers | `shift_signups` → `volunteers` + `shifts` + `volunteer_jobs` | Click row to view detail |
| Status Badges | Show arrival and attendance status | `event_arrivals`, `attendance_records` | None (display only) |
| Export Button | Export volunteer list as CSV | N/A | Download CSV |

**User Actions**:
1. **Search**: Type to filter volunteers by name or email
2. **Filter by job/shift**: Narrow the list to a specific job or shift
3. **View volunteer detail**: Click a row to navigate to the volunteer detail page
4. **Export** (Could Have): Download the filtered list as CSV

**Data Requirements**:
- **Read**: shift_signups with volunteers, shifts, volunteer_jobs, event_arrivals, attendance_records
- **Write**: None from this page

**States**:
- **Loading**: Skeleton table rows
- **Empty**: "No volunteers have signed up yet. Share your event page to start recruiting!"
- **Filtered empty**: "No volunteers match your search."
- **Normal**: Table populated with volunteer data and status badges

### Page 8: Volunteer Detail

**Route**: `/events/{event}/volunteers/{volunteer}`
**Purpose**: View a single volunteer's complete information for an event
**Auth required**: Yes (Organizer, Volunteer Admin)

**Layout**:
- **Structure**: Back link → Volunteer header (name, email) → Shift assignments table → Event arrival status → Attendance records → Promotion action (Organizer only)

**Components**:
| Component | Purpose | Data Source | Key Interactions |
|---|---|---|---|
| Volunteer Header | Name and email | `volunteers` table | None |
| Shift Assignments | All shifts this volunteer is signed up for | `shift_signups` → `shifts` → `volunteer_jobs` | None |
| Arrival Status | Whether they've arrived at the event | `event_arrivals` | None (display only) |
| Attendance Records | On-time/late/no-show per shift | `attendance_records` | None (display only) |
| Promote Button | Promote to staff role (Organizer only) | N/A | Opens promotion modal |

**User Actions**:
1. **View details**: See full volunteer profile for this event
2. **Promote** (Organizer, Could Have): Open promotion modal to create a user account

**Data Requirements**:
- **Read**: volunteer, shift_signups, shifts, volunteer_jobs, event_arrivals, attendance_records
- **Write**: None (promotion is via modal → separate action)

**States**:
- **Loading**: Skeleton layout
- **Normal**: All sections populated
- **No attendance data**: "Attendance not yet recorded" for shifts that haven't started
- **Already promoted**: Promote button replaced with "Staff member" badge

### Page 9: Attendance Tracker

**Route**: `/events/{event}/attendance`
**Purpose**: Volunteer Admin marks shift-level attendance (on_time / late / no_show)
**Auth required**: Yes (Volunteer Admin: edit; Organizer: read-only)

**Layout**:
- **Structure**: Tab content area. Shift selector at top → Volunteer roster for selected shift → Attendance action buttons per volunteer

**Components**:
| Component | Purpose | Data Source | Key Interactions |
|---|---|---|---|
| Shift Selector | Choose which shift to track | `shifts` → `volunteer_jobs` | Dropdown or card selector |
| Volunteer Roster | List volunteers for the selected shift | `shift_signups` → `volunteers` (for selected shift) | None |
| Attendance Buttons | Mark on_time / late / no_show per volunteer | `attendance_records` | Click to set/change status |
| Status Summary | Count of on-time / late / no-show / unmarked | Aggregated from `attendance_records` | None (display only) |
| Event Arrival Indicator | Whether volunteer has scanned in at entrance | `event_arrivals` | None (display only, informational) |

**User Actions**:
1. **Select shift**: Choose a shift to view its roster
2. **Mark attendance**: Click on_time, late, or no_show for each volunteer
3. **Change attendance**: Click a different status to correct a previous entry

**Data Requirements**:
- **Read**: shifts (for event), shift_signups (for selected shift), volunteers, attendance_records, event_arrivals
- **Write**: attendance_records (create or update)

**States**:
- **Loading**: Skeleton roster
- **No shifts today**: "No shifts scheduled for today. Select a date or shift to view attendance."
- **All unmarked**: Roster shows all volunteers with no status — default state before shift starts
- **Partially marked**: Some volunteers have status, others pending
- **All marked**: Summary shows complete attendance for the shift
- **Conflict indicator**: Volunteer arrived at event (has event_arrival) but no-show at shift — highlighted

### Page 10: QR Scanner

**Route**: `/scanner`
**Purpose**: Scan volunteer QR tickets at the event entrance (works offline)
**Auth required**: Yes (Entrance Staff, Organizer)

**Layout**:
- **Structure**: Fullscreen, mobile-optimized. Camera viewfinder fills most of the screen. Result panel slides up from bottom after scan.
- **Key sections**: Event selector (top bar) → Camera viewfinder → Scan result panel → Manual lookup link

**Components**:
| Component | Purpose | Data Source | Key Interactions |
|---|---|---|---|
| Event Selector | Choose which event to scan for | Cached events list | Dropdown at top of screen |
| Camera Viewfinder | Live camera feed with scan overlay | Device camera via `jsQR` | Auto-detects QR codes |
| Scan Result Panel | Shows volunteer info after successful scan | JWT decode + IndexedDB cache | Slides up with volunteer details |
| Status Badge | Arrival status — new / already arrived / flagged | `event_arrivals` (IndexedDB) | Color-coded |
| Confirm Arrival Button | Record the volunteer as arrived | Creates `event_arrival` record (IndexedDB → sync) | Tap to confirm |
| Manual Lookup Link | Navigate to name-based search | N/A | Link to /scanner/lookup |
| Sync Status Indicator | Shows online/offline status and pending sync count | Service Worker + IndexedDB | Auto-updates |

**User Actions**:
1. **Select event**: Choose the event being scanned (persists in local storage)
2. **Scan QR**: Point camera at volunteer's QR code. System auto-detects and validates the JWT.
3. **Review result**: See volunteer name, assigned job/shift, and any flags
4. **Confirm arrival**: Tap to record the volunteer as arrived at the event
5. **Handle flags**: Note if volunteer is flagged (no-show history, shift not started, duplicate scan)
6. **Fallback to manual lookup**: Navigate to name search for volunteers without QR

**Data Requirements**:
- **Read** (offline-capable): Pre-downloaded volunteer list (IndexedDB), JWT validation (on-device), previous arrival records (IndexedDB)
- **Write** (offline-capable): event_arrivals queued in IndexedDB, synced to server when online

**States**:
- **Loading**: Camera permission request
- **Camera denied**: "Camera access required for scanning. Please enable camera permissions."
- **Ready**: Camera viewfinder active, awaiting QR code
- **Scan success — new arrival**: Green panel with volunteer info. "Confirm Arrival" button.
- **Scan success — already arrived**: Yellow panel. "Already scanned at [time]."
- **Scan success — flagged**: Orange panel. Flag reason displayed (e.g., "No-show on a previous shift at this event").
- **Scan failure — invalid QR**: Red panel. "Invalid ticket. Try manual lookup."
- **Offline mode**: Sync indicator shows "Offline — arrivals will sync when connected. [N] pending."
- **Sync complete**: Brief toast "All arrivals synced."

### Page 11: Manual Lookup

**Route**: `/scanner/lookup`
**Purpose**: Find and check in volunteers who can't present a QR code
**Auth required**: Yes (Entrance Staff, Organizer)

**Layout**:
- **Structure**: Full-width, mobile-optimized. Search bar at top, results below, action buttons per result.
- **Key sections**: Search bar → Results list → Arrival action

**Components**:
| Component | Purpose | Data Source | Key Interactions |
|---|---|---|---|
| Search Bar | Search by volunteer name | IndexedDB cache (offline) or server | Type to search, debounced |
| Result List | Matching volunteers with job/shift info | Volunteers + shift_signups (cached) | Select a volunteer |
| Volunteer Card | Show volunteer name, email, job, shift | Cached data | Tap to select |
| Confirm Arrival Button | Record arrival via manual lookup | Creates `event_arrival` with method=manual_lookup | Tap to confirm |

**User Actions**:
1. **Search by name**: Type volunteer name, see matching results
2. **Select volunteer**: Tap a matching volunteer card
3. **Confirm arrival**: Mark volunteer as arrived (method: manual_lookup)

**Data Requirements**:
- **Read**: Volunteers for the selected event (from IndexedDB cache)
- **Write**: event_arrivals (method=manual_lookup, queued in IndexedDB)

**States**:
- **Empty**: "Search for a volunteer by name"
- **No results**: "No volunteers found matching '[query]'. Check the spelling or try a different name."
- **Results**: List of matching volunteers with job/shift info
- **Already arrived**: Selected volunteer shows "Already arrived at [time]" — can still re-confirm if needed
- **Confirmed**: Toast notification "Arrival recorded for [name]"

### Page 12: Organization Team Management

**Route**: `/settings/team`
**Purpose**: Manage organization staff accounts and roles
**Auth required**: Yes (Organizer only)

**Layout**:
- **Structure**: Sidebar + main content. Team member table with role badges. Invite form.
- **Key sections**: Team member list → Invite new member form → Pending promotions

**Components**:
| Component | Purpose | Data Source | Key Interactions |
|---|---|---|---|
| Team Member Table | List all organization users with roles | `organization_user` → `users` | Edit role, remove |
| Role Badge | Show user's role | `organization_user.role` | None |
| Invite Form | Add a new team member by email | N/A | Submit: create user + send invite |
| Promoted Volunteers | Recently promoted volunteers with status | `volunteer_promotions` | None (informational) |

**User Actions**:
1. **View team**: See all staff members and their roles
2. **Change role**: Update a member's role (organizer, volunteer_admin, entrance_staff)
3. **Remove member**: Remove a user from the organization
4. **Invite new member**: Send an email invitation to a new staff member
5. **View promotions**: See audit log of volunteer-to-staff promotions

**Data Requirements**:
- **Read**: organizations (current org), organization_user (with users), volunteer_promotions
- **Write**: organization_user (role update, delete), users (create for invite)

**States**:
- **Loading**: Skeleton table
- **Empty**: "Just you so far! Invite team members to help manage events."
- **Normal**: Table populated with members and roles
- **Invite sent**: Toast "Invitation sent to [email]"
- **Remove confirmation**: "Remove [name] from the organization? They will lose access to all events."

## Core User Flows

### Flow 1: Event Setup

**Trigger**: Organizer decides to create a new event
**Persona**: Organizer
**Goal**: Create a fully configured event with jobs and shifts, ready for volunteer signup
**Addresses**: PR-1 (unified workflow)

| Step | User Action | System Response | Page/Component |
|---|---|---|---|
| 1 | Clicks "Create Event" on Dashboard or Events List | Opens create event modal | Dashboard / Events List |
| 2 | Fills in event name, dates, location, description | Validates input, creates event in draft status | Events List > Create Event Modal |
| 3 | Navigates to Jobs & Shifts tab | Shows empty jobs list with "Add Job" prompt | Jobs & Shifts Manager |
| 4 | Clicks "Add Job", enters job name, description, instructions | Creates volunteer_job record | Jobs & Shifts Manager |
| 5 | Clicks "Add Shift" within the job, sets start/end time and capacity | Creates shift record, shows capacity display | Jobs & Shifts Manager |
| 6 | Repeats steps 4-5 for additional jobs and shifts | Additional records created | Jobs & Shifts Manager |
| 7 | Navigates to Overview tab, clicks "Publish" | Changes event status to published, generates public_token if not exists, public page goes live | Event Overview |
| 8 | Copies the public event URL | URL copied to clipboard | Event Overview |

**Success criteria**: Event is published with at least one job containing at least one shift. Public URL is shareable.
**Error paths**: Validation errors on event creation form (missing name, invalid dates). Attempting to publish with no jobs/shifts shows a warning.

### Flow 2: Volunteer Sign-Up

**Trigger**: Volunteer finds or receives the public event link
**Persona**: Volunteer
**Goal**: Sign up for a shift and receive an event ticket
**Addresses**: SP-1 (account friction), PR-1 (unified workflow), FP-1 (affordable QR)

| Step | User Action | System Response | Page/Component |
|---|---|---|---|
| 1 | Opens the public event URL | Loads public event page with event info, jobs, and available shifts | Public Event Page |
| 2 | Browses jobs, expands one to see shifts | Shows shift cards with times and remaining capacity | Public Event Page > Job Accordion |
| 3 | Clicks a shift with available spots | Highlights selected shift, reveals signup form below | Public Event Page > Shift Card |
| 4 | Enters name, email address, and optional phone number | If email exists in volunteers table, pre-fills name; phone is updated if provided | Public Event Page > Signup Form |
| 5 | Clicks "Sign Up" | System: (a) upserts volunteer by email, (b) creates shift_signup, (c) generates ticket with JWT QR, (d) creates magic_link_token, (e) sends confirmation email with ticket link | Public Event Page > Signup Form |
| 6 | Receives confirmation email | Email uses custom template if set by organizer, otherwise default. Contains: event details, shift info, and a magic link to view their QR ticket | Email (off-platform) |
| 7 | Clicks magic link in email | Opens ticket page with QR code, event details, and shift assignments | Volunteer Ticket Page |

**Success criteria**: Volunteer sees confirmation on page, receives email with magic link, can view QR ticket.
**Error paths**: Invalid email format (inline validation). Shift is full (show "Full" badge, disable signup). Email delivery failure (system logs error; volunteer can use manual lookup at entrance).

### Flow 3: Pre-Shift Notifications

**Trigger**: Scheduled job runs, finds shifts starting in 24h or 4h
**Persona**: System → Volunteer
**Goal**: Deliver job-specific information to volunteers before their shift
**Addresses**: PR-1 (unified workflow), PP-3 (coordinator tech support)

| Step | User Action | System Response | Page/Component |
|---|---|---|---|
| 1 | (Automated) Scheduler runs | Finds shift_signups where shift starts in 24h and notification_24h_sent is false | Background job |
| 2 | (None — system-initiated) | Sends notification email with: event name, job name, shift time, job-specific instructions (from volunteer_jobs.instructions) | Email (off-platform) |
| 3 | (None — system-initiated) | Sets notification_24h_sent = true on the shift_signup record | Database update |
| 4 | Volunteer reads email | Sees all info needed for their shift: what to wear, where to park, who to report to | Email (off-platform) |
| 5 | (Automated) 4h reminder follows same pattern | Sends 4h notification, sets notification_4h_sent = true | Background job + Email |

**Success criteria**: Volunteer receives notifications at the configured intervals with job-specific instructions.
**Error paths**: Email delivery failure (logged, no retry for notifications — they're time-sensitive). Job has no instructions (notification still sent but without the instructions section).

### Flow 4: Shift Attendance Verification

**Trigger**: Shift start time arrives, Volunteer Admin begins checking in volunteers
**Persona**: Volunteer Admin
**Goal**: Record which volunteers showed up to their shift and whether they were on time
**Addresses**: PR-2 (event arrival vs shift attendance), PR-3 (no-show detection)

| Step | User Action | System Response | Page/Component |
|---|---|---|---|
| 1 | Opens the Attendance Tracker for the event | Loads shift selector with today's shifts highlighted | Attendance Tracker |
| 2 | Selects the current shift | Loads volunteer roster for that shift with arrival indicators | Attendance Tracker > Shift Selector |
| 3 | As volunteers report to their station, clicks "On Time" for each | Creates attendance_record with status=on_time, recorded_by=current user | Attendance Tracker > Attendance Buttons |
| 4 | After shift start, marks late arrivals as "Late" | Creates attendance_record with status=late | Attendance Tracker > Attendance Buttons |
| 5 | After grace period, marks remaining as "No Show" | Creates attendance_record with status=no_show | Attendance Tracker > Attendance Buttons |
| 6 | Reviews summary | Sees count of on-time / late / no-show for the shift | Attendance Tracker > Status Summary |

**Success criteria**: All volunteers for the shift have an attendance status recorded.
**Error paths**: Volunteer Admin changes a status (system allows correction — updates existing attendance_record). Volunteer arrives after being marked no-show (change to late).

### Flow 5: Entrance QR Scanning

**Trigger**: Event day — Entrance Staff opens the scanner
**Persona**: Entrance Staff
**Goal**: Validate volunteer tickets at the entrance, record arrivals, flag issues
**Addresses**: PP-1 (manual check-in), FP-1 (affordable scanning), PR-1 (unified workflow)

| Step | User Action | System Response | Page/Component |
|---|---|---|---|
| 1 | Opens the Scanner page | Prompts for camera permission, loads event selector | QR Scanner |
| 2 | Selects the current event | Pre-downloads volunteer list and event data to IndexedDB (if online). Derives per-event, per-day HMAC key for JWT validation. | QR Scanner > Event Selector |
| 3 | Points camera at volunteer's QR code | jsQR detects the QR code, decodes the JWT, validates signature with derived HMAC key | QR Scanner > Camera Viewfinder |
| 4 | Reviews scan result | Panel shows: volunteer name, assigned job, shift time, and any flags (no-show on another shift, already scanned, shift not started) | QR Scanner > Scan Result Panel |
| 5 | Taps "Confirm Arrival" | Creates event_arrival record in IndexedDB (method=qr_scan). If online, syncs immediately. If offline, queues for later sync. | QR Scanner > Confirm Button |
| 6 | Continues scanning next volunteer | Scanner returns to viewfinder state, ready for next QR | QR Scanner |
| 7 | (When connectivity returns) | Service Worker syncs queued arrivals to server via POST /api/scanner/sync | Background (Service Worker) |

**Success criteria**: Volunteer is recorded as arrived. Entrance Staff sees any relevant flags. Arrival data eventually reaches the server.
**Error paths**: Invalid JWT (show "Invalid ticket" error, suggest manual lookup). Duplicate scan (show "Already arrived" with timestamp). Camera permission denied (show instructions to enable). Offline mode (all operations continue; sync indicator shows pending count).

### Flow 6: Manual Lookup at Entrance

**Trigger**: Volunteer cannot present QR code (forgot phone, no signal, lost ticket)
**Persona**: Entrance Staff
**Goal**: Find and check in a volunteer by name
**Addresses**: PP-1 (manual check-in), SP-2 (mobile experience)

| Step | User Action | System Response | Page/Component |
|---|---|---|---|
| 1 | Clicks "Manual Lookup" link on Scanner page | Navigates to search interface | Manual Lookup |
| 2 | Types volunteer's name | Searches IndexedDB cache for matching volunteers (works offline) | Manual Lookup > Search Bar |
| 3 | Selects the correct volunteer from results | Shows volunteer card with name, email, job, shift info | Manual Lookup > Volunteer Card |
| 4 | Taps "Confirm Arrival" | Creates event_arrival record with method=manual_lookup | Manual Lookup > Confirm Button |
| 5 | Returns to scanner or continues lookups | "Arrival recorded" toast, search clears for next lookup | Manual Lookup |

**Success criteria**: Volunteer found by name, arrival recorded with method=manual_lookup.
**Error paths**: Name not found (suggest checking spelling; volunteer may not be signed up for this event). Multiple matches (show list, staff selects correct one).

### Flow 7: Volunteer Promotion

**Trigger**: Organizer wants to give a volunteer staff access
**Persona**: Organizer
**Goal**: Create a user account for a volunteer with a temporary password
**Addresses**: Operational need (Could Have feature)

| Step | User Action | System Response | Page/Component |
|---|---|---|---|
| 1 | Opens Volunteer Detail page for the volunteer to promote | Views volunteer info and history | Volunteer Detail |
| 2 | Clicks "Promote to Staff" button | Opens promotion modal with role selector (Volunteer Admin, Entrance Staff) | Volunteer Detail > Promote Modal |
| 3 | Selects a role and confirms | System: (a) creates a user account with the volunteer's name/email, (b) generates a temporary password, (c) sets must_change_password=true, (d) creates organization_user with selected role, (e) sets volunteer.user_id to the new user, (f) creates volunteer_promotions audit record, (g) sends email with temporary password | Volunteer Detail > Promote Modal |
| 4 | (Promoted volunteer) Receives email with temp password | Email contains: login link, temporary password, instruction to change password | Email (off-platform) |
| 5 | (Promoted volunteer) Logs in with temp password | RequirePasswordChange middleware redirects to /change-password | Login → Change Password |
| 6 | (Promoted volunteer) Sets a new password | Updates password, sets must_change_password=false, redirects to dashboard | Change Password |

**Success criteria**: Volunteer has a user account, is linked to the organization with the correct role, and has changed their temporary password.
**Error paths**: Volunteer email already has a user account (show message, link existing user). Promoted user forgets temp password (standard password reset flow).

## Tech Stack

| Layer | Choice | Rationale |
|---|---|---|
| Framework | Laravel 12 | Already selected; mature ecosystem, excellent for CRUD + auth + queues |
| Frontend | Livewire 4 + Flux UI | Already in starter kit; server-rendered components, minimal JS needed for most pages |
| Styling | Tailwind CSS 4 | Included with Flux UI; utility-first, mobile-responsive by default |
| Auth (staff) | Laravel Fortify | Already in composer.json; handles login, password reset, forced password change |
| Auth (volunteers) | Magic links (SHA-256 hashed DB tokens) | Zero-friction; no account needed. Tokens are hashed before storage, have expiration. |
| QR generation | `chillerlan/php-qrcode` | Pure PHP, no external service dependency. Generates QR as SVG or PNG. |
| QR scanning | `jsQR` (client-side JS) | Lightweight JS library. Runs on-device, no server round-trip needed for decoding. |
| JWT | `firebase/php-jwt` | Server-side JWT creation (HS256). Client-side validation via JS (HMAC key derived per-event, per-day from APP_KEY). |
| Offline | Service Worker + IndexedDB | Pre-download volunteer list, queue arrivals. Standard PWA pattern for offline-first. |
| Database | SQLite (dev) / MySQL/PostgreSQL (prod) | Laravel default. SQLite for easy local dev; MySQL or PostgreSQL for production. |
| Testing | Pest | Already in dev dependencies. PHP testing framework designed for Laravel. |
| Queue | Laravel Queue (database driver) | For sending emails (confirmation, notifications, promotion). Database driver to avoid Redis dependency. |

### QR / JWT Architecture

**Ticket creation** (server-side):
1. When a volunteer signs up, generate a JWT containing: `volunteer_id`, `event_id`, `shift_ids[]`, `issued_at`
2. Sign the JWT with HS256 using a secret derived from: `hash_hmac('sha256', $event->id . ':' . $date, config('app.key'))`
3. Render the JWT string as a QR code using `chillerlan/php-qrcode`
4. Store the JWT in the `tickets` table

**Ticket validation** (client-side, offline-capable):
1. Camera captures QR code → `jsQR` decodes the QR to get the JWT string
2. JS code validates the JWT signature using the pre-downloaded HMAC key for today's event
3. Extract `volunteer_id` and `event_id` from the JWT payload
4. Look up volunteer in the pre-downloaded IndexedDB cache for display info
5. Check IndexedDB for existing `event_arrival` record (duplicate detection)
6. If valid and not duplicate: show volunteer info and "Confirm Arrival" button

**Per-event, per-period key derivation**: The HMAC secret used for JWT signing is derived from the event ID, a date period (rotating at 4:00 AM local time, not midnight), and the application key. This means:
- The IndexedDB cache contains the derived key for the current period only — not the master APP_KEY
- If IndexedDB data is exfiltrated, the attacker gets a key that only validates the current period's tickets for one event
- The next period's key will be different, limiting the window of exposure
- The scanner validates against both the current period's key and the previous period's key before rejecting, ensuring events spanning the 4 AM boundary work seamlessly

**Offline sync architecture**:
1. Before the event, Entrance Staff opens the Scanner page while online
2. Service Worker caches the scanner app shell (HTML, JS, CSS)
3. A fetch to `GET /api/scanner/data/{event}` downloads: volunteer list, shift assignments, and the derived HMAC key for today
4. Data is stored in IndexedDB
5. During scanning, arrivals are written to an IndexedDB "outbox" table
6. When connectivity is detected (navigator.onLine event or periodic check), Service Worker POSTs the outbox to `POST /api/scanner/sync`
7. Server processes arrivals, handles duplicates (idempotent upsert by volunteer_id + event_id), and returns confirmation
8. Synced records are removed from the outbox

**Edge cases**:
- **Duplicate scan**: Client-side check in IndexedDB before confirming. Server-side upsert handles race conditions (first-write-wins for timestamp).
- **Stale volunteer data**: Volunteer list is re-downloaded when the Scanner page is opened while online. A "Refresh Data" button allows manual re-download. If a JWT validates successfully but the volunteer is not found in the IndexedDB cache (signed up after download), the scanner displays the JWT payload fields (volunteer_id, shift_ids) with a "Not in cached list — data may be stale" warning. The operator can still confirm the arrival or refresh data first.
- **Volunteer cancellation while offline**: JWTs cannot be revoked without a server call. If a volunteer cancels after the data is cached, their ticket will still validate offline. The server reconciles on sync — the arrival record is created but flagged if the signup no longer exists.
- **Sync conflicts**: Server treats arrivals as idempotent (first-write-wins) — if a volunteer was already marked arrived (e.g., by manual lookup at another entrance), the sync response indicates "already_recorded" so the device can update its local state. This also enables cross-device duplicate detection after sync.
- **Multi-device scanning**: Multiple Entrance Staff can scan at different entrances. Each device maintains its own IndexedDB. Server-side first-write-wins upsert handles convergence. After sync, the server's response includes the full arrival list, allowing devices to update their local cache for better cross-device duplicate detection.
- **Key rotation at midnight**: The per-event, per-day HMAC key uses 4:00 AM local time as the rotation boundary (not midnight) to avoid mid-event key changes. Additionally, the scanner validates against both the current period's key and the previous period's key before rejecting a ticket, providing a graceful transition window.

### Middleware

- **RequirePasswordChange**: Checks `auth()->user()->must_change_password`. If true, redirects to `/change-password` for all routes except `/change-password`, `/logout`. Applied after auth middleware.
- **ResolveOrganization**: Resolves the current organization from the authenticated user's memberships and binds it to the container as `currentOrganization`. Applied to all authenticated routes after auth middleware. Single-org users auto-resolve; multi-org users select via session.

## Design Principles

- **Volunteer-first simplicity**: Every volunteer interaction (signup, ticket access) must work without an account, without instructions, and on a phone. If a volunteer needs help, the design has failed.
- **Offline by default for event-day operations**: The scanner must work without internet. Design for offline first, treat connectivity as a bonus.
- **Separate concerns, separate entities**: Event arrival (entrance) and shift attendance (station) are different events recorded by different people. The data model reflects this distinction.
- **Progressive disclosure**: Simple pages by default, complexity revealed on demand. Job accordion expands to show shifts. Volunteer row expands to detail.
- **Affordable by design**: No features that require paid external services (no Twilio, no cloud scanning APIs). Self-hostable if desired.

## Architectural Guardrails

### PHP Enums

All status, role, and method fields use PHP 8.3+ backed enums with `string` backing types. Enums are cast on Eloquent models via `$casts` — no string comparisons anywhere in the codebase.

| Enum Class | Backing Type | Values | Used On |
|---|---|---|---|
| `App\Enums\EventStatus` | `string` | `draft`, `published`, `archived` | `events.status` |
| `App\Enums\StaffRole` | `string` | `organizer`, `volunteer_admin`, `entrance_staff` | `organization_user.role` |
| `App\Enums\AttendanceStatus` | `string` | `on_time`, `late`, `no_show` | `attendance_records.status` |
| `App\Enums\ArrivalMethod` | `string` | `qr_scan`, `manual_lookup` | `event_arrivals.method` |

**Rules**:
- Models cast these columns: `protected $casts = ['status' => EventStatus::class]`
- Migrations use `->string()` columns (not native DB enums) for portability
- All conditionals use enum comparisons: `$event->status === EventStatus::Published`
- Enum cases use PascalCase (`Draft`, `Published`, `Archived`)

### Multi-Tenancy Scoping

Organizations are the tenant boundary. Every query touching events, jobs, shifts, volunteers, tickets, and arrivals must be scoped to the current organization. The scoping strategy uses **middleware + container binding** so that Actions receive the org context via injection, and models stay unaware of tenant filtering.

**Flow**:
1. A `ResolveOrganization` middleware runs on all authenticated routes
2. The middleware resolves the current organization from the authenticated user's memberships (single-org users auto-resolve; multi-org users select via session)
3. The middleware binds the `Organization` instance to the container: `app()->instance('currentOrganization', $org)`
4. Actions receive the current organization via constructor injection (type-hinted `Organization` resolved from the container binding)
5. Actions scope all queries through the organization relationship: `$org->events()->where(...)` rather than `Event::where('organization_id', ...)`

**Rules**:
- Never use `Event::all()`, `Volunteer::where(...)`, or any unscoped model query in authenticated contexts
- Always traverse from the organization: `$org->events()`, `$event->volunteerJobs()`, etc.
- Public routes (event page, ticket page) resolve the organization from the `public_token` or `magic_token` on the resource — no middleware needed
- The `ScannerController` API endpoints receive the event ID in the request; the controller verifies the authenticated user has access to that event's organization before returning data

### Authorization Strategy

Authorization uses **Laravel Policies per model**, called from Actions. Livewire components never contain authorization logic — they delegate to the Action, which calls the Policy. Route-level middleware handles broad role gating.

**Layers**:

| Layer | Mechanism | Purpose |
|---|---|---|
| Route middleware | `role:organizer`, `role:organizer,volunteer_admin` | Broad role gating — prevents unauthorized route access |
| Model Policies | `EventPolicy`, `ShiftPolicy`, `VolunteerPolicy`, `AttendanceRecordPolicy` | Fine-grained permission checks (e.g., "can this user manage this event?") |
| Actions | Call `$this->authorize()` or `Gate::authorize()` before mutating | Single enforcement point for business operations |
| Livewire components | No auth logic — call Actions, display results | Pure adapter — no policy or gate calls |

**Policies**:

| Policy | Model | Key Methods |
|---|---|---|
| `EventPolicy` | `Event` | `viewAny`, `view`, `create`, `update`, `publish`, `archive` |
| `VolunteerJobPolicy` | `VolunteerJob` | `viewAny`, `create`, `update`, `delete` |
| `ShiftPolicy` | `Shift` | `viewAny`, `create`, `update`, `delete` |
| `VolunteerPolicy` | `Volunteer` | `viewAny`, `view`, `promote` |
| `AttendanceRecordPolicy` | `AttendanceRecord` | `record` (Volunteer Admin + Organizer only) |
| `EventArrivalPolicy` | `EventArrival` | `record` (Entrance Staff + Organizer only) |
| `OrganizationPolicy` | `Organization` | `manageTeam` (Organizer only) |

**Rules**:
- "Volunteer Admin sees only assigned events" is enforced by scoping queries in the Policy's `viewAny` method — not by hiding UI elements
- Entrance Staff access to scanner is gated by route middleware (`role:entrance_staff,organizer`)
- Organizer has implicit access to everything within their organization — Policies check org membership, not role, then fall through to role-specific checks

### Action Orchestration

Actions are single-responsibility classes with one `execute()` method. When an operation requires multiple steps, the primary Action **orchestrates** other Actions via constructor injection. Side effects (emails, notifications) are dispatched as queued jobs/notifications — never inline.

**`SignUpVolunteer` orchestration** (the most complex flow):

```
SignUpVolunteer::execute(string $name, string $email, Shift $shift, ?string $phone = null)
├── Upserts volunteer record by email (updates phone if provided)
├── Creates shift_signup (with capacity check)
├── Calls GenerateTicket::execute(volunteer, event)
│   └── Creates JWT, generates QR, stores ticket record
├── Calls GenerateMagicLink::execute(volunteer)
│   └── Creates hashed magic link token
└── Dispatches SignupConfirmation notification (queued)
    └── Email contains event details, shift info, magic link to ticket
```

**Rules**:
- Actions receive dependencies via constructor injection: `__construct(GenerateTicket $generateTicket, GenerateMagicLink $generateMagicLink)`
- Actions never dispatch emails directly — they dispatch queued Notifications or queue Jobs
- `RecordArrival` and `RecordAttendance` are independent Actions (different actors, different entities) — they do not orchestrate each other
- `PromoteVolunteer` orchestrates user creation, role assignment, and dispatches the `VolunteerPromoted` notification

### Validation Strategy

Validation lives at the adapter boundary — in Livewire components for form submissions, in Form Requests for API endpoints. Actions trust their inputs (they receive typed parameters from validated adapters).

**Livewire components**: Use `#[Validate]` attributes on public properties for declarative validation. The `$this->validate()` call in the component method triggers validation before calling the Action.

```php
// Example: Public Event Page signup form
#[Validate('required|string|max:255')]
public string $name = '';

#[Validate('required|email')]
public string $email = '';
```

**API endpoints**: The `ScannerController` uses Form Requests (`SyncArrivalsRequest`) for input validation. No manual `$request->validate()` calls in controller methods.

**Rules**:
- No validation logic inside Actions — they receive pre-validated, typed parameters
- Livewire components use `#[Validate]` attributes (not `$rules` arrays) for consistency
- Form Requests are used only for the `ScannerController` API endpoints
- Domain constraints (shift capacity, duplicate signup) are enforced in Actions as business logic, not as validation rules

### Domain Exceptions

Business rule violations throw domain-specific exceptions from Actions. Livewire components catch these and translate them to user-facing messages (flash messages or inline errors). This keeps error semantics in the domain layer.

| Exception | Thrown By | When |
|---|---|---|
| `ShiftFullException` | `SignUpVolunteer` | Shift capacity reached at time of signup |
| `DuplicateSignupException` | `SignUpVolunteer` | Volunteer already signed up for this shift |
| `EventNotPublishedException` | `SignUpVolunteer` | Signup attempted on a draft or archived event |
| `InvalidMagicLinkException` | `TicketPage` (Livewire) resolves token | Magic link token expired, already used, or not found |
| `InvalidTicketException` | `RecordArrival` | JWT validation failure (invalid signature, expired, malformed) |
| `DuplicateArrivalException` | `RecordArrival` | Volunteer already recorded as arrived at this event |

All domain exceptions extend a base `App\Exceptions\DomainException` class (which extends `\DomainException`). This allows a single catch block in Livewire components:

```php
try {
    $this->signUpVolunteer->execute($this->name, $this->email, $this->shift);
} catch (DomainException $e) {
    $this->addError('signup', $e->getMessage());
}
```

**Rules**:
- Domain exceptions carry user-safe messages — they're designed to be shown to users
- Actions throw domain exceptions; they never return error arrays or false
- Livewire components catch `DomainException` and translate to UI feedback
- Unexpected errors (DB failures, etc.) are not caught by components — they bubble up to Laravel's exception handler

### Data Transfer Objects (DTOs)

When Actions accept more than 3 parameters or need to pass structured data between layers, use simple DTOs (readonly classes with public properties). This prevents the "array with magic keys" anti-pattern.

| DTO | Used By | Fields |
|---|---|---|
| `SignupData` | `SignUpVolunteer` | `name`, `email`, `shift_id` |
| `ArrivalData` | `RecordArrival`, `SyncArrivals` | `volunteer_id`, `event_id`, `method` (ArrivalMethod enum), `scanned_at`, `scanned_by` |
| `AttendanceData` | `RecordAttendance` | `shift_signup_id`, `status` (AttendanceStatus enum), `recorded_by` |
| `PromotionData` | `PromoteVolunteer` | `volunteer_id`, `role` (StaffRole enum), `promoted_by` |

DTOs are readonly classes constructed via named arguments:

```php
readonly class SignupData
{
    public function __construct(
        public string $name,
        public string $email,
        public int $shiftId,
    ) {}
}
```

**Rules**:
- DTOs are immutable (readonly classes)
- DTOs carry no behavior — only data
- Livewire components create DTOs from validated form data and pass them to Actions
- DTOs may reference enums as field types for type safety

## Implementation Sequence

### Phase 1: Foundation [Complete]
- Database schema (all 15 entities: migrations, models, relationships, enum casts)
- PHP Enums (`EventStatus`, `StaffRole`, `AttendanceStatus`, `ArrivalMethod`)
- Auth scaffolding (Fortify configuration, RequirePasswordChange middleware)
- Multi-tenancy middleware (`ResolveOrganization` — org context binding)
- Authorization Policies (`EventPolicy`, `OrganizationPolicy`, etc.)
- Domain exception base class and initial exceptions
- Organization and team management (CRUD, role pivot)
- Basic layout with sidebar navigation and role-based visibility

### Phase 2: Event Setup & Volunteer Signup [Complete]
- Event CRUD (create, edit, publish, archive)
- Volunteer Jobs CRUD with instructions field
- Shifts CRUD with capacity
- Event Overview page with metrics
- Public event page with volunteer signup form
- Volunteer model, shift signup logic with capacity enforcement

### Phase 3: Tickets & QR Scanner [Complete]
- QR ticket generation (JWT creation with `chillerlan/php-qrcode`)
- Magic link token generation and Volunteer Ticket Page
- Confirmation email with ticket link
- Scanner page with camera integration (`jsQR`)
- Client-side JWT validation with per-event, per-day HMAC key derivation
- Service Worker for offline caching
- IndexedDB for volunteer data cache and arrival queue
- Sync mechanism (outbox -> POST /admin/scanner/api/events/{eventId}/sync)
- Manual Lookup page

### Phase 4: Attendance & Admin [Complete]
- Attendance Tracker (shift-level on_time/late/no_show)
- Pre-shift notification scheduler (24h, 4h)
- Notification email template with job-specific instructions
- Notification tracking flags on shift_signups

### Phase 5: Dashboard & Volunteer Management [Complete]
- Dashboard with role-adaptive metrics
- Volunteer List page (search, filter)
- Volunteer Detail page

### Phase 6: Polish & Testing [Complete]
- Event cloning (Could Have)
- Volunteer promotion flow (Could Have)
- CSV export (Could Have)
- Dashboard analytics — no-show rates, attendance trends (Could Have)
- End-to-end Pest tests for all core flows
- Mobile responsiveness refinement
- Error handling and edge case coverage

## File & Directory Structure

```
app/
├── Actions/
│   ├── CreateEvent.php
│   ├── PublishEvent.php
│   ├── ArchiveEvent.php
│   ├── SignUpVolunteer.php          — orchestrates GenerateTicket + GenerateMagicLink
│   ├── GenerateTicket.php
│   ├── GenerateMagicLink.php
│   ├── RecordArrival.php
│   ├── RecordAttendance.php
│   ├── PromoteVolunteer.php
│   └── SyncArrivals.php
├── DTOs/
│   ├── SignupData.php
│   ├── ArrivalData.php
│   ├── AttendanceData.php
│   └── PromotionData.php
├── Enums/
│   ├── EventStatus.php              — Draft, Published, Archived
│   ├── StaffRole.php                — Organizer, VolunteerAdmin, EntranceStaff
│   ├── AttendanceStatus.php         — OnTime, Late, NoShow
│   └── ArrivalMethod.php            — QrScan, ManualLookup
├── Exceptions/
│   ├── DomainException.php          — base class for all domain exceptions
│   ├── ShiftFullException.php
│   ├── DuplicateSignupException.php
│   ├── EventNotPublishedException.php
│   ├── InvalidMagicLinkException.php
│   ├── InvalidTicketException.php
│   └── DuplicateArrivalException.php
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       └── ScannerController.php
│   ├── Middleware/
│   │   ├── RequirePasswordChange.php
│   │   └── ResolveOrganization.php  — binds current org to container
│   └── Requests/
│       └── SyncArrivalsRequest.php  — Form Request for scanner sync API
├── Livewire/
│   ├── Dashboard.php
│   ├── Events/
│   │   ├── EventList.php
│   │   ├── EventOverview.php
│   │   ├── JobsShiftsManager.php
│   │   ├── VolunteerList.php
│   │   ├── VolunteerDetail.php
│   │   └── AttendanceTracker.php
│   ├── Public/
│   │   ├── EventPage.php
│   │   └── TicketPage.php
│   ├── Scanner/
│   │   ├── QrScanner.php
│   │   └── ManualLookup.php
│   └── Settings/
│       └── TeamManagement.php
├── Models/
│   ├── Organization.php
│   ├── Event.php
│   ├── VolunteerJob.php
│   ├── Shift.php
│   ├── Volunteer.php
│   ├── ShiftSignup.php
│   ├── AttendanceRecord.php
│   ├── Ticket.php
│   ├── EventArrival.php
│   ├── MagicLinkToken.php
│   └── VolunteerPromotion.php
├── Notifications/
│   ├── SignupConfirmation.php
│   ├── PreShiftReminder.php
│   └── VolunteerPromoted.php
├── Policies/
│   ├── EventPolicy.php
│   ├── VolunteerJobPolicy.php
│   ├── ShiftPolicy.php
│   ├── VolunteerPolicy.php
│   ├── AttendanceRecordPolicy.php
│   ├── EventArrivalPolicy.php
│   └── OrganizationPolicy.php
└── Jobs/
    └── SendPreShiftNotifications.php

database/migrations/
├── create_organizations_table.php
├── create_organization_user_table.php
├── create_events_table.php
├── create_volunteer_jobs_table.php
├── create_shifts_table.php
├── create_volunteers_table.php
├── create_shift_signups_table.php
├── create_attendance_records_table.php
├── create_tickets_table.php
├── create_event_arrivals_table.php
├── create_magic_link_tokens_table.php
├── create_volunteer_promotions_table.php
└── add_must_change_password_to_users_table.php

resources/
├── views/
│   └── livewire/
│       ├── dashboard.blade.php
│       ├── events/
│       │   ├── event-list.blade.php
│       │   ├── event-overview.blade.php
│       │   ├── jobs-shifts-manager.blade.php
│       │   ├── volunteer-list.blade.php
│       │   ├── volunteer-detail.blade.php
│       │   └── attendance-tracker.blade.php
│       ├── public/
│       │   ├── event-page.blade.php
│       │   └── ticket-page.blade.php
│       ├── scanner/
│       │   ├── qr-scanner.blade.php
│       │   └── manual-lookup.blade.php
│       └── settings/
│           └── team-management.blade.php
├── js/
│   ├── scanner.js          — jsQR integration, camera handling, JWT validation
│   └── service-worker.js   — Offline caching, IndexedDB management, sync
└── css/
    └── app.css             — Tailwind imports + custom scanner styles

routes/
├── web.php                 — Public routes + authenticated routes
└── api.php                 — Scanner sync endpoint

public/
└── sw.js                   — Service Worker entry point (compiled from resources/js/)
```

## Open Questions

- [ ] Should event cloning copy volunteer signups or just the event/job/shift structure?
- [ ] What's the ticket expiration policy for magic links? (Suggestion: 30 days or until event ends, whichever is later)
- [ ] Should the system support multiple signups per volunteer per event (different shifts), or limit to one shift per volunteer per event?
- [ ] Should there be a "check-in kiosk" mode where volunteers scan their own QR at an unattended station?
