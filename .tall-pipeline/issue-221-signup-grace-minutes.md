# Milestone: issue-221-signup-grace-minutes — Signup Grace Minutes

**GitHub Issue:** [#221](https://github.com/reneweiser/voluntify/issues/221)
**Features:** #221
**Dependencies:** issue-168-shifts-jobs-active-state, issue-203-priority-shift-gate, issue-206-hide-fully-booked-jobs, issue-207-signup-empty-state-notifications
**Branch:** `milestone/phase-1-2-closeout`

## Plan
- **Status:** complete
- **Gate summary:** add event-level signup cutoff control with a default 30-minute grace window, enforce it consistently in public signup selection, reservation, and final signup, preserve organizer manual enrollment, and verify the behavior with focused Pest and Playwright coverage.

### Scope
- Add `signup_grace_minutes` to events with a default of `30`
- Expose the setting in event settings and persist it through the existing form/action path
- Hide no-longer-bookable shifts from public signup while preserving returning volunteers' existing selections
- Reject expired reservations and final signups with consistent messaging
- Keep organizer manual enrollment working beyond the public cutoff
- Treat untimed shifts as closed at `shift_date` start-of-day plus grace minutes

## Implement
- **Status:** complete
- **Iteration:** 1
- **Tasks:**
  - [x] RED: add public signup, reservation, action, and settings tests covering the cutoff rules
  - [x] GREEN: add event setting storage and a shared signup cutoff check
  - [x] GREEN: enforce the cutoff in public signup, reservations, and final signup while preserving organizer enrollment and returning-volunteer visibility
  - [x] GREEN: add deterministic E2E coverage for the event setting and public signup behavior
  - [x] REFACTOR: centralize the cutoff logic to avoid drift across model and action paths
- **Gate summary:** events now store `signup_grace_minutes`, shifts expose a shared cutoff helper, public signup filters late shifts out of the visible list, reservations and final signup reject expired selections server-side, organizer manual enrollment keeps an explicit bypass, and final submit is bound to both the verified email token and the reserved shift set.

## Test
- **Status:** complete
- **Gate summary:** focused feature, action, Livewire, and full-suite coverage all passed, and Playwright proved that changing the organizer setting immediately changes the visible public shift list for a deterministic event.

### Verification
- `vendor/bin/sail artisan test --compact tests/Feature/Livewire/Events/EventSettingsTest.php tests/Feature/Actions/ReserveShiftsTest.php tests/Feature/Actions/SignUpVolunteerForShiftsTest.php tests/Feature/Public/EventSignupTest.php tests/Feature/Public/EventSignupNotificationSubscriptionTest.php tests/Feature/Livewire/ManualEnrollmentTest.php`
- `vendor/bin/sail artisan test --compact tests/Feature/Actions/UpdateEventTest.php tests/Feature/Public/EventSignupTest.php`
- `vendor/bin/sail artisan test --compact tests/Feature/Livewire/EventSignupTest.php`
- `vendor/bin/sail artisan test --compact`
- `vendor/bin/sail bin pint --dirty --format agent`
- `bash e2e/setup.sh && vendor/bin/sail npm exec playwright test e2e/signup-grace-minutes.spec.ts`

## Security Audit
- **Status:** complete
- **Gate summary:** no unresolved critical or high findings remain. Public reserve and submit paths now enforce `PublishedOpen`, final signup is tied to the verified email token plus the reserved shift set, and server-side update validation backs up the form-level `signup_grace_minutes` bounds.

## Decisions

| # | Stage | Decision | Rationale | Affects |
|---|---|---|---|---|
| 1 | plan | Untimed shifts use `shift_date` start-of-day as their synthetic start for signup cutoff checks | Matches existing date-only shift handling and avoids open-ended same-day signups | implement, test |
| 2 | plan | Organizer manual enrollment bypasses the public signup cutoff | Manual enrollment already bypasses the public priority gate and should stay organizer-controlled | implement, test |
| 3 | plan | Returning volunteers keep seeing their existing shifts even after cutoff | Prevents a regression in returning-volunteer context and preserves already-held selections | implement, test |

## Cross-Milestone Interface

| Category | Items |
|---|---|
| Models | `Event::$fillable/$casts` now include `signup_grace_minutes`; `Shift::signupCutoffAt()` and `Shift::isSignupOpen()` define the shared cutoff rule |
| Actions | `ReserveShifts` rejects closed events and expired shifts; `SignUpVolunteerForShifts` supports public-only `PublishedOpen` enforcement plus the existing organizer bypass flags; `UpdateEvent` validates and persists `signup_grace_minutes`; `ProcessVolunteerSignup` requires public signups to use `PublishedOpen` |
| Livewire | `EventSettings` edits `signup_grace_minutes`; `EventSignup` binds final submit to the verified email token, the reserved shift set, and current event openness |
| E2E | `e2e/setup.sh` seeds `e2e-signup-grace-token`; `e2e/signup-grace-minutes.spec.ts` verifies organizer setting changes against the public shift list |

## Reviews

### plan — 2026-05-07

| # | Perspective | Concern | Severity | Resolution | Rationale |
|---|---|---|---|---|---|
| 1 | Devil's Advocate | Cutoff logic could diverge between filtering and backend enforcement | high | accepted | Plan includes a shared cutoff check reused across all paths |
| 2 | Junior Developer Lens | Organizer enrollment could regress silently if the new rule is global | medium | accepted | Keep an explicit organizer-only bypass and add a regression test |

### security-audit — 2026-05-07

| # | Perspective | Concern | Severity | Resolution | Rationale |
|---|---|---|---|---|---|
| 1 | Security Paranoid | Final public submit trusted tamperable `selectedShiftIds` instead of the reserved shift set | high | accepted | `EventSignup::submitSignup()` now compares submitted shift IDs against active session reservations before calling the signup action |
| 2 | Security Paranoid | `signup_grace_minutes` policy was only enforced in the Livewire form | medium | accepted | `UpdateEvent` now rejects out-of-range values server-side as well |
| 3 | Security Paranoid | Final signup trusted a mutable `volunteerEmail` instead of the verified email token | high | accepted | Public submit now requires the locked verification token to stay verified, project-matched, and email-matched |
| 4 | Security Paranoid | A stale public tab could still reserve or sign up after the organizer closed the event | high | accepted | `ReserveShifts` and public signup completion now enforce `PublishedOpen` at execution time |
| 5 | Security Paranoid | Shared bypass booleans on `SignUpVolunteerForShifts` could be misused by future callers | medium | rejected | In current code they remain reachable only through organizer-authorized `ManualEnrollment`, and the milestone scope does not introduce a broader caller surface |
| 6 | Security Paranoid | Session-scoped reservations can still be replaced by another signup flow in the same browser session | medium | rejected | This is the documented session-scoped reservation limitation already accepted for the public signup wizard and outside the scope of `#221` |
| 7 | Security Paranoid | Locked-shift validation still has a narrow organizer/public mutation race after prevalidation | medium | deferred | No critical/high issue remains for this milestone; broader concurrent mutation hardening can be handled separately if it becomes a real-world problem |
| 8 | Security Paranoid | Priority-gated non-priority shifts can still remain visible in the public list before reserve-time enforcement | low | rejected | Existing priority-gate UX intentionally keeps locked shifts visible for context while server-side reserve/signup still block them |
| 9 | Security Paranoid | Playwright only covers the organizer setting plus public listing update | low | rejected | The higher-risk reserve/final/manual paths are covered by focused Pest tests, which are more reliable for those server-side branches |

## Feedback Loops

| # | Date | Direction | Trigger | Fix | Resolution |
|---|---|---|---|---|---|
