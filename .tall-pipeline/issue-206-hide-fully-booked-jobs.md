# Milestone: issue-206-hide-fully-booked-jobs — Hide Fully Booked Jobs

**GitHub Issue:** [#206](https://github.com/reneweiser/voluntify/issues/206)
**Features:** #206
**Dependencies:** issue-168-shifts-jobs-active-state, issue-203-priority-shift-gate
**Branch:** current workspace

## Plan
- **Status:** complete
- **Gate summary:** filter fully booked jobs out of the public signup job list while keeping partially available jobs intact and preserving visibility for returning volunteers who already hold a shift in an otherwise full job.

### Scope
- Filter `EventSignup::jobs()` so jobs disappear only when every active shift is full and none of those shifts belongs to the returning volunteer.
- Keep existing per-shift rendering unchanged so full shifts still show within partially available jobs.
- Cover new volunteer, returning volunteer, and all-jobs-full cases with focused public signup tests.

## Implement
- **Status:** complete
- **Iteration:** 1
- **Tasks:**
  - [x] RED: add public signup coverage for hidden full jobs, partially full jobs, returning-volunteer visibility, and all-jobs-full empty collections
  - [x] GREEN: filter fully booked jobs in `EventSignup::jobs()` while preserving jobs containing an existing volunteer signup
  - [x] REFACTOR: reuse the existing shift fullness helpers and leave the Blade rendering unchanged
  - [x] Verify: run focused Sail Pest coverage and Pint
- **Gate summary:** the public signup now omits jobs that offer no selectable shifts, but still shows partially available jobs and keeps returning volunteers' existing fully booked jobs visible.

## Test
- **Status:** complete
- **Gate summary:** focused public signup Pest coverage passed for hiding fully booked jobs, preserving partially available jobs, keeping returning-volunteer visibility, and returning an empty collection when every job is fully booked, plus dirty Pint formatting.

## Cross-Milestone Interface

| Category | Items |
|---|---|
| Public signup | `EventSignup::jobs()` now filters fully booked jobs unless one of the job's shifts is in `existingShiftIds` |
