# Voluntify Code Quality Audit

**Date:** 2026-04-02
**Scope:** PHP backend — architecture, SOLID, Clean Code, technical debt, test coverage. Frontend (TypeScript, Alpine, Service Worker) and operational concerns are covered in dedicated sections but were not audited at the same depth as PHP.

---

## Executive Summary

Voluntify is a **well-structured, production-quality Laravel 12 codebase** scoring **8.3/10 overall** on its PHP backend. The project demonstrates strong adherence to SOLID principles, consistent use of Action classes for business logic, proper Livewire v4 patterns, and strong test coverage (850+ tests, ~90% Action coverage, ~88% Livewire component coverage). The main areas for improvement are: implicit auth coupling in Actions (for audit-trail purposes), missing Form Objects for complex Livewire components, 8 untested Actions (including destructive operations), and unaudited frontend/operational dimensions that carry real production risk.

---

## Architecture Overview

| Metric | Value |
|--------|-------|
| Models | 33 |
| Actions | 77 (incl. 2 Fortify) |
| Livewire Components | 43 |
| Services | 7 |
| Policies | 3 |
| Custom Exceptions | 12 |
| Notifications | 9 |
| Test Files | ~221 |
| Test Cases | 850+ |

**Layered Architecture:**
```
Livewire Components (thin adapters)
  -> Actions (single-responsibility business logic)
    -> Models + Services (data + infrastructure)
      -> Policies (authorization)
```

---

## Scorecard

| Category | Score | Notes |
|----------|-------|-------|
| Action Design | 9/10 | Excellent SRP, DI, transactions, domain exceptions |
| Model Design | 8/10 | Clean relationships, scopes; some missing return types |
| Livewire Components | 8/10 | Thin delegation pattern; no Form Objects for complex forms |
| Services | 9/10 | Focused, well-typed, proper DI |
| Policies & Auth | 9/10 | Consistent Gate::authorize(), proper hierarchy |
| Error Handling | 8/10 | 12 domain exceptions, consistent usage |
| Routes & Middleware | 8/10 | Proper grouping, throttling, naming; view-layer gaps (wire:loading, i18n) noted separately |
| Test Quality | 8/10 | Strong Action coverage (~90%); Livewire coverage ~88%; 5 components + 8 Actions untested |
| Type Safety | 8/10 | 95%+ on Actions, 70% on model scopes |
| Database Design | 9/10 | Proper FKs, indexes, unique constraints |
| Frontend Quality | Not fully audited | Scanner TS, Alpine, SW need dedicated review |
| Operations | Not fully audited | Queue scaling and container health need review |
| **Overall** | **8.3/10** | |

---

## Findings by Severity

### CRITICAL

No critical issues identified.

---

### HIGH (4 issues)

#### H0. Untested Livewire Components and Actions
5 Livewire components have no test coverage: `Logout`, `JobCheatSheet`, `Appearance`, `DeleteUserForm`, and `RecoveryCodes`. Of these, `DeleteUserForm` (destructive, auth-sensitive) and `RecoveryCodes` (security-critical 2FA recovery) are highest priority.

8 Actions have no tests (see H3 below for the full list). Overall Livewire coverage is ~88% (38/43) and Action coverage is ~90% (69/77) — strong, but the gaps are concentrated in security-sensitive and destructive operations.

**Fix:** Add tests for `DeleteUserForm` and `RecoveryCodes` first (security-sensitive), then `JobCheatSheet` (public-facing). Add Action tests per H3.

**Implementation plan:** [planning/h0.md](h0.md) — 5 components, ~16 tests, prioritized by security sensitivity.

#### H1. Implicit Auth Coupling in 22 Actions (Audit-Trail Pattern)
**Files:** `ArchiveEvent`, `CreateEvent`, `CreateProject`, `CreateShift`, `DeleteShift`, `PublishEvent`, `UpdateEvent`, `UpdateProject`, and 14 more Actions.

These Actions call `auth()->user()` to dispatch activity events (e.g., `EventArchived::dispatch($event, auth()->user())`). The pattern is uniform: always `if (auth()->user()) { SomeEvent::dispatch($model, auth()->user()); }` — purely optional audit-trail dispatch, never authorization or business logic. This hides a dependency, scatters the audit-trail concern across 22 files, and means zero tests verify that activity events actually fire.

