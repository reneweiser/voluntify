# Amendment 003: M4, M5, M6 Completion & Cross-Cutting Features Sync

**Date**: 2026-03-07
**Affects**: `specs/status.md`, `specs/project.md`, `design/app-concept.md`, `design/app-design-spec.md`
**Reason**: Milestones 4, 5, and 6 (4/5 features) shipped without spec updates. Six additional cross-cutting features exist in code but were not tracked. Entity count references are stale.

---

## 1. Milestone 4: Attendance & Notifications (Features 13 & 14)

Both features are **Done**.

### Attendance Tracker (Feature 13)

**Livewire Component**: `App\Livewire\Events\AttendanceTracker`
- Displays shift roster with volunteer attendance status
- Volunteer Admins and Organizers can mark each signup as on_time, late, or no_show
- Conflict detection: flags no_show when volunteer has an event arrival record

**Action**: `App\Actions\RecordAttendance`
- `execute(ShiftSignup, AttendanceStatus, User): AttendanceRecord`
- Uses `updateOrCreate` for idempotent status changes
- Detects arrival/no-show conflicts via `EventArrival` lookup

### Pre-Shift Notifications (Feature 14)

**Action**: `App\Actions\SendPreShiftReminders`
- Queries unflagged signups within the reminder window
- Only sends to verified volunteers for published events
- Flags signup after sending (`notification_24h_sent`, `notification_4h_sent`)
- Error handling per-signup with logging (never halts the batch)

**Console Command**: `App\Console\Commands\SendPreShiftRemindersCommand`
- Scheduled command that invokes `SendPreShiftReminders` for both windows

**Notification**: `App\Notifications\PreShiftReminder`
- Queued email with event name, job name, shift time, and job-specific instructions
- Uses `EmailTemplateRenderer` for customizable templates

**Enum**: `App\Enums\ReminderWindow`
- Cases: `TwentyFourHour = '24h'`, `FourHour = '4h'`
- Methods: `hours()`, `flagColumn()`, `templateType()`

---

## 2. Milestone 5: Dashboard & Volunteer Management (Features 15 & 16)

Both features are **Done**.

### Dashboard (Feature 15)

**Livewire Component**: `App\Livewire\Dashboard`
- Role-adaptive dashboard using `currentOrganization()` helper
- Computed properties: `upcomingEventsCount`, `totalVolunteersCount`, `shiftsNeedingAttention`, `upcomingEvents` (limit 5), `canCreateEvents`, `noShowRate`, `attendanceSummary`, `recentPastEvents`
- `attendanceSummary()` returns breakdown: `{on_time, late, no_show, unmarked}`

### Volunteer List & Detail (Feature 16)

**Livewire Components**:
- `App\Livewire\Events\VolunteerList` -- Per-event volunteer list with search and filtering
- `App\Livewire\Events\VolunteerDetail` -- Individual volunteer view with shift signups, attendance records, and event arrivals

---

## 3. Milestone 6: Polish (Features 17-21)

Four of five features are **Done**. Feature 21 (browser tests) has a Playwright MCP runbook.

### Event Cloning (Feature 17) -- Done

**Action**: `App\Actions\CloneEvent`
- `execute(Event): Event`
- Replicates event + all jobs + all shifts in a DB transaction
- Cloned event: `"(Copy)"` suffix, draft status, new slug, no title image
- Excludes: signups, volunteers, tickets, arrivals (structure-only clone)

### Volunteer Promotion (Feature 18) -- Done

**Action**: `App\Actions\PromoteVolunteer`
- `execute(Volunteer, Organization, StaffRole, User): VolunteerPromotion`
- Creates user account (or finds existing by email), attaches to org with role
- Sets `must_change_password = true`, generates temp password
- Links volunteer to user via `user_id` FK
- Guards: `VolunteerAlreadyPromotedException`, `UserAlreadyInOrganizationException`

**Notification**: `App\Notifications\VolunteerPromoted`
- Sends temp password + login link to newly promoted user
- Only sent for new user accounts (not existing users added to org)

