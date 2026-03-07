# Amendment 001: Sync Status with Implemented Milestones (M1, M2, M2.1, M3 Part 1)

**Date**: 2026-03-02
**Affects**: `specs/status.md`, `specs/project.md`
**Reason**: The status dashboard and project spec have not been updated since initial creation. Milestones 1, 2, 2.1, and M3 Part 1 have been implemented, but `status.md` still shows all features as "Not Started."

---

## What Was Discovered

A full code audit against `specs/project.md` and `specs/status.md` reveals that four milestones worth of features have been implemented without corresponding spec status updates. The codebase contains:

- 15 Eloquent models with factories (all 14 domain entities)
- 21 Actions (covering event CRUD, jobs/shifts CRUD, signup, tickets, magic links, arrivals, email templates)
- 8 Livewire components (Dashboard, EventList, EventShow, JobsAndShiftsManager, EmailTemplateEditor, EventSignup, VolunteerTicket, ChangePassword)
- 3 Services (JwtKeyService, QrCodeGenerator, EmailTemplateRenderer)
- 2 Middleware (RequirePasswordChange, ResolveOrganization)
- 2 Policies (EventPolicy, OrganizationPolicy)
- 1 API Controller (ScannerApiController with data + sync endpoints)
- 5 Enums (StaffRole, EventStatus, ArrivalMethod, AttendanceStatus, EmailTemplateType)
- 3 Value Objects (HashedToken, PublicToken, SignupBatchResult)
- 7 Domain Exceptions
- 2 Notifications (SignupConfirmation, StaffInvitation)
- 53 test files (51 Feature, 2 Unit)

## Impact Assessment

### Features Now Complete

| # | Feature | Evidence |
|---|---|---|
| 01 | Database schema, models & factories | All 14 entities migrated, 14 factories created, model relationships defined |
| 02 | Auth, roles & middleware | Fortify auth (login, register, 2FA, password reset), RequirePasswordChange middleware, StaffRole enum, ChangePassword component |
| 03 | Organization management | Organization model, ResolveOrganization middleware, OrganizationPolicy, TeamManagement component (invite, role change, remove) |
| 04 | App layout & navigation | `layouts/app.blade.php`, `layouts/auth.blade.php`, `layouts/public.blade.php`, sidebar navigation, warm modern visual redesign |
| 05 | Event CRUD | CreateEvent, UpdateEvent, PublishEvent, ArchiveEvent, DeleteEventImage actions; EventList + EventShow components |
| 06 | Jobs & shifts management | CreateVolunteerJob, UpdateVolunteerJob, DeleteVolunteerJob, CreateShift, UpdateShift, DeleteShift actions; JobsAndShiftsManager component |
| 07 | Public event page & volunteer signup | EventSignup component, SignUpVolunteerForShifts action (multi-shift batch), SignupConfirmation notification with magic link |
| 09 | Ticket generation & email | GenerateTicket action (JWT via JwtKeyService), SignupConfirmation notification includes ticket link |
| 10 | Magic links & ticket page | GenerateMagicLink, VerifyMagicLink actions; VolunteerTicket component at `/my-ticket/{magicToken}`; QrCodeGenerator service |
| 22 | Optional volunteer phone number | `phone` column on volunteers, phone field in EventSignup component, VolunteerFactory updated |
| 23 | Event title image upload | `title_image_path` on events, WithFileUploads in EventList + EventShow, DeleteEventImage action |
| 24 | Customizable email templates | EmailTemplate model, EmailTemplateType enum, EmailTemplateRenderer service, EmailTemplateEditor component, SaveEmailTemplate + DeleteEmailTemplate actions |

### Features Partially Complete

| # | Feature | What Exists | What Remains |
|---|---|---|---|
| 11 | QR scanner PWA | ScannerApiController (data + sync endpoints), JwtKeyService.validateToken(), RecordArrival action, SyncArrivalsRequest, EventPolicy.scan() | PWA frontend (Service Worker, IndexedDB, jsQR camera integration, offline queue) |
| 12 | Manual lookup | RecordArrival action supports `ArrivalMethod::ManualLookup` | Manual lookup UI (name search component for Entrance Staff) |

### Features Not Started (Confirmed)

Features 13-21 remain not started. No code exists for attendance tracking, pre-shift notifications, dashboard analytics, volunteer list/detail, event cloning, volunteer promotion flow, CSV export, or browser integration tests.

## Changes to project.md

### 1. Multi-Shift Signup Pattern (Implementation Detail Divergence)

The project spec describes feature 07 with `SignUpVolunteer` as the tracer bullet action. The implementation evolved to support multi-shift signup per submission:

- `SignUpVolunteerForShifts` is the primary batch action (accepts `array $shiftIds`)
- `SignUpVolunteer` is a thin wrapper for single-shift scenarios (used in tests)
- `SignupBatchResult` value object tracks `newSignups`, `skippedFull`, `skippedDuplicate`
- The EventSignup component uses `selectedShiftIds` (array of checkboxes) rather than a single shift selector

This is an intentional feature enhancement (commit `de7c451`), not a bug. The spec's description of the tracer bullet should note the batch pattern.

### 2. Value Objects Section

`SignupBatchResult` should be added to the Value Objects documentation in project.md. It is a readonly class in `app/ValueObjects/` that holds batch signup results.

### 3. Email Template Placeholder Variables

The spec lists 6 placeholder variables: `volunteer_name`, `event_name`, `job_name`, `shift_date`, `shift_time`, `event_location`. The implementation adds a 7th: `shifts_summary` (used in signup confirmation to list all shifts). This should be documented.

### 4. M3 Feature Split

Features 09 and 10 were described as providing backend Actions that feature 07 orchestrates. In practice, GenerateTicket and GenerateMagicLink are called from within SignUpVolunteerForShifts (feature 07's action), and the ticket page (feature 10) also includes the QR code display via Ticket::qrCodeSvg(). The scanner API (part of feature 11) is also complete on the backend side. The remaining M3 work is purely frontend: the PWA scanner UI and the manual lookup search interface.

## Proposed Changes

1. **Update `specs/status.md`** to reflect actual completion status for all features listed above.
2. **Add amendment reference** to `specs/project.md` header.
3. **No changes to project.md requirements** -- the spec's requirements are still valid. The implementation is a superset (multi-shift is additive, not contradictory).

## Items Flagging for Human Review

1. **Feature 11 split**: Should the scanner PWA frontend be tracked as a separate sub-feature (e.g., "11a: Scanner API backend" = Done, "11b: Scanner PWA frontend" = Not Started)? The current granularity makes it unclear that half the feature is complete.
2. **Feature 12 split**: Similarly, the RecordArrival action for manual lookup exists, but the UI does not. Should this be tracked separately?