**Fix:** Pass `User $causer` as a **non-nullable** explicit parameter to `execute()`. Non-nullable because in production these Actions are always called from authenticated contexts (Livewire components, artisan commands with a known user). This eliminates the `if (auth()->user())` conditional entirely, guarantees audit trail completeness, and makes the dependency testable — tests can now assert `Event::assertDispatched(EventArchived::class, fn ($e) => $e->causer->is($user))`. Adds one line per test (`$user = User::factory()->create()`) but removes a coverage blind spot. Estimate: 8-12 hours across multiple sprints (each Action change touches its call sites and tests).

**Implementation plan:** [planning/h1.md](h1.md) — 4-batch refactor covering all 22 Actions, 8 Livewire components, and 22 test files.

#### H2. Missing Form Objects for Complex Livewire Components
**Affected:** `EventSettings` (12 fields), `ScannerManagement` (8+ fields), `ProjectShow` (multiple form states)

No `app/Livewire/Forms/` directory exists. All validation is inline. Livewire Form Objects would improve reusability, validation separation, and state management.

**Fix:** Introduce Form Objects for components with 8+ validated fields.

**Implementation plan:** [planning/h2.md](h2.md) — 4 Form Objects across 3 components (EventSettingsForm, ScannerForm, ProjectDetailsForm, CreateEventInProjectForm).

#### H3. Untested Destructive and Critical-Path Actions
8 Actions have no tests:
1. `CreateAdminWithOrganization` — foundational setup
2. `ExportGearSummaryCsv` — CSV export
3. `InviteMember` — invitation flow
4. `PermanentlyDeleteEvent` — destructive operation
5. `PermanentlyDeleteProject` — destructive operation
6. `SendTestEmail` — config testing
7. `Fortify/CreateNewUser` — custom validation logic (password rules, org creation)
8. `Fortify/ResetUserPassword` — password policy enforcement

The destructive operations (`PermanentlyDeleteEvent`, `PermanentlyDeleteProject`), invitation flow (`InviteMember`), and Fortify actions with custom logic are highest priority.

**Fix:** Add tests for all 8. Start with destructive operations, InviteMember, and Fortify actions.

**Implementation plan:** [planning/h3.md](h3.md) — 8 test files, ~47 tests, prioritized by risk (destructive ops first).

---

### MEDIUM (9 issues)

#### M1. Missing Return Types on Model Scopes
15+ query scopes across `Volunteer`, `Event`, `Shift`, `ShiftSignup` lack explicit return type declarations.

#### M2. Blade Views Contain Business Logic
`volunteer-list.blade.php:61-73` calculates attendance percentages in `@php` blocks. Should be a computed property or model accessor.

#### M3. Dashboard Computed Metrics Not Cached
`app/Livewire/Dashboard.php:98-126` — Complex subqueries run on every render without caching.

#### M4. Magic Session Keys
Scanner components use string literals for session keys (`'scanner_id'`, `'scanner_authenticated_at'`). Should use constants or enum.

#### M5. Service Locator in Model Accessor
`app/Models/GuestEntry.php:71` uses `app(QrCodeGenerator::class)` instead of DI.

#### M6. Missing Property Type Hints
~8 Livewire components have `$event*Image` properties without explicit types.

#### M7. Inconsistent Computed Property Strategy
Some components use `#[Computed]` extensively (Dashboard: 11 properties), others mix with private methods.

#### M8. Reservation Expiry Check Ordering in EventSignup
**File:** `app/Livewire/Public/EventSignup.php:290-296`

The `submitSignup()` method checks reservation existence after running `validatePersonalInfo()`. An earlier check in `advanceToConfirmation()` already catches most expirations, but a narrow window remains where validation runs unnecessarily before discovering expiry. The consequence is a graceful redirect to the `WizardState::Expired` screen — not a crash — but moving the check before validation would avoid wasted work.

**Fix:** Move `ShiftReservation::forSession()->active()->exists()` check before `validatePersonalInfo()`.

#### M9. Inconsistent Domain Logic in GearTracker
**File:** `app/Livewire/Events/GearTracker.php:60-64`