### CSV Export (Feature 19) -- Done

**Action**: `App\Actions\ExportVolunteersCsv`
- `execute(Event, ?string search): LazyCollection`
- Columns: name, email, phone, shifts (formatted), arrived (Yes/No), attendance (marked/total)
- Uses cursor-based lazy collection for memory efficiency
- Supports optional search filter via `Volunteer::search()` scope

**Controller**: `App\Http\Controllers\VolunteerExportController`
- Streams CSV response with proper headers

### Dashboard Analytics (Feature 20) -- Done

Analytics are integrated directly into the `Dashboard` component (Feature 15):
- `noShowRate()` -- Organization-wide no-show percentage
- `attendanceSummary()` -- Breakdown by status (on_time, late, no_show, unmarked)
- `recentPastEvents` -- Last 5 completed events with volunteer and arrival counts
- `shiftsNeedingAttention` -- Unfilled shifts across upcoming events

### Browser Integration Tests (Feature 21) -- Done (Runbook)

Playwright MCP runbook with 4 scenarios documented. Browser tests are run via Playwright MCP tools rather than committed test files.

---

## 4. New Cross-Cutting Features (#27-#32)

Six features exist in code but were not tracked in the original spec. Assigned feature numbers #27-#32.

### Feature 27: Per-Organization SMTP Settings -- Done

Enables organizations to send emails from their own SMTP server instead of the app default.

**Service**: `App\Services\OrganizationMailerService`
- `resolveMailerName(Organization): string`
- Dynamically registers a named mailer (`org-{id}`) in Laravel's mail config
- Falls back to default mailer if no SMTP configured

**Trait**: `App\Notifications\Concerns\UsesOrganizationMailer`
- Applied to volunteer-facing notifications
- Resolves the correct mailer based on the event's organization

**Livewire Component**: `App\Livewire\Settings\EmailSettings`
- SMTP configuration form: host, port, username, password, encryption, from address/name
- Test email functionality via `SendTestEmail` action

**Action**: `App\Actions\SendTestEmail`
- Dispatches a test email through the organization's configured SMTP

**Enum**: `App\Enums\SmtpEncryption`
- Cases: `Tls`, `Ssl`, `None`

**Migration**: `add_smtp_settings_to_organizations_table`
- Adds columns: `smtp_host`, `smtp_port`, `smtp_username`, `smtp_password` (encrypted), `smtp_encryption`, `smtp_from_address`, `smtp_from_name`

### Feature 28: Log Viewer -- Done

**Livewire Component**: `App\Livewire\Logs\LogViewer`
- Available at `/admin/logs` for Organizers
- Reads and displays Laravel log entries

### Feature 29: Organization Switching -- Done

Allows users who belong to multiple organizations to switch between them.

**Livewire Component**: `App\Livewire\OrganizationSwitcher`
- Dropdown in sidebar showing current organization
- Lists all organizations the user belongs to
- Inline form to create a new organization

**Action**: `App\Actions\CreateOrganization`
- `execute(User, string name, ?string slug): Organization`
- Creates org in transaction, attaches user as Organizer
- Auto-generates unique slug if not provided

**Helper**: `currentOrganization()` (in `app/helpers.php`)
- Global function to resolve the current organization from the container

**Console Command**: `App\Console\Commands\BackfillPersonalOrganizations`
- One-time migration command for existing users without organizations

### Feature 30: Scanner Event Select -- Done

**Livewire Component**: `App\Livewire\Scanner\ScannerEventSelect`
- 2-step scanner flow: select event first, then scan
- Lists published events for the current organization
- Replaces direct event selection in the scanner component

### Feature 31: Delete User Account -- Done

**Livewire Component**: `App\Livewire\Settings\DeleteUserForm`
- Password confirmation required before deletion
- Available in user settings

### Feature 32: Two-Factor Authentication UI -- Done

