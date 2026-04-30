# Milestone: issue-196-scanner-arrival-duplicate-status — Scanner Arrival Duplicate Status

**GitHub Issue:** [#196](https://github.com/reneweiser/voluntify/issues/196)
**Features:** #196
**Dependencies:** m11-scanner, m16-bugfixes
**Branch:** current workspace

## Plan
- **Status:** complete
- **Gate summary:** keep the `entry_staff` arrival duplicate flow unchanged while preventing `volunteer_admin` QR scans from showing the irrelevant duplicate arrival state.

### Scope
- Update `resources/js/scanner/alpine-scanner.ts` so QR-selected volunteers on `volunteer_admin` scanners stop after loading the volunteer result
- Preserve the existing `entry_staff` duplicate and ready-to-check-in messaging exactly
- Add focused Vitest regression coverage for both scanner types and both arrival states

## Implement
- **Status:** complete
- **Iteration:** 1
- **Tasks:**
  - [x] RED: add failing QR-selection tests for `volunteer_admin` and `entry_staff` arrival states
  - [x] GREEN: return early for `volunteer_admin` before the arrival duplicate branch
  - [x] REFACTOR: keep the change isolated to the existing QR selection flow with no behavior changes outside scanner type branching
  - [x] Verify: run focused Sail Vitest coverage and a production Vite build
- **Gate summary:** `volunteer_admin` now always lands on `state = 'result'` with an empty `resultMessage` after QR volunteer selection, while `entry_staff` still shows the existing duplicate and ready messages.

## Test
- **Status:** complete
- **Gate summary:** RED confirmed with 2 failing Vitest assertions before the fix. GREEN verified with `vendor/bin/sail npm run test -- --run tests/js/scanner/alpine-scanner.test.ts` and `vendor/bin/sail npm run build`.

## Cross-Milestone Interface

| Category | Items |
|---|---|
| Scanner TS | `resources/js/scanner/alpine-scanner.ts` QR volunteer selection flow |
| Tests | `tests/js/scanner/alpine-scanner.test.ts` |