Pickup creation uses `RecordGearPickup` Action, but removal directly calls `$gear->pickups()->delete()` — bypassing activity logging. The risk is a missing audit log entry, not data corruption.

**Fix:** Create `RemoveGearPickup` Action for consistency.

---

### LOW (5 issues)

#### L1. ScannerDataController Repeated Guard Clause
**File:** `app/Http/Controllers/ScannerDataController.php`

Scanner ID verification (`$scanner->id !== $scannerId`) is repeated across 6 controller methods. This is a defensive guard — the scanner is already resolved by middleware via `$request->attributes->get('scanner')`. The repetition is cosmetic, not a logic risk.

**Fix:** Extract to a private method on the same controller (not middleware — changing the request lifecycle for a stable, event-day-critical endpoint adds unnecessary risk).

#### L2. Password Validation in Actions
`RequestEventDeletion` and `RequestProjectDeletion` call `Hash::check()` directly instead of delegating to a validation service.

#### L3. Array Parameters Without DTOs
`CreateAnnouncement` accepts associative arrays with optional keys. Consider DTOs for type safety.

#### L4. attendance_records Initial Migration Design
Initial schema had `recorded_by` as NOT NULL, later corrected to nullable. Minor design debt.

#### L5. Some Exceptions Lack User-Facing Messages
Only `MemberAlreadyExistsException` and `CancellationCutoffPassedException` include custom messages. Consider standardizing.

---

## Unaudited Dimensions (Flagged for Follow-Up)

### Frontend Quality
The codebase has a non-trivial TypeScript scanner PWA (~500 lines in `resources/js/scanner/`), Alpine.js components, and a Service Worker (`public/sw.js`). These were not audited at the same depth as PHP. Known concerns:
- **Service Worker cache invalidation:** `CACHE_NAME = 'voluntify-scanner-v1'` with an empty `STATIC_ASSETS` array. Cache version never rotates on deployment, risking stale JS/CSS for scanner users at live events.
- **i18n gaps:** Scanner Alpine component has 10+ hardcoded English strings. Scanner Blade view mixes German ("Gastliste") and English ("Volunteers", "Check In") without `__()` wrapping.
- **`wire:loading` coverage:** Only 3 views use `wire:loading`. Public signup buttons lack `wire:loading.attr="disabled"`, risking double-submits on slow connections at outdoor events.

### Accessibility
Public signup flow has good ARIA patterns (role="status", aria-live, aria-current="step"). Admin views (`volunteer-list`, `gear-tracker`) have minimal ARIA markup. Scanner UI has partial patterns (role="tablist", role="alert") but missing tabpanel focus management.

### Operations
- **Queue scaling:** Production runs a single `queue:work` container with `database` driver. Under event-day load with notifications, reservation cleanup (`every minute`), and pre-shift reminders, the queue can back up.
- **Scheduler health:** The `scheduler` service in `docker-compose.prod.yml` has no healthcheck. If `schedule:work` dies silently, reservation cleanup stops and reservations pile up.

---

## Test Coverage Analysis

### Coverage Summary

| Category | Covered | Total | Percentage |
|----------|---------|-------|------------|
| Actions | 69 | 77 | ~90% |
| Livewire Components | 38 | 43 | ~88% |
| Models | Well-tested | 33 | ~85% |
| HTTP Endpoints | Tested | -- | Good |
| Edge Cases | 4 dedicated files | -- | Good |

*Note: Livewire tests are distributed across `tests/Feature/Livewire/`, `tests/Feature/Events/`, `tests/Feature/Settings/`, `tests/Feature/Auth/`, and `tests/Feature/Public/`. Some components are tested via `Livewire::test()` class invocations, others via string-based component names.*

### Untested Livewire Components (5)
- `Actions/Logout`, `Settings/Appearance`, `Settings/DeleteUserForm`, `Settings/TwoFactor/RecoveryCodes`, `Public/JobCheatSheet`
- **Highest priority:** `DeleteUserForm` (destructive, auth-sensitive) and `RecoveryCodes` (security-critical 2FA)

