# Milestone: m13-polish — Communication & Polish

**Features:** 19 independent features shipped as individual PRs
**Issues:** #87, #81, #86, #85, #84, #83, #80, #79, #78, #77, #76, #74, #67, #66, #64, #55, #48, #47, #46
**Dependencies:** m8-project-scoped (complete), m9-roles (complete), m11-scanner (complete)

## Plan
- **Status:** complete
- **Architecture:** Default Laravel (existing — no DDD). 30+ models, but logic stays in single-responsibility Actions, Livewire components enforce policies, Pest feature tests.
- **Action method:** `execute()` (existing convention)
- **Gate summary:** 19 features across 5 implementation phases. ~14 migrations, ~4 new models, ~3 new enums/enum updates, ~18 new/updated Actions, ~3 new Jobs, ~6 new/updated Livewire components, ~6 updated Livewire components, ~8 new Activity events, ~2 new Notifications, ~180+ tests.

## Implement
- **Status:** in_progress
- **Iteration:** 1
- **Tasks:**
  - [x] Phase A — #67 Fix MATCH AGAINST email search (regex sanitization, 3 new tests)
  - [x] Phase A — #64 Activity log extension (migration, 4 events, 4 listener handlers, 6 tests)
  - [x] Phase A — #48 Scanner touch targets (min-h-12 buttons, larger tap areas)
  - [x] Phase A — #80 Rate limiting (30min lockout, German messages, ScannerLockout event, 5 tests)
  - [x] Phase B — #81 Email templates (German defaults, new placeholders, EventUpdated type) — 5 new enum cases, German defaults for all 9 types, 18 new tests
  - [x] Phase B — #55 Event-level email templates — all 9 types editable, German UI labels, German preview sample data, label() method on enum, 6 new tests
  - [ ] Phase C — #47 Project email settings
  - [ ] Phase C — #46 Event settings area
  - [ ] Phase C — #74 Hint texts
  - [ ] Phase C — #86 Optional shift times
  - [ ] Phase C — #85 Shift cancellation rework
  - [ ] Phase D — #84 Re-publish notification
  - [ ] Phase D — #83 Project website
  - [ ] Phase D — #79 Data deletion
  - [ ] Phase D — #78 Clone with date offset
  - [ ] Phase D — #77 Gear summary
  - [ ] Phase D — #66 Promote rework
  - [ ] Phase E — #87 Announcements
  - [ ] Phase E — #76 Dashboard rework

---

## Implementation Phases (Internal Dependencies)

Features are grouped into 5 phases based on internal M13 dependencies:

### Phase A — Zero-Dependency Foundations (can all start in parallel)
| # | Issue | Feature | Internal Deps |
|---|-------|---------|---------------|
| 1 | #67 | Fix MATCH AGAINST email handling | none |
| 2 | #64 | Activity log extension — membership changes | none |
| 3 | #48 | Scanner touch target improvements | none |
| 4 | #80 | Rate limiting | none |

### Phase B — Email & Template Layer
| # | Issue | Feature | Internal Deps |
|---|-------|---------|---------------|
| 5 | #81 | Email templates — German defaults, new placeholders, EventUpdated type | none (but shipped before #84, #87) |
| 6 | #55 | Event-level email templates | #81 (needs EventUpdated enum case) |

### Phase C — Settings & Configuration Layer
| # | Issue | Feature | Internal Deps |
|---|-------|---------|---------------|
| 7 | #47 | Project settings — email sender config | none |
| 8 | #46 | Event settings — dedicated settings area | none |
| 9 | #74 | Hint texts — configurable per project | none |
| 10 | #86 | Shifts — optional times with custom display text | none |
| 11 | #85 | Shift cancellation rework — project-level settings + digest | #46 (event settings tab), #47 (organizer notification email) |

### Phase D — Major Features (depend on B/C being stable)
| # | Issue | Feature | Internal Deps |
|---|-------|---------|---------------|
| 12 | #84 | Re-publish notification | #46 (event settings tab), #81 (needs event_updated template type) |
| 13 | #83 | Project website — content editor + public listing | none |
| 14 | #79 | Data deletion — cascade rules | none |
| 15 | #78 | Clone project or event with date offset | none |
| 16 | #77 | Gear summary — project-level read-only overview | none |
| 17 | #66 | Promote rework — scope to scanner assignment | none |

### Phase E — Dashboard Rework (depends on most features being complete)
| # | Issue | Feature | Internal Deps |
|---|-------|---------|---------------|
| 18 | #87 | Announcements — manual organizer emails to filtered groups | #81 (templates), #47 (sender config) |
| 19 | #76 | Dashboard rework — project tiles, quick actions, reminders | #77 (gear count), #85 (cancellation count), #87 (announcement action) |

---

## Feature Specifications

---

### Feature 1: Fix MATCH AGAINST Email Handling (#67)

**PR scope:** Bug fix — `@` in email breaks MATCH AGAINST boolean mode.

#### 1.1 Database Changes
None.

#### 1.2 Backend

**Model change — `Volunteer::scopeSearch()`:**
Replace the existing sanitization:
```php
// Before
$term = str_replace(['+', '-', '*', '~', '<', '>', '(', ')', '"'], '', $search);

// After
$term = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $search);
```
This strips all non-letter, non-number, non-whitespace characters (including `@`, `.`, etc.), which is safe for MATCH AGAINST boolean mode.

#### 1.3 Frontend
None.

#### 1.4 Tests (~5 tests)
- `tests/Feature/Models/VolunteerSearchTest.php` (new):
  - search by email with `@` returns results
  - search by partial email domain returns results
  - search by name still works
  - search with special characters (`+`, `-`, `*`, `~`) does not throw
  - short search (<3 chars) falls back to LIKE

#### 1.5 Dependencies
None.

---

### Feature 2: Activity Log Extension — Membership Changes (#64)

**PR scope:** Log member invited/role changed/removed/left at Project and Event level.

#### 2.1 Database Changes

**Migration: add_project_id_to_activity_logs_table**
```
$table->foreignId('project_id')->nullable()->after('organization_id')->constrained()->nullOnDelete();
$table->index('project_id');
```

**Model update — `ActivityLog`:**
Add `project_id` to `$fillable`. Add `project()` BelongsTo. Add `scopeForProject()`.

#### 2.2 Backend

**New Activity Events:**
- `ProjectMemberAdded` — dispatched from `AddProjectMember`
- `ProjectMemberRemoved` — dispatched from `RemoveProjectMember`
- `ProjectMemberRoleChanged` — dispatched when role changes (new action or within `AddProjectMember` if re-adding with different role)
- `ScannerAssigneeAdded` — dispatched from scanner assignee operations
- `ScannerAssigneeRemoved` — dispatched from scanner assignee operations

All events follow the existing pattern: plain PHP classes with public constructor properties, dispatched via `Event::dispatch()`.

**RecordActivityListener updates:**
Add handlers for each new event. All log to the `Member` category. Include `project_id` where applicable.

**Action updates:**
- `AddProjectMember::execute()` — dispatch `ProjectMemberAdded`
- `RemoveProjectMember::execute()` — dispatch `ProjectMemberRemoved`

#### 2.3 Frontend
None (existing `ActivityFeed` component already renders by category).

#### 2.4 Tests (~8 tests)
- `tests/Feature/Activity/MembershipActivityTest.php` (new):
  - adding project member logs activity with project_id
  - removing project member logs activity
  - scanner assignee added logs activity
  - scanner assignee removed logs activity
  - activity log filterable by project_id
  - all membership activities use Member category

#### 2.5 Dependencies
None.

---

### Feature 3: Scanner Touch Target Improvements (#48)