**Livewire Components**:
- `App\Livewire\Settings\TwoFactor` -- Enable/disable TOTP 2FA, QR code display, confirmation flow
- `App\Livewire\Settings\TwoFactor\RecoveryCodes` -- View and regenerate recovery codes

Uses Laravel Fortify's built-in 2FA backend.

---

## 5. Updated Artifact Inventory

| Category | Count | Items |
|---|---|---|
| Models | 15 | Organization, User, OrganizationUser, Event, VolunteerJob, Shift, Volunteer, ShiftSignup, AttendanceRecord, Ticket, EventArrival, MagicLinkToken, VolunteerPromotion, EmailTemplate, EmailVerificationToken |
| Livewire Components | 26 | Dashboard, EventList, EventShow, JobsAndShiftsManager, AttendanceTracker, VolunteerList, VolunteerDetail, EmailTemplateEditor, EventSignup, VolunteerTicket, EmailVerificationPage, QrScanner, ManualLookup, ScannerEventSelect, TeamManagement, EmailSettings, Profile, Password, Appearance, DeleteUserForm, TwoFactor, TwoFactor\RecoveryCodes, ChangePassword, Logout, LogViewer, OrganizationSwitcher |
| Actions | 30 | CreateEvent, UpdateEvent, PublishEvent, ArchiveEvent, CloneEvent, CreateVolunteerJob, UpdateVolunteerJob, DeleteVolunteerJob, CreateShift, UpdateShift, DeleteShift, SignUpVolunteer, SignUpVolunteerForShifts, ProcessVolunteerSignup, SendEmailVerification, CompleteEmailVerification, GenerateTicket, GenerateMagicLink, VerifyMagicLink, RecordArrival, RecordAttendance, SendPreShiftReminders, PromoteVolunteer, ExportVolunteersCsv, SaveEmailTemplate, DeleteEmailTemplate, DeleteEventImage, CreateAdminWithOrganization, CreateOrganization, SendTestEmail |
| Controllers | 3 | Controller (base), ScannerApiController, VolunteerExportController |
| Services | 4 | EmailTemplateRenderer, JwtKeyService, QrCodeGenerator, OrganizationMailerService |
| Notifications | 5 | SignupConfirmation, EmailVerification, PreShiftReminder, VolunteerPromoted, StaffInvitation |
| Enums | 8 | EventStatus, StaffRole, AttendanceStatus, ArrivalMethod, EmailTemplateType, SignupOutcomeType, ReminderWindow, SmtpEncryption |
| Value Objects | 4 | HashedToken, PublicToken, SignupOutcome, SignupBatchResult |
| Exceptions | 10 | DomainException, AlreadySignedUpException, EventNotReadyException, ShiftFullException, HasSignupsException, InvalidMagicLinkException, InvalidTicketException, ExpiredVerificationException, VolunteerAlreadyPromotedException, UserAlreadyInOrganizationException |
| Console Commands | 3 | CreateAdminCommand, SendPreShiftRemindersCommand, BackfillPersonalOrganizations |
| Migrations | 23 | (3 Laravel default + 20 application) |
| PHP Tests | 92 | Feature + Unit tests |
| JS Tests | 4 | Vitest scanner tests |

---

## 6. Domain Model Update

Entity count remains at **15** (unchanged from amendment 002). No new database entities were added in M4-M6 or cross-cutting features #27-#32. The SMTP settings were added as columns on the existing `organizations` table.

---

## 7. Items Flagging for Human Review

1. **Entity count references**: `project.md` says "14 entities", `app-concept.md` line 219 says "13 entities". Both should say 15 (corrected from amendment 002).
2. **M6 outcome text**: `project.md` describes M6 as "Quality-of-life features and browser-level tests" without noting completion.
3. **Email notification list**: `project.md` lists 3 notifications; there are now 5 (`StaffInvitation` was always present but unlisted).
4. **Scanner sync URL**: `app-design-spec.md` line 1065 references `/api/scanner/sync`; actual URL is `/admin/scanner/api/events/{eventId}/sync`.