### Test Quality Strengths
- Factories with useful states (published, archived, verified, expired, etc.)
- Meaningful assertions (no shallow `assertOk()` patterns)
- Comprehensive flow tests (VolunteerSignupFlow, EventLifecycle, ScannerIntegration)
- Proper faking (Notification::fake in 92+ tests, Storage::fake, Queue::fake)
- Time-sensitive testing with Carbon::setTestNow()
- Authorization boundary testing across roles

### Test Quality Weaknesses
- 8 untested Actions concentrated in destructive/security-sensitive operations
- Some Livewire tests use brittle `assertSee()` where `assertSet()` would be better
- Limited Unit tests (5 files, 25 cases) — Value Objects and DTOs undertested
- Validation error paths undertested in some Livewire components

---

## SOLID Compliance

| Principle | Grade | Evidence |
|-----------|-------|----------|
| **Single Responsibility** | A | One Action per use case, thin Livewire components, focused Services |
| **Open/Closed** | A- | Enums for status/config, traits for composition; some tightly coupled scanner logic |
| **Liskov Substitution** | A | Proper inheritance chains, Fortify actions implement contracts correctly |
| **Interface Segregation** | B+ | Actions are focused; no unnecessary interfaces; could benefit from contracts for key services |
| **Dependency Inversion** | B+ | Good constructor DI in Actions; 22 Actions use `auth()` for audit-trail dispatch — decision made to refactor to explicit non-nullable `User $causer` parameter |

---

## Clean Code Assessment

### Strengths
- Descriptive naming throughout (`ProcessVolunteerSignup`, `CancellationCutoffPassedException`)
- PHPDoc blocks on complex methods with proper array shape types
- Value Objects for complex returns (`ShiftSignupResult`, `ReservationResult`, `SignupOutcome`)
- Pessimistic locking in concurrent operations (`ReserveShifts`, `SignUpVolunteerForShifts`)
- LazyCollection for memory-efficient exports
- Encrypted storage for sensitive config (SMTP passwords)
- DB transactions wrapping all multi-model mutations

### Weaknesses
- Business logic in blade templates (attendance calculation)
- Magic strings for session keys
- Some missing explicit return types (model scopes)
- No Form Objects despite complex multi-field forms

---

## Recommended Action Plan

### Immediate (This Sprint)
1. Add tests for untested destructive/critical Actions (H3) — 3-4 hours
2. Add tests for `DeleteUserForm` and `RecoveryCodes` components (H0) — 1-2 hours
3. Create RemoveGearPickup Action (M9) — 30 min
4. Move reservation check earlier in EventSignup (M8) — 15 min

### Next Sprint
5. Introduce Form Objects for complex components (H2) — 2-3 hours
6. Add return types to model scopes (M1) — 1 hour
7. Add `wire:loading` protection to public signup buttons — 30 min
8. Test remaining 3 untested Livewire components (Logout, Appearance, JobCheatSheet) — 1 hour

### Ongoing (Spread Across Sprints)
9. Refactor `auth()->user()` to non-nullable `User $causer` parameter in 22 Actions (H1) — 8-12 hours total
10. Extract blade business logic to computed properties (M2)
11. Cache Dashboard metrics (M3)
12. Create SessionKeys enum (M4)

### Follow-Up Audits Needed
13. **Frontend audit:** Scanner TS, Alpine patterns, Service Worker cache strategy
14. **Accessibility audit:** Admin views ARIA coverage, scanner focus management
15. **i18n audit:** Extract hardcoded strings, standardize German/English
16. **Operations review:** Queue scaling strategy, scheduler healthcheck, Redis evaluation

---

## Review Log

### Reviewers
| Name | Background | Cognitive Style | Focus |
|---|---|---|---|
| Elara Voss | Senior Laravel Architect | Analytical / Rigorous | Correctness & Internal Logic |
| Marcus Chen | Product Engineer (TALL Stack) | Creative / Lateral | Completeness, Gaps & Alternatives |
| Sasha Petrov | Production SRE | Adversarial / Skeptical | Practical Viability & Failure Modes |