**PR scope:** Mobile UX — increase touch target sizes for check-in actions.

#### 3.1 Database Changes
None.

#### 3.2 Backend
None.

#### 3.3 Frontend

**Blade/CSS changes in scanner templates:**
- `resources/views/livewire/scanner-app.blade.php` — increase button sizes
- `resources/js/scanner/alpine-scanner.ts` — no logic changes
- Scanner check-in buttons: min-height 48px (per WCAG 2.5.8 Target Size), ideally 56-64px
- Full-width check-in buttons in volunteer/guest result cards
- Per-shift check-in rows: entire row tappable, not just small button
- Gear pickup toggles: larger toggle area
- Result status badges: larger with clear iconography

#### 3.4 Tests (~3 tests)
- Manual testing checklist (documented in PR):
  - Check-in button is easily tappable on 375px viewport
  - Shift rows are full-width tappable
  - Gear pickup toggles meet 48px minimum

#### 3.5 Dependencies
None.

---

### Feature 4: Rate Limiting (#80)

**PR scope:** Rate limits on magic links, email verification, scanner auth.

#### 4.1 Database Changes
None (rate limiting uses Laravel's built-in cache-based rate limiter).

#### 4.2 Backend

**Configure rate limiters in `AppServiceProvider::boot()` or `bootstrap/app.php`:**

```php
// SS-4 resolution: compound key — per-email AND per-IP secondary limiter
RateLimiter::for('magic-link-request', fn (Request $request) => [
    Limit::perHour(3)->by($request->input('email')),
    Limit::perMinute(10)->by($request->ip()),
]);

RateLimiter::for('email-verification-resend', fn (Request $request) => [
    Limit::perHour(3)->by($request->input('email')),
    Limit::perMinute(10)->by($request->ip()),
]);

RateLimiter::for('qr-code-resend', fn (Request $request) =>
    Limit::perMinutes(5, 1)->by($request->input('email'))
);

RateLimiter::for('scanner-link-resend', fn (Request $request) =>
    Limit::perMinutes(5, 1)->by('scanner-link:'.$request->route('scannerId'))
);

RateLimiter::for('scanner-auth-attempt', fn (Request $request) =>
    Limit::perMinutes(30, 5)->by('scanner-auth:'.$request->route('scannerToken'))
);
```

**Livewire component updates:**
- `ScannerAuth::authenticate()` — check rate limit, 5 failed attempts → 30min lockout, activity log on lockout
- Public components that send emails — apply rate limiter via `RateLimiter::tooManyAttempts()` + user feedback

**Activity log for scanner lockout:**
- New `ScannerLockout` activity event → logs under `System` category
- Manual reset: add `resetScannerLockout()` method to `ScannerManagement` component

**User feedback:** German message: "Zu viele Versuche. Bitte warte X Minuten."

#### 4.3 Frontend
- Add error message display in `ScannerAuth` template for lockout state
- Add rate limit feedback in public forms (email verification resend, etc.)

#### 4.4 Tests (~10 tests)
- `tests/Feature/RateLimiting/MagicLinkRateLimitTest.php`:
  - 3 requests succeed, 4th is blocked
  - rate limit resets after window
- `tests/Feature/RateLimiting/ScannerAuthRateLimitTest.php`:
  - 5 failed attempts trigger lockout
  - lockout lasts 30 minutes
  - activity log entry created on lockout
  - manual reset clears lockout
- `tests/Feature/RateLimiting/EmailVerificationRateLimitTest.php`:
  - resend limited to 3/hour

#### 4.5 Dependencies
None.

---

### Feature 5: Email Templates — German Defaults (#81)

**PR scope:** German default texts for all system emails, new placeholders, EventUpdated template type.

#### 5.1 Database Changes
None (defaults are in `EmailTemplateRenderer::DEFAULTS` constant).

#### 5.2 Backend

**Enum update — `EmailTemplateType`:**
Add cases:
```php
case StaffInvitation = 'staff_invitation';
case VolunteerPromoted = 'volunteer_promoted';
case AddedToOrganization = 'added_to_organization';
case EventAnnouncement = 'event_announcement';
case EventUpdated = 'event_updated';
```

**Service update — `EmailTemplateRenderer`:**

New placeholders added to `availablePlaceholders()`:
- `vorname`, `nachname`, `telefon` (German name variants)
- `gear_zusammenfassung` (gear summary)
- `portal_link` (volunteer portal URL)
- `kontakt_email` (contact email)
- `project_name` (project name)
- `organizer_note` (free-text note from organizer)

Replace all English defaults in `DEFAULTS` with German:

```php
'signup_confirmation' => [
    'subject' => 'Anmeldebestätigung für {{event_name}}',
    'body' => "Hallo {{vorname}}!\n\nDu bist für **{{event_name}}** angemeldet.\n\n**Deine Schichten:**\n{{shifts_summary}}\n{{event_location}}\nDu erhältst dein Ticket mit QR-Code über einen separaten Link.\n\nVielen Dank für deine Unterstützung!",
],
// ... all 9 types with full German templates
```

**Notification updates:**
All 9 notification classes (`SignupConfirmation`, `EmailVerification`, `PreShiftReminder`, `StaffInvitation`, `VolunteerPromoted`, `AddedToOrganization`, `EventAnnouncementNotification`) updated to:
- Pass new placeholders (vorname, nachname, telefon, portal_link, kontakt_email, project_name) to renderer
- Use German greeting: "Hallo {{vorname}}!" instead of "Hello {{volunteer_name}}!"

#### 5.3 Frontend
None (templates render server-side in emails).

#### 5.4 Tests (~12 tests)
- `tests/Feature/Services/EmailTemplateRendererTest.php` (update existing):
  - all 9 template types have German defaults
  - new placeholders are correctly replaced
  - EventUpdated template renders with organizer_note
- `tests/Feature/Notifications/` (update existing notification tests):
  - each notification passes new placeholders
  - German subject lines render correctly
  - custom event-level templates override defaults

#### 5.5 Dependencies
None (but should be merged before #84 and #87 which depend on EventUpdated type).

---

### Feature 6: Event-Level Email Templates (#55)

**PR scope:** Email templates configured at event level, override system defaults.

#### 6.1 Database Changes
None — `email_templates` table already has `event_id` FK. The existing `EmailTemplateRenderer::render()` already queries `event->emailTemplates()` and falls back to defaults. This feature is about the UI and ensuring all 9 types are editable.

#### 6.2 Backend

**Update `EmailTemplateEditor` component:**
Currently handles the 4 original template types. Extend to support all 9 types from `EmailTemplateType` enum.

**Update `EmailTemplateRenderer`:**
Ensure the `render()` method checks for event-level custom templates for all 9 types.

#### 6.3 Frontend

**Update `EventShow` or new "Email Settings" section in Event Settings (#46):**
If #46 (Event Settings) is done first, the email template editor moves into the Settings tab under an "Email" section. Otherwise, the existing `events/{eventId}/emails` route continues to work.

**Template editor UI improvements:**
- Show all 9 template types as editable cards
- Preview pane with placeholder substitution
- "Reset to default" button per template
- Show available placeholders per type

#### 6.4 Tests (~8 tests)
- `tests/Feature/Livewire/Events/EmailTemplateEditorTest.php` (update):
  - can edit all 9 template types
  - custom template overrides system default in rendered email
  - reset to default deletes custom template
  - non-organizer cannot edit templates

#### 6.5 Dependencies
#81 (needs all 9 enum cases to exist), #46 (email template editor moves into Event Settings tab).

---

### Feature 7: Project Settings — Email Sender Config (#47)

**PR scope:** Project-level email sender name and contact email.

#### 7.1 Database Changes

**Migration: add_email_settings_to_projects_table**
```
$table->string('sender_name')->nullable()->after('description');
$table->string('contact_email')->nullable()->after('sender_name');
```

**Model update — `Project`:**
Add `sender_name`, `contact_email` to `$fillable`.

#### 7.2 Backend

**Update `UpdateProject` action:**
Accept `senderName` and `contactEmail` params.

**Update `UsesOrganizationMailer` trait (or create `UsesProjectMailer`):**
When sending volunteer-facing notifications, check `$project->sender_name` for the from display name, and `$project->contact_email` as reply-to. Fall back to org-level SMTP settings.

**Notification updates:**
All volunteer-facing notifications that currently use `UsesOrganizationMailer` should additionally check project-level sender settings. The `kontakt_email` placeholder resolves to `$project->contact_email ?? $org->smtp_from_address`.

#### 7.3 Frontend

**Update `ProjectShow` component:**
Add sender_name and contact_email fields in the edit form.

Alternatively, if #46 (Event Settings) establishes a "Settings tab" pattern, create a `ProjectSettings` component or add a settings section to `ProjectShow`.

#### 7.4 Tests (~6 tests)
- `tests/Feature/Actions/UpdateProjectTest.php` (update):
  - updates sender_name and contact_email
- `tests/Feature/Notifications/ProjectSenderTest.php` (new):
  - email uses project sender_name as from name
  - email includes project contact_email as reply-to
  - falls back to org settings when project settings empty

#### 7.5 Dependencies
None.

---

### Feature 8: Event Settings — Dedicated Settings Area (#46)

**PR scope:** Separate event edit form into a read-only Overview tab and a Settings tab.

#### 8.1 Database Changes
None.

#### 8.2 Backend

**New Livewire component — `Events\EventSettings`:**
Full-page component at route `events/{eventId}/settings`. Organizer only.

Sections:
- **General:** name, description, location, title image, dates
- **Signup:** deadline, phone_required toggle
- **Attendance:** grace period
- **Email:** organizer notification email, link to email template editor

This extracts the editing logic from `EventShow` into a dedicated component.

**Update `EventShow`:**
Remove edit form. Make it a read-only overview with link to Settings.

#### 8.3 Frontend

**New route:**
```php
Route::livewire('events/{eventId}/settings', EventSettings::class)->name('events.settings');
```

**New Blade view — `livewire/events/event-settings.blade.php`:**
Tabbed sections using Flux UI tabs. Each section is a collapsible/expandable card.

**Update `livewire/events/event-show.blade.php`:**
Remove edit form, add "Settings" link/button for Organizers.

#### 8.4 Tests (~8 tests)
- `tests/Feature/Livewire/Events/EventSettingsTest.php` (new):
  - organizer can view settings
  - non-organizer cannot access settings (403)
  - can update general settings
  - can update signup settings
  - can update attendance settings
  - validates required fields
  - redirects after save
  - settings page shows current values

#### 8.5 Dependencies
None.

---

### Feature 9: Hint Texts — Configurable Per Project (#74)

**PR scope:** Configurable hint texts at org and project level.

#### 9.1 Database Changes

**New model + migration: create_hint_texts_table**
```
$table->id();
$table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
$table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
$table->string('location');          // e.g. 'signup_email', 'signup_last_name', 'portal_top_banner'
$table->string('label');             // display label for admin
$table->text('text');                // the hint content
$table->boolean('enabled')->default(true);
$table->timestamps();

$table->unique(['organization_id', 'location']);
$table->unique(['project_id', 'location']);

// JD-3 resolution: CHECK constraint ensures exactly one scope is set
DB::statement('ALTER TABLE hint_texts ADD CONSTRAINT hint_texts_scope_check CHECK (
    (organization_id IS NOT NULL AND project_id IS NULL) OR
    (organization_id IS NULL AND project_id IS NOT NULL)
)');
```

#### 9.2 Backend

**New model — `HintText`:**
```php
$fillable = ['organization_id', 'project_id', 'location', 'label', 'text', 'enabled'];
```
Relationships: `organization()`, `project()`.
Scope: `scopeForLocation()`.

Validation: exactly one of `organization_id` or `project_id` must be set (same pattern as `CustomRegistrationField`).

**New enum — `HintLocation`:**
```php
enum HintLocation: string
{
    // Signup flow
    case SignupEmail = 'signup_email';
    case SignupLastName = 'signup_last_name';
    case SignupPhone = 'signup_phone';
    case SignupSummary = 'signup_summary';
    case SignupConfirmation = 'signup_confirmation';
    // Volunteer portal
    case PortalTopBanner = 'portal_top_banner';
    case PortalGearSection = 'portal_gear_section';
    case PortalShiftsSection = 'portal_shifts_section';
    // Scanner
    case ScannerWelcome = 'scanner_welcome';
}
```

**New service — `HintTextResolver`:**
```php
public function resolve(HintLocation $location, Project $project): ?string
```
Lookup order: project-level → org-level → null (not shown).

**New Livewire component — `Projects\HintTextSettings`:**
Full-page at `projects/{projectId}/hint-texts`. Organizer only.
Lists all `HintLocation` cases, shows current text (project override or org default), toggle enabled, edit text.

**New Livewire component — `Settings\OrganizationHintTexts`:**
For org-level defaults. Accessible from org settings.

**Update consuming components:**
- `EventSignup` — inject hint resolver, pass hints to Blade
- `VolunteerPortal` — inject hint resolver, pass hints to Blade
- `ScannerApp` — pass hint from `ProjectScanner->hint_text` (already exists) or override from `HintTextResolver`

#### 9.3 Frontend
- Hint text display: small muted text blocks in signup flow, portal, scanner
- Admin UI: editable text areas per hint location, preview

#### 9.4 Tests (~10 tests)
- `tests/Feature/Services/HintTextResolverTest.php`:
  - project hint overrides org hint
  - disabled hint returns null
  - missing hint returns null
  - org-level fallback works
- `tests/Feature/Livewire/Projects/HintTextSettingsTest.php`:
  - organizer can view and edit hints
  - non-organizer cannot access

#### 9.5 Dependencies
None.

---

### Feature 10: Shifts — Optional Times with Custom Display Text (#86)

**PR scope:** Make shift start/end times optional, add custom display text overrides.

#### 10.1 Database Changes

**Migration: make_shift_times_nullable_and_add_text_overrides**
```
// Make start_at/end_at nullable (they are currently required datetime)
$table->dateTime('starts_at')->nullable()->change();
$table->dateTime('ends_at')->nullable()->change();

// Add date column (always required)
$table->date('shift_date')->after('volunteer_job_id');

// Add text overrides
$table->string('start_text_override')->nullable()->after('ends_at');
$table->string('end_text_override')->nullable()->after('start_text_override');
```

**Data migration:** Populate `shift_date` from existing `starts_at` values for all current rows.

**Model update — `Shift`:**
- Add `shift_date`, `start_text_override`, `end_text_override` to `$fillable` and `$casts`
- New method: `hasDefinedTimes(): bool` — returns true if `starts_at` is not null (DA-4 resolution: centralized null check)
- New method: `displayTimeRange(): string` — returns custom text if set, else formatted time. Custom text is required when times are absent (JD-4 resolution: no "Ganztägig" fallback — validation enforces custom text).
- Update `attendanceStatusAt()` to handle null `starts_at`
- **Implementation note (DA-4):** During implementation, grep for every `->starts_at->` and `->ends_at->` chain across the codebase. Each must be null-guarded or use `hasDefinedTimes()`. Expand tests to cover notification rendering and scanner data serialization for timeless shifts.
- Update `isFull()`, `spotsRemaining()` (no change needed — independent of time)

#### 10.2 Backend

**Update `CreateShift` action:**
Accept optional `startsAt`, `endsAt`, required `shiftDate`, optional `startTextOverride`, `endTextOverride`.
Validation: if no `startsAt` and no `startTextOverride` for Date-only config → require custom text.

**Update `UpdateShift` action:**
Same parameter changes.

**Update `SendPreShiftReminders`:**
If shift has no `starts_at`, use 03:00 on `shift_date` as the reminder reference time.

**Update scanner data sorting:**
Shifts without times sort first (before timed shifts on the same date).

**Update overlap detection:**
Shifts without times skip overlap checks.

**Update `ExportVolunteersCsv`:**
Use `displayTimeRange()` for shift time display in export.

#### 10.3 Frontend

**Update `JobsAndShiftsManager`:**
- Shift form: date picker (required), optional start/end time pickers, optional text override fields
- Display: use `displayTimeRange()` in shift cards

**Update `VolunteerPortal`:**
- Display shift dates with `displayTimeRange()`

**Update `EventSignup` wizard:**
- Shift selection cards show `displayTimeRange()`

#### 10.4 Tests (~12 tests)
- `tests/Feature/Actions/CreateShiftTest.php` (update):
  - creates shift with date only (no times)
  - creates shift with custom text override
  - creates shift with date + start only
  - validates custom text required when no times
- `tests/Feature/Models/ShiftTest.php`:
  - `displayTimeRange()` returns custom text when set
  - `displayTimeRange()` returns formatted time range when times set
  - `hasDefinedTimes()` returns false when starts_at is null
- `tests/Feature/Actions/SendPreShiftRemindersTest.php` (update):
  - reminder uses 03:00 for shifts without start time

#### 10.5 Dependencies
None.

---

### Feature 11: Shift Cancellation Rework (#85)

**PR scope:** Move cancellation settings to project level, add organizer digest.

#### 11.1 Database Changes

**Migration: add_cancellation_settings_to_projects_table**
```
$table->boolean('cancellation_enabled')->default(false)->after('contact_email');
$table->unsignedInteger('cancellation_cutoff_hours')->nullable()->after('cancellation_enabled');
```

**Migration: drop_cancellation_cutoff_hours_from_events**
```
$table->dropColumn('cancellation_cutoff_hours');
```

**Note (DA-2 resolution):** The event-level `cancellation_cutoff_hours` column is removed. Project is the single source of truth for cancellation settings. No per-event override. Update `Event` model, `EventShow`/`EventSettings`, `CancelShiftSignup`, `VolunteerPortal`, and all tests that reference `$event->cancellation_cutoff_hours`.

**Migration: add_notification_email_to_events_table**
```
$table->string('notification_email')->nullable()->after('visibility');
```

**Model updates:**
- `Project`: add `cancellation_enabled`, `cancellation_cutoff_hours` to `$fillable` and `$casts`
- `Event`: add `notification_email` to `$fillable`. Remove `cancellation_cutoff_hours` from `$fillable`/`$casts`. Remove `isCancellationAllowed()` method (moves to Project).

#### 11.2 Backend

**Update `CancelShiftSignup` action:**
Check project-level cancellation settings. No event-level fallback.

```php
$project = $event->project;
if (!$project->cancellation_enabled) {
    throw new DomainException('Cancellation is not enabled for this project.');
}
```

**New Job — `SendCancellationDigest`:**
- Runs every 6 hours (scheduled in `routes/console.php`)
- Queries `shift_signups` cancelled in the last 6 hours, grouped by project
- Sends digest to `event.notification_email` (or project contact_email fallback)
- Only sends if there are cancellations

**New Notification — `CancellationDigestNotification`:**
- Contains: list of volunteer name, event, shift, job, time
- Recipients: organizer notification email addresses
- Uses `UsesOrganizationMailer`

**Update `VolunteerPortal`:**
- Check project-level cancellation settings for button visibility

#### 11.3 Frontend

**Update `ProjectShow` or new `ProjectSettings` component:**
- Cancellation toggle (default off)
- Cutoff hours field (shown when enabled)
- Validation: cancellation_cutoff_hours required when enabled

**Update `EventSettings` (#46) or `EventShow`:**
- Add notification_email field under Attendance or Email section

#### 11.4 Tests (~10 tests)
- `tests/Feature/Actions/CancelShiftSignupTest.php` (update):
  - respects project-level cancellation_enabled
  - respects project-level cutoff hours
  - throws when project cancellation disabled
- `tests/Feature/Jobs/SendCancellationDigestTest.php` (new):
  - sends digest when cancellations exist
  - does not send when no cancellations
  - groups cancellations by project
  - uses event notification_email
  - falls back to project contact_email

#### 11.5 Dependencies
#47 (needs project contact_email for fallback).

---

### Feature 12: Re-publish Notification (#84)

**PR scope:** Notify volunteers when an event is re-published from Draft.

#### 12.1 Database Changes

**Migration: add_was_previously_published_to_events**
```
$table->boolean('was_previously_published')->default(false)->after('status');
```

**Model update — `Event`:**
Add `was_previously_published` to `$fillable` and `$casts`.

#### 12.2 Backend

**Update `PublishEvent` action:**
After publishing, check `was_previously_published`. If true, dispatch a new `RepublishEvent` job/action. On first publish, set `was_previously_published = true`.

```php
// In PublishEvent::execute()
$wasPublished = $event->was_previously_published;
$event->update([
    'status' => EventStatus::PublishedOpen,
    'was_previously_published' => true,
]);

if ($wasPublished) {
    SendRepublishNotificationJob::dispatch($event, $organizerNote);
}
```

**New Job — `SendRepublishNotificationJob`:**
- Recipients: all volunteers with active signups in this event
- Uses `event_updated` template type from #81
- Passes `organizer_note` placeholder

**Update `EventShow` (or EventSettings) component:**
When re-publishing from Draft, show modal with optional organizer note text field.

**New Notification — `EventRepublishedNotification`:**
- Implements `ShouldQueue` (SS-3 resolution: prevents fan-out timeout for large events)
- Uses `EmailTemplateRenderer` with `EventUpdated` type
- Passes: volunteer_name, event_name, organizer_note, portal_link

#### 12.3 Frontend

**Update event publish flow UI:**
- Modal appears on re-publish with text area for organizer note
- "Erneut veröffentlichen" button

#### 12.4 Tests (~8 tests)
- `tests/Feature/Actions/PublishEventTest.php` (update):
  - first publish sets was_previously_published
  - re-publish sends notifications to active volunteers
  - re-publish includes organizer note
  - first publish does NOT send re-publish notification
- `tests/Feature/Notifications/EventRepublishedNotificationTest.php`:
  - uses event_updated template type
  - includes organizer_note placeholder

#### 12.5 Dependencies
#81 (needs `EventUpdated` enum case and German template).

---

### Feature 13: Project Website — Content Editor & Public Listing (#83)

**PR scope:** Public-facing project website with content editor.

#### 13.1 Database Changes

**Migration: add_website_fields_to_projects_table**
```
$table->text('website_description')->nullable()->after('description');
$table->string('website_contact_info')->nullable()->after('website_description');
$table->string('website_token')->nullable()->unique()->after('public_token');
$table->boolean('website_published')->default(false)->after('website_token');
```

Note: The project already has a `public_token` used for the `/p/{publicToken}` route. The existing `ProjectWebsite` component already exists. This feature enriches it with editable content.

**Model update — `Project`:**
Add new fields to `$fillable`.

#### 13.2 Backend

**Update `ProjectWebsite` component:**
Currently a minimal component. Enhance to:
- Show `website_description` (rendered as Markdown)
- Show contact info
- Show published events: Published Open (with signup CTA), Published Closed (with label)
- Hide Draft events, remove Archived events
- Preview mode for organizers (show unpublished content with preview banner)

**New Livewire component — `Projects\ProjectWebsiteEditor`:**
Full-page at `projects/{projectId}/website`. Organizer only.
- Rich text / Markdown editor for description
- Contact info field
- Title image upload (reuse `HasTitleImage`)
- Publish toggle
- Preview button

#### 13.3 Frontend

**New route:**
```php
Route::livewire('projects/{projectId}/website-editor', ProjectWebsiteEditor::class)->name('projects.website-editor');
```

**Update `livewire/public/project-website.blade.php`:**
- Rich layout with title image hero
- Markdown-rendered description
- Event cards grid: Published Open with "Anmelden" CTA, Published Closed with "Registrierung geschlossen" label
- Contact info section

**ProjectWebsiteEditor Blade view:**
- Form with text areas, image upload, toggle
- Live preview panel

#### 13.4 Tests (~8 tests)
- `tests/Feature/Livewire/Projects/ProjectWebsiteEditorTest.php`:
  - organizer can edit website content
  - non-organizer cannot access
  - can publish/unpublish website
  - validates fields
- `tests/Feature/Livewire/Public/ProjectWebsiteTest.php` (update):
  - published events visible, draft hidden, archived hidden
  - published_open shows signup CTA
  - published_closed shows closed label
  - unpublished website returns 404

#### 13.5 Dependencies
None.

---

### Feature 14: Data Deletion — Cascade Rules (#79)

**PR scope:** Proper cascade deletion for Projects and Events with safety checks.

#### 14.1 Database Changes

**Migration: add_deletion_requested_at_to_projects_and_events**
```
// projects
$table->timestamp('deletion_requested_at')->nullable();

// events
$table->timestamp('deletion_requested_at')->nullable();
```

**Note (DA-1 resolution):** Do NOT use Laravel's `SoftDeletes` trait. Using `SoftDeletes` adds a global scope to every query, silently breaking 30+ call-sites. Instead, use `deletion_requested_at` as a manual flag. UI queries use `scopeActive()` to exclude pending-deletion records. The purge job hard-deletes rows after 30 days.

#### 14.2 Backend

**Model updates:**
- `Project`: add `deletion_requested_at` to `$fillable` and `$casts` (datetime). Add `scopeActive()` that filters `whereNull('deletion_requested_at')`. Add `isPendingDeletion(): bool`.
- `Event`: same pattern — `deletion_requested_at`, `scopeActive()`, `isPendingDeletion()`.

**Update queries:** Add `->active()` scope to UI-facing queries (dashboard, sidebar, public pages). Internal queries (purge job, admin restore) query without the scope. No global scope — explicit opt-in.

**Update `DeleteProject` action → `RequestProjectDeletion`:**
- Require password confirmation (passed as param, verified in action)
- Set `deletion_requested_at = now()`
- 30-day grace period before permanent deletion
- Record does NOT get deleted — remains visible with "pending deletion" badge

**New Action — `RestoreProject` / `RestoreEvent`:**
- Sets `deletion_requested_at = null` (within 30-day window)

**New Action — `RequestEventDeletion`:**
- Published events cannot be deleted directly (must archive first)
- Password confirmation required
- Sets `deletion_requested_at = now()`

**New Action — `PermanentlyDeleteProject`:**
- Called by scheduled job after 30 days
- Cascades: hard-delete all events, jobs, shifts, signups, volunteers (if no other project), attendance, arrivals, tickets, gear, announcements, custom fields, scanners, guest lists

**New Action — `PermanentlyDeleteEvent`:**
- Called by scheduled job after 30 days
- Check each volunteer: if volunteer has signups in OTHER events of the project, keep volunteer; else delete volunteer entirely

**New Command — `app:purge-pending-deletions`:**
- Scheduled daily
- Finds projects/events where `deletion_requested_at < now()->subDays(30)`
- Calls permanent delete actions

**Update `ProjectPolicy`:** Add `delete()` method. Only org Organizer.

**Update `EventPolicy`:** Add `delete()` method. Only project Organizer. Cannot delete published events.

#### 14.3 Frontend

**Update `ProjectShow`:**
- Delete button with password confirmation modal
- Warning message about 30-day grace period
- "Restore" button for soft-deleted projects (within grace period)

**New or updated event delete flow:**
- Archive required before delete for published events
- Password confirmation dialog
- Warning: "Dieses Event wird in 30 Tagen endgültig gelöscht."

#### 14.4 Tests (~14 tests)
- `tests/Feature/Actions/RequestProjectDeletionTest.php`:
  - soft deletes project
  - requires password confirmation
  - sets deletion_requested_at
- `tests/Feature/Actions/DeleteEventTest.php`:
  - cannot delete published event
  - soft deletes archived event
  - keeps volunteer with other event signups
  - deletes volunteer with only this event's signups
  - requires password confirmation
- `tests/Feature/Actions/PermanentlyDeleteProjectTest.php`:
  - cascades all related data
  - only runs after 30 days
- `tests/Feature/Commands/PurgeSoftDeletedTest.php`:
  - purges after 30 days
  - does not purge before 30 days

#### 14.5 Dependencies
None.

---

### Feature 15: Clone Project or Event with Date Offset (#78)

**PR scope:** Clone entire project with structure, clone event with date offset.

#### 15.1 Database Changes
None.

#### 15.2 Backend

**New Action — `CloneProject`:**
```php
public function execute(Project $project, ?int $dateOffsetDays = null): Project
```
Copies: events (via `CloneEvent`), jobs, shifts, gear items, custom fields, email templates, scanners (without assignees), hint texts.
Does NOT copy: volunteers, signups, guests, announcements, attendance.
Date offset: if provided, shifts all dates (event starts_at/ends_at, shift starts_at/ends_at/shift_date, scanner starts_at/ends_at) by N days.
Always creates Draft events.

**Update `CloneEvent` action:**
Add optional `targetProjectId` and `dateOffsetDays` params.
```php
public function execute(Event $event, ?int $targetProjectId = null, ?int $dateOffsetDays = null): Event
```
When `targetProjectId` is set, clone event into that project (cross-project clone).
When `dateOffsetDays` is set, offset all dates.

**New Activity Events:**
- `ProjectCloned` — dispatched from `CloneProject`

**RecordActivityListener:**
Add handler for `ProjectCloned`.

#### 15.3 Frontend

**Update `ProjectShow` or Dashboard:**
- "Projekt duplizieren" button → modal with optional date offset (days)

**Update `EventShow`:**
- "Event duplizieren" button already exists
- Add modal with: target project dropdown (default: same project), date offset field
- Clone creates Draft, redirects to new event

#### 15.4 Tests (~10 tests)
- `tests/Feature/Actions/CloneProjectTest.php` (new):
  - clones all events as Draft
  - clones jobs and shifts
  - clones gear items
  - clones custom fields
  - clones email templates
  - does NOT clone volunteers/signups
  - applies date offset to all dates
  - clones scanners without assignees
- `tests/Feature/Actions/CloneEventTest.php` (update):
  - clone into different project
  - date offset applies correctly

#### 15.5 Dependencies
None.

---

### Feature 16: Gear Summary — Project-Level Read-Only Overview (#77)

**PR scope:** Read-only gear overview at project level with stats and export.

#### 16.1 Database Changes
None.

#### 16.2 Backend

**New Action — `GenerateGearSummary`:**
```php
public function execute(Project $project): array
```
Returns per-gear-item stats:
- `pending`: assigned but not picked up
- `picked_up`: has pickup record
- `not_available`: declined/not available state
- Total quantities for quantity-type items

**New Action — `ExportGearSummaryCsv`:**
Returns `LazyCollection` of gear summary rows for CSV export.

**New Livewire component — `Projects\GearSummary`:**
Full-page at `projects/{projectId}/gear-summary`. Organizer + VolunteerAdmin. Uses `WithPagination` (SS-5 resolution).
- Per-item stats cards (aggregate queries, no pagination needed)
- Filterable detail table with pagination (volunteer names, item, status)
- Filter by item, event, status
- Missing gear report (items not yet picked up)
- "Not available" summary for reorder
- CSV export button

#### 16.3 Frontend

**New route:**
```php
Route::livewire('projects/{projectId}/gear-summary', GearSummary::class)->name('projects.gear-summary');
```

**Blade view:**
- Stats cards: total items, pending pickup, picked up, not available
- Filterable table with gear items, volunteer names, status
- Export CSV button

#### 16.4 Tests (~8 tests)
- `tests/Feature/Actions/GenerateGearSummaryTest.php`:
  - counts pending correctly
  - counts picked up correctly
  - counts not available correctly
  - handles quantity-type items
- `tests/Feature/Livewire/Projects/GearSummaryTest.php`:
  - organizer can view
  - non-organizer cannot access
  - filter by item works
  - CSV export downloads

#### 16.5 Dependencies
None.

---

### Feature 17: Promote Rework — Scope to Scanner Assignment (#66)

**PR scope:** Rework volunteer promotion to scope to scanner assignments.

#### 17.1 Database Changes
None (uses existing `project_scanner_assignees` table).

#### 17.2 Backend

**Update `PromoteVolunteer` action:**
Two paths:
1. **Promote to VA:** Select a VA scanner → add volunteer's email/user to assignee list → send scanner link. Does NOT add to org users.
2. **Promote to Organizer:** Create user if needed → add to project_user pivot as Organizer → add to org if not present.

**Revoking VA access:**
Remove from scanner assignee list. Volunteer retains volunteer record.

**Edge cases:**
- No VA scanner exists → return error prompting to create one
- Already assigned to selected scanner → show error
- Existing user already in org → skip org attachment

**Update `ScannerManagement`:**
Show promoted VAs in assignee list. Allow removal (revoke).

#### 17.3 Frontend

**Update promote dialog in `VolunteerDetail` or `VolunteerList`:**
- Promote to VA: dropdown to select VA scanner → confirm → assignee added
- Promote to Organizer: confirm → user created/attached
- Show "No VA scanner configured" warning with link to scanner management

#### 17.4 Tests (~10 tests)
- `tests/Feature/Actions/PromoteVolunteerTest.php` (update):
  - promote to VA adds scanner assignee
  - promote to VA does not add to org users
  - promote to Organizer adds to project_user
  - no VA scanner returns error
  - already assigned returns error
  - revoking removes from scanner assignees
  - promote to Organizer creates user if needed

#### 17.5 Dependencies
None.

---

### Feature 18: Announcements — Manual Organizer Emails (#87)

**PR scope:** Project-scoped announcements with filters and scheduling.

#### 18.1 Database Changes

**Migration: create_announcements_table**
```
Schema::create('announcements', function (Blueprint $table) {
    $table->id();
    $table->foreignId('project_id')->constrained()->cascadeOnDelete();
    $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
    $table->foreignId('job_id')->nullable()->constrained('volunteer_jobs')->nullOnDelete();
    $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
    $table->string('subject');
    $table->text('body');
    $table->dateTime('send_at')->nullable();       // null = immediate
    $table->dateTime('sent_at')->nullable();
    $table->foreignId('created_by')->constrained('users');
    $table->unsignedInteger('recipient_count')->default(0);
    $table->timestamps();

    $table->index(['project_id', 'sent_at']);
});
```

**Migration: create_announcement_templates_table**
```
Schema::create('announcement_templates', function (Blueprint $table) {
    $table->id();
    $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->string('subject');
    $table->text('body');
    $table->timestamps();
});
```

**Note (DA-3 resolution):** The old Phase 1 `event_announcements` system is removed entirely. This PR includes:
- Drop `event_announcements` table (migration)
- Delete `EventAnnouncement` model
- Delete `SendEventAnnouncement` action
- Delete `EventAnnouncements` Livewire component + Blade view
- Delete `EventAnnouncementNotification`
- Remove old routes from `web.php`
- Redirect any old URLs to the new `projects/{projectId}/announcements` route
The new project-scoped `announcements` table fully supersedes the old system.

#### 18.2 Backend

**New model — `Announcement`:**
```php
$fillable = ['project_id', 'event_id', 'job_id', 'shift_id', 'subject', 'body', 'send_at', 'sent_at', 'created_by', 'recipient_count'];
$casts = ['send_at' => 'datetime', 'sent_at' => 'datetime'];
```
Relationships: `project()`, `event()`, `job()`, `shift()`, `creator()`.

**New model — `AnnouncementTemplate`:**
```php
$fillable = ['organization_id', 'name', 'subject', 'body'];
```
Relationships: `organization()`.

**New Action — `SendAnnouncement`:**
```php
public function execute(Announcement $announcement): void
```
- Resolves recipients based on filters (project → event → job → shift)
- Sends to verified volunteers with active signups matching the filter chain
- Updates `sent_at` and `recipient_count`
- Dispatches activity event

**New Action — `CreateAnnouncement`:**
```php
public function execute(Project $project, array $data, User $creator): Announcement
```
- Creates announcement record
- If `send_at` is null → immediate: dispatch `SendAnnouncementJob`
- If `send_at` is future → scheduled: `SendAnnouncementJob` dispatched with delay

**New Action — `CreateAnnouncementTemplate` / `UpdateAnnouncementTemplate` / `DeleteAnnouncementTemplate`**

**New Job — `SendAnnouncementJob`:**
- Implements `ShouldQueue`
- Calls `SendAnnouncement` action
- Handles delay for scheduled announcements

**New Notification — `AnnouncementNotification`:**
- Implements `ShouldQueue` (SS-1 resolution: framework dispatches individual mail jobs, preventing burst timeout)
- Uses project sender settings (from #47)
- Free-text subject + body (no template type lookup — direct content)

**New Livewire component — `Projects\AnnouncementComposer`:**
Full-page at `projects/{projectId}/announcements`. Organizer only.
- Filter chain: Event dropdown → Job dropdown (filtered by event) → Shift dropdown (filtered by job)
- Content mode: free-text OR select from AnnouncementTemplate
- Timing: immediate or scheduled (date+time picker)
- Recipient count preview (updates as filters change)
- Send confirmation dialog
- History list with status (sent, scheduled, pending)

**New Livewire component — `Settings\AnnouncementTemplates` (or within org settings):**
CRUD for AnnouncementTemplate at org level.

#### 18.3 Frontend

**New routes:**
```php
Route::livewire('projects/{projectId}/announcements', AnnouncementComposer::class)->name('projects.announcements');
```

**Blade views:**
- Composer form with cascading filter dropdowns
- Template selector (loads subject + body from template)
- Schedule date/time picker
- Recipient count badge
- History table

#### 18.4 Tests (~14 tests)
- `tests/Feature/Actions/CreateAnnouncementTest.php`:
  - creates immediate announcement
  - creates scheduled announcement
  - dispatches job for immediate
  - dispatches delayed job for scheduled
- `tests/Feature/Actions/SendAnnouncementTest.php`:
  - sends to correct recipients with project filter only
  - filters by event
  - filters by event + job
  - filters by event + job + shift
  - updates recipient_count and sent_at
  - skips unverified volunteers
  - skips cancelled signups
- `tests/Feature/Livewire/Projects/AnnouncementComposerTest.php`:
  - organizer can access
  - non-organizer cannot access
  - recipient count updates on filter change

#### 18.5 Dependencies
#81 (for consistent email patterns), #47 (for project sender config).

---

### Feature 19: Dashboard Rework — Project Tiles, Quick Actions (#76)

**PR scope:** Redesigned organizer dashboard with project tiles and smart reminders.

#### 19.1 Database Changes
None.

#### 19.2 Backend

**Update `Dashboard` component — major rework:**

Replace current flat metrics with project-tile-based layout.

**New computed properties (SS-2 resolution: batch aggregates with GROUP BY project_id):**
- `projects()` — all accessible projects. Metrics loaded via batched aggregate queries (one query per metric type with `GROUP BY project_id`), not per-project subqueries.
- `nextUpcomingEvent()` — single nearest event across all projects
- `globalVolunteerSearch()` — search across all project volunteers
- `staffOverview()` — entry staff + VA counts per project (single GROUP BY query)
- `smartReminders()` — array of warning objects:
  - Scanner not set up for project with upcoming events
  - Unconfirmed signups (pending email verification)
  - Shifts needing volunteers (capacity not met)
  - New cancellations since last login

**Per-project tile data (computed):**
- Status badges (draft/published/closed counts)
- Metrics: upcoming events count, total volunteers, shifts needing volunteers
- No-show rate
- Attendance breakdown (on_time/late/no_show/unmarked)
- Gear outstanding count (from #77)
- Quick actions: new event, copy signup link, duplicate event, scanner link, send announcement

**New Livewire components (nested):**
- `Dashboard\ProjectTile` — Blade component (no Livewire — just props)
- `Dashboard\SmartReminders` — Blade component showing warnings
- `Dashboard\StaffOverview` — Blade component

**Filter support:**
- Time filter: upcoming / past / all
- Project filter: dropdown

#### 19.3 Frontend

**Updated `livewire/dashboard.blade.php`:**
- Top bar: next upcoming event card, "Neues Projekt" button
- Global volunteer search bar (wire:model.live.debounce)
- Staff overview widget
- Smart reminders/warnings section (yellow/orange badges)
- Project tiles grid:
  - Each tile: project name, status badges, key metrics
  - Quick action buttons row
  - Gear outstanding badge (if #77 is done)
  - New cancellations badge (if #85 is done)
- Filter bar: time range, project selector

#### 19.4 Tests (~12 tests)
- `tests/Feature/Livewire/DashboardTest.php` (major update):
  - displays project tiles for accessible projects
  - shows next upcoming event
  - global volunteer search returns results
  - smart reminders: shows "scanner not set up" warning
  - smart reminders: shows "shifts needing volunteers" warning
  - project tile shows correct volunteer count
  - project tile shows correct no-show rate
  - filters by time range
  - filters by project
  - non-organizer sees limited dashboard
  - quick action links work

#### 19.5 Dependencies
#77 (gear outstanding count), #85 (cancellation count for reminders), #87 (announcement quick action).

---

## Authorization Model Summary

All features follow existing patterns:

| Feature | Policy Method | Allowed Roles |
|---------|---------------|---------------|
| Hint text settings | `ProjectPolicy::update` | Project Organizer |
| Org hint texts | Org Organizer check | Org Organizer |
| Event settings | `EventPolicy::update` | Project Organizer |
| Announcements | `ProjectPolicy::update` | Project Organizer |
| Announcement templates | Org Organizer check | Org Organizer |
| Gear summary | `ProjectPolicy::view` | Project Organizer + VA (view), Organizer (export) |
| Project website editor | `ProjectPolicy::update` | Project Organizer |
| Data deletion | `ProjectPolicy::delete` / `EventPolicy::delete` | Org Organizer (project), Project Organizer (event) |
| Clone project | `ProjectPolicy::create` | Org Organizer |
| Clone event (cross-project) | `ProjectPolicy::update` on target | Project Organizer |
| Rate limiting | N/A (infrastructure) | N/A |
| Scanner lockout reset | `ProjectPolicy::manageScanners` | Project Organizer |
| Promote rework | `ProjectPolicy::update` | Project Organizer |

---

## Queue & Events Summary

### New Jobs
| Job | Queue | Timeout | Tries | Idempotent? |
|---|---|---|---|---|
| `SendAnnouncementJob` | mail | 60s | 3 | Yes (checks sent_at) |
| `SendRepublishNotificationJob` | mail | 30s | 3 | Yes (one-shot) |
| `SendCancellationDigest` | mail | 30s | 3 | Yes (time-window query) |

### New Scheduled Commands
| Command | Schedule |
|---|---|
| `app:send-cancellation-digest` | Every 6 hours |
| `app:purge-pending-deletions` | Daily at 03:00 |
| `app:send-scheduled-announcements` | Every minute (checks send_at) |

### New Activity Events
| Event | Category | Dispatched From |
|---|---|---|
| `ProjectMemberAdded` | Member | `AddProjectMember` |
| `ProjectMemberRemoved` | Member | `RemoveProjectMember` |
| `ScannerAssigneeAdded` | Member | Scanner assignee operations |
| `ScannerAssigneeRemoved` | Member | Scanner assignee operations |
| `ProjectCloned` | Project | `CloneProject` |
| `ScannerLockout` | System | `ScannerAuth` rate limit |
| `AnnouncementScheduled` | Email | `CreateAnnouncement` |
| `EventRepublished` | Event | `PublishEvent` (re-publish path) |

---

## New Database Entities Summary

### New Models
| Model | Table | FK Dependencies |
|---|---|---|
| `HintText` | `hint_texts` | organizations, projects |
| `Announcement` | `announcements` | projects, events?, volunteer_jobs?, shifts?, users |
| `AnnouncementTemplate` | `announcement_templates` | organizations |

### Modified Models
| Model | Changes |
|---|---|
| `Project` | +sender_name, +contact_email, +cancellation_enabled, +cancellation_cutoff_hours, +website_description, +website_contact_info, +website_token, +website_published, +deletion_requested_at |
| `Event` | +notification_email, +was_previously_published, +deletion_requested_at, -cancellation_cutoff_hours (dropped) |
| `Shift` | +shift_date, starts_at/ends_at nullable, +start_text_override, +end_text_override |
| `ActivityLog` | +project_id |
| `EmailTemplateType` | +5 new cases |
| `EmailTemplateRenderer` | German defaults, new placeholders |

### Migration Order
1. `add_project_id_to_activity_logs_table` (#64)
2. `add_email_settings_to_projects_table` (#47)
3. `add_notification_email_to_events_table` (#85)
4. `add_cancellation_settings_to_projects_table` (#85)
5. `add_website_fields_to_projects_table` (#83)
6. `make_shift_times_nullable_and_add_text_overrides` (#86)
7. `create_hint_texts_table` (#74)
8. `add_was_previously_published_to_events` (#84)
9. `add_deletion_requested_at_to_projects_and_events` (#79)
9b. `drop_cancellation_cutoff_hours_from_events` (#85)
10. `create_announcements_table` (#87)
11. `create_announcement_templates_table` (#87)

Note: Since features ship as individual PRs, each PR contains its own migration(s). The order above ensures FK dependencies are respected if PRs merge in sequence, but since no migration depends on another M13 migration's table, they can merge in any order.

---

## Data Classification

| Model | Classification | Notes |
|---|---|---|
| HintText | internal | No PII, admin-facing content |
| Announcement | internal | Contains subject/body, no PII |
| AnnouncementTemplate | internal | Reusable content, no PII |
| Project (new fields) | internal | sender_name/contact_email are org data |
| Event (new fields) | internal | notification_email is org data |

---

## Alpine.js State Plan

Most features in M13 are server-driven (Livewire). Alpine usage is minimal:

| Component | Alpine State | Purpose |
|---|---|---|
| `AnnouncementComposer` | `x-data="{ showScheduler: false }"` | Toggle between immediate/scheduled mode |
| `EventSettings` | `x-data="{ activeTab: 'general' }"` | Tab navigation |
| `ProjectWebsiteEditor` | `x-data="{ previewMode: false }"` | Toggle preview |
| `Dashboard` (rework) | `x-data="{ expandedProject: null }"` | Expand/collapse project tiles |
| Scanner touch targets | Existing Alpine scanner state | No changes |

No new `Alpine.store()` usage. All state is component-local.

---

## Tailwind CSS v4 Configuration

No changes to theme configuration. All features use existing design tokens and Flux UI components. The scanner touch target improvements (#48) use standard Tailwind sizing utilities (min-h-12, min-h-14, p-4, etc.).

---

## Testing Strategy Summary

**Test pyramid (M13 totals):**
- Feature tests: ~180+ (primary coverage)
- Unit tests: ~10 (pure logic: HintTextResolver, GearSummary computation, date offset calculation)
- Browser tests: 0 (existing Playwright runbook covers critical flows; M13 features are admin-facing)

**Per-feature test counts:**
| Feature | Tests |
|---|---|
| #67 Fix MATCH AGAINST | ~5 |
| #64 Activity log extension | ~8 |
| #48 Scanner touch targets | ~3 (manual) |
| #80 Rate limiting | ~10 |
| #81 Email templates German | ~12 |
| #55 Event email templates | ~8 |
| #47 Project email settings | ~6 |
| #46 Event settings | ~8 |
| #74 Hint texts | ~10 |
| #86 Optional shift times | ~12 |
| #85 Cancellation rework | ~10 |
| #84 Re-publish notification | ~8 |
| #83 Project website | ~8 |
| #79 Data deletion | ~14 |
| #78 Clone with offset | ~10 |
| #77 Gear summary | ~8 |
| #66 Promote rework | ~10 |
| #87 Announcements | ~14 |
| #76 Dashboard rework | ~12 |
| **Total** | **~180+** |

**Factory requirements:**
- `HintTextFactory` (new)
- `AnnouncementFactory` (new)
- `AnnouncementTemplateFactory` (new)
- Updates to `ShiftFactory` (nullable times, shift_date)
- Updates to `ProjectFactory` (new fields)
- Updates to `EventFactory` (new fields)

---

## Reviews

### plan — 2026-04-01

| # | Perspective | Concern | Severity | Resolution | Rationale |
|---|---|---|---|---|---|
| DA-1 | Devil's Advocate | SoftDeletes on Project/Event silently changes every query across 30+ files | high | accepted | Use `deletion_requested_at` + `scopeActive()` instead of SoftDeletes trait. Purge job hard-deletes after 30 days. |
| DA-2 | Devil's Advocate | Cancellation rework creates dual-source-of-truth with event-level `cancellation_cutoff_hours` | high | accepted | Drop event-level column. Project is single source. Migration removes column. |
| DA-3 | Devil's Advocate | New Announcements coexists with old `event_announcements` without migration path | medium | accepted | Remove old system entirely: drop `event_announcements` table, delete `EventAnnouncement` model, `SendEventAnnouncement` action, `EventAnnouncements` component, `EventAnnouncementNotification`. New `announcements` table supersedes. |
| DA-4 | Devil's Advocate | Nullable shift times ripples through 36+ files but plan only names ~8 update points | medium | accepted | Add `Shift::hasDefinedTimes(): bool` helper. Grep audit of `->starts_at->` chains during implementation. Expand tests for notification rendering and scanner data serialization. |
| DA-5 | Devil's Advocate | "19 independent features" masks coupling — Event Settings shape depends on merge order | medium | accepted | Pin #46 (Event Settings) as hard prerequisite for #55, #85, #84. Tab structure defined once, subsequent PRs slot in. |
| SS-1 | Scalability Skeptic | Announcement email burst — 1000+ synchronous SMTP calls in one 60s job | high | accepted | `AnnouncementNotification` implements `ShouldQueue`. Framework dispatches individual mail jobs. |
| SS-2 | Scalability Skeptic | Dashboard query explosion — 6N+ queries per page (N = project count) | high | accepted | Batch aggregates with `GROUP BY project_id` — one query per metric type, distribute to tiles. |
| SS-3 | Scalability Skeptic | Re-publish notification fan-out — 30s timeout for 500+ recipients | medium | accepted | `EventRepublishedNotification` implements `ShouldQueue`. |
| SS-4 | Scalability Skeptic | Rate limiter keyed only by email — attacker can spray across addresses | medium | accepted | Add secondary IP-based limiter: `Limit::perMinute(10)->by($request->ip())`. |
| SS-5 | Scalability Skeptic | Gear summary unbounded query loads full dataset into component | low | accepted | Add `WithPagination` for detail rows. Stats remain aggregate queries. |
| JD-1 | Junior Dev Lens | Cancellation rework — "or in addition to" leaves priority undefined | high | accepted | Merged with DA-2. Project is single source, no event-level override. Drop column. |
| JD-2 | Junior Dev Lens | Announcements coexistence forces implementer to make architectural decision | high | accepted | Merged with DA-3. Old system removed entirely in this milestone. |
| JD-3 | Junior Dev Lens | Hint text unique constraint on nullable columns won't prevent duplicates | medium | accepted | Add CHECK constraint: exactly one of `organization_id` or `project_id` must be non-null. |
| JD-4 | Junior Dev Lens | "Ganztägig" fallback contradicts "custom text required when no times" validation | medium | accepted | Remove "Ganztägig" fallback. Custom text is required when both times are absent. |
| JD-5 | Junior Dev Lens | Job naming `SendRepublishNotificationJob` breaks `Send[Thing]Job` convention | low | accepted | Rename to `SendRepublishNotificationJobJob`. |

## Feedback Loops

| # | Date | Direction | Trigger | Fix | Resolution |
|---|---|---|---|---|---|

---

## Self-Review Checklist

### Pass 1: Laravel Best Practices
- [x] `$casts` method (not property) for all models — confirmed in existing code and planned for new models
- [x] No business logic in controllers or Livewire components — all in Actions
- [x] Livewire components handle validation inline (existing pattern, no Form Request classes used)
- [x] Policies cover every model needing access control
- [x] Routes use middleware for auth and authorization

### Pass 2: Livewire v4 Correctness
- [x] No `.defer` syntax — `wire:model` is deferred by default
- [x] `wire:model.live` only for search bars and real-time filter previews
- [x] No deep Livewire nesting — nested components are Blade (not Livewire)
- [x] `#[Locked]` on IDs and tamper-sensitive properties
- [x] `#[Url]` values treated as untrusted input
- [x] `Route::livewire()` used for full-page component routing
- [x] `#[Computed]` for derived data

### Pass 3: Clean Architecture
- [x] Architecture right-sized — default Laravel structure maintained
- [x] Single responsibility per Action class
- [x] Props down (component to Blade), events up (child Livewire dispatch)
- [x] Data classification assigned
- [x] Confidential data (passwords in deletion flow) never persisted in components

### Pass 4: Testability
- [x] Every Action unit-testable without HTTP
- [x] Livewire components testable with `Livewire::test()`
- [x] No static calls preventing mocking (services resolved via `app()`)
- [x] Factories defined for every new model
- [x] All features have test specifications mapped
