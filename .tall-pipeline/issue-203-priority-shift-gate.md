# Milestone: issue-203-priority-shift-gate — Priority Shift Gate

**GitHub Issue:** [#203](https://github.com/reneweiser/voluntify/issues/203)
**Features:** #203
**Dependencies:** m10-signup, issue-168-shifts-jobs-active-state
**Branch:** current workspace

## Plan
- **Status:** complete
- **Gate summary:** add event-level priority gate persistence and latch logic, wire it into volunteer signup and cancellation flows, and expose the controls/status in organizer admin plus public signup UI.

### Scope
- Add `events.priority_unlock_threshold_percent`, `events.priority_gate_unlocked_at`, and `shifts.is_priority`
- Evaluate the one-way unlock latch from event settings, shift edits, signups, and cancellations
- Block public reservation/signup attempts on regular shifts while the gate is closed, while keeping organizer manual enrollment exempt
- Show locked teaser states plus progress on the public signup page and expose organizer controls/status in jobs/shifts, event settings, and event overview

## Implement
- **Status:** complete
- **Iteration:** 1
- **Tasks:**
  - [x] RED: extend model, action, cancellation, public signup, event settings, event overview, and manual enrollment tests for priority gating
  - [x] GREEN: add schema/model support for event thresholds, unlock timestamp, and per-shift priority flags
  - [x] GREEN: enforce the gate in reservation and signup actions while bypassing it for organizer manual enrollment
  - [x] GREEN: add organizer admin controls and public signup banner/progress with locked teaser shifts
  - [x] REFACTOR: keep the latch one-way and reuse the shared event evaluation logic from all relevant mutations
  - [x] Verify: run focused Sail Pest coverage and Pint
- **Gate summary:** volunteers now see and respect the priority gate before regular shifts unlock, organizers can configure and inspect it in admin, and manual organizer enrollment still bypasses the gate while contributing to the unlock threshold.

## Test
- **Status:** complete
- **Gate summary:** focused Pest coverage passed for the new event gate model logic, signup and cancellation actions, public signup UI, organizer admin screens, and adjacent shift/event action regressions, plus dirty Pint formatting.

## Cross-Milestone Interface

| Category | Items |
|---|---|
| Schema | `events.priority_unlock_threshold_percent`, `events.priority_gate_unlocked_at`, `shifts.is_priority` |
| Models | `Event::isPriorityGateOpen()`, `Event::priorityFillRate()`, `Event::priorityFilledSpots()`, `Event::priorityCapacityTotal()`, `Event::evaluatePriorityGate()`, `Shift::scopePriority()`, `Shift::scopeNonPriority()` |
| Actions | `ReserveShifts` and `SignUpVolunteerForShifts` gate regular public selections; `ManualEnrollment` bypasses with `bypassPriorityGate`; `CancelShiftSignup`, `CreateShift`, `UpdateShift`, and `UpdateEvent` all re-evaluate the latch |
| Admin UI | `EventSettings` threshold control, `JobsAndShiftsManager` priority toggle, `EventShow` priority gate status card |
| Public signup | `EventSignup` priority gate banner/progress plus locked regular-shift teaser state |