### Cycle 1 Changes Applied
| Change | Raised By | Rationale |
|---|---|---|
| Removed false C1 (N+1 in VolunteerList) | Elara + Sasha (consensus) | `VolunteerList.php:38` already eager-loads `shiftSignups.attendanceRecord` |
| Downgraded C3 (reservation race) from Critical to Medium (M8) | Elara + Sasha (consensus) | Existing expiration handling redirects gracefully to `WizardState::Expired` |
| Downgraded C2 (GearTracker) from Critical to Medium (M9) | Sasha (solo, H confidence) | Risk is missing audit log, not data corruption |
| Downgraded H2 (ScannerDataController DRY) to Low (L1) | Elara + Sasha (consensus) | Guard clause backed by middleware; refactoring stable event-day endpoint adds risk |
| Fixed Action count from 85 to 75 | Elara (solo, H confidence) | Verified via filesystem |
| Fixed Test file count from 174 to ~221 | Elara (solo, H confidence) | Verified via filesystem |
| Promoted Livewire test coverage gap to Critical (C1) | Marcus (solo, H confidence) | 62% coverage in a Livewire-heavy app is the most impactful gap |
| Added "Unaudited Dimensions" section (frontend, a11y, i18n, ops) | Marcus + Sasha (consensus) | Audit claimed "full codebase" but omitted non-PHP dimensions |
| Narrowed scope claim in header | Marcus (solo, H confidence) | Prevents false sense of completeness |
| Reframed H1 from "implicit dependency" to "audit-trail coupling" | Elara (solo, M confidence) | Actions function without auth; `auth()` is for optional event dispatch |
| Updated H1 time estimate from 3-4 hours to 8-12 hours | Sasha (solo, M confidence) | Changing 22 Action signatures touches all call sites and tests |
| Adjusted Routes & Middleware score from 9/10 to 7/10 | Marcus (solo, H confidence) | Missing `wire:loading` and i18n gaps |
| Adjusted Test Quality score from 8/10 to 7/10 | Marcus (solo, H confidence) | 62% Livewire coverage is too low for 8/10 |
| Adjusted DI grade from B to B+ | Elara (solo, M confidence) | `auth()` usage is optional, not a core DI violation |

### Cycle 2 Changes Applied
| Change | Raised By | Rationale |
|---|---|---|
| Corrected Livewire coverage from 30/48 (62%) to 38/43 (~88%) | All 3 (consensus) | Tests distributed across multiple directories; original count only checked `tests/Feature/Livewire/` |
| Downgraded C1 (Livewire coverage) from Critical to High (H0) | All 3 (consensus) | At 88% with only 5 untested components (mostly low-risk), Critical was overcorrection |
| Updated untested components list from 18 to 5 | All 3 (consensus) | `Dashboard`, `ChangePassword`, etc. have tests in non-obvious locations |
| Corrected Action count from 75 to 77 | Elara + Marcus + Sasha (consensus) | 2 Fortify actions were excluded without explanation |
| Added 2 Fortify actions to H3 untested list (now 8 total) | Marcus + Sasha (consensus) | `CreateNewUser` and `ResetUserPassword` have custom validation logic |
| Restored Routes & Middleware score from 7/10 to 8/10 | Elara + Sasha (consensus) | wire:loading and i18n are view-layer concerns, not routing/middleware |
| Restored Test Quality score from 7/10 to 8/10 | All 3 (consensus) | Corrected coverage numbers (88% Livewire, 90% Actions) justify 8/10 |
| Updated overall score from 8.0/10 to 8.3/10 | Synthesis | Reflects corrected metrics and removal of false findings |

### Deferred Issues
| Issue | Raised By | Reason Deferred |
|---|---|---|
| Service Worker cache invalidation | Marcus (M confidence) | Flagged in "Unaudited Dimensions" for follow-up audit — needs deeper investigation |
| Scheduler healthcheck | Sasha (M confidence) | Flagged in "Unaudited Dimensions" — operational concern outside PHP audit scope |
| Queue as correctness risk (not just scaling) | Marcus Cycle 2 (H confidence) | Valid concern that reservation cleanup can queue behind notifications; flagged in Operations section but not promoted to main findings — needs load testing data |
| Form Objects threshold arbitrary | Marcus Cycle 2 (M confidence) | H2 kept at High; could be Medium since no concrete defect demonstrated, but pattern improvement is well-justified for 12+ field forms |
