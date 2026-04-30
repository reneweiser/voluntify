# Milestone: issue-168-shifts-jobs-active-state — Jobs & Shifts Active State

**GitHub Issue:** [#168](https://github.com/reneweiser/voluntify/issues/168)
**Features:** #168
**Dependencies:** m10-signup
**Branch:** current workspace

## Plan
- **Status:** complete
- **Gate summary:** add an active flag to volunteer jobs and shifts, keep inactive records visible in admin, and filter them out of public signup without touching existing signups.

### Scope
- Add `is_active` persistence for `volunteer_jobs` and `shifts`
- Show inactive state plus reactivation controls in `JobsAndShiftsManager`
- Filter inactive jobs/shifts from public signup queries and reject inactive IDs in reservation/signup actions
- Cover the behavior with focused action, Livewire, and public signup tests

## Implement
- **Status:** complete
- **Iteration:** 1
- **Tasks:**
  - [x] RED: extend action, admin, and public signup tests for inactive jobs/shifts
  - [x] GREEN: add `is_active` schema/model support and filter inactive records from signup
  - [x] GREEN: add admin inactive badges plus activate/deactivate controls for jobs and shifts
  - [x] REFACTOR: keep existing signup and admin flows intact for active records and existing signups
  - [x] Verify: run focused Sail Pest coverage and Pint
- **Gate summary:** inactive jobs and shifts now stay manageable in admin, can be reactivated, and are both hidden and rejected in the public signup flow.

## Test
- **Status:** complete
- **Gate summary:** focused Pest coverage passed for the updated actions, public signup flow, and jobs/shifts manager, plus dirty Pint formatting.

## Cross-Milestone Interface

| Category | Items |
|---|---|
| Schema | `volunteer_jobs.is_active`, `shifts.is_active` |
| Models | `VolunteerJob::scopeActive()`, `Shift::scopeActive()` |
| Admin UI | `JobsAndShiftsManager` activate/deactivate controls and inactive badges |
| Public signup | `EventSignup`, `ReserveShifts`, `SignUpVolunteerForShifts` active-only filtering |
