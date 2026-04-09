# Milestone: m16-bugfixes — Bug Fixes

**Features:** #132 scanner modes, #133 gear-only shifts, #138 VA arrival button, #141 dropdown placeholder, #142 portal cancel errors
**Dependencies:** m15-volunteer-portal-enhancements

## Issues

### #132 — fix(scanner): disable modes selection for Entry Staff scanner type
- **Area:** Scanner
- **Problem:** Mode checkboxes (Check-in, Gear Pickup) shown for both scanner types; Entry Staff doesn't support modes
- **Fix:** Only show mode checkboxes when type = Volunteer Admin in `ScannerManagement` component

### #133 — fix(scanner): VA gear-only mode still shows shifts after scan
- **Area:** Scanner
- **Problem:** When VA scanner configured with only "Gear Pickup" mode, shifts still display after scan
- **Fix:** Conditionally render shifts (if `modes.includes('checkin')`) and gear (if `modes.includes('gear_pickup')`) in scanner frontend

### #138 — fix(scanner): remove global arrival button from Volunteer Admin scanner
- **Area:** Scanner
- **Problem:** VA scanner shows global "Check In" button that triggers `confirmArrival()` (event-level), confusing with per-shift `confirmAttendance()`
- **Fix:** Remove `confirmArrival()` button from VA scanner view; keep it only for Entry Staff scanner

### #141 — fix(signup): custom field dropdown missing placeholder option
- **Area:** Signup
- **Problem:** Select/dropdown custom fields auto-select first option; submitting without changing triggers validation error
- **Fix:** Add empty placeholder `<flux:select.option>` as first entry in custom field dropdowns (and gear size dropdowns)

### #142 — fix(portal): 403/500 when cancelling shifts from volunteer portal
- **Area:** Portal
- **Problem:** Two errors when cancelling shifts: 403 (volunteer ID mismatch across projects) and 500 (uncaught DomainException)
- **Fix:** Fix volunteer ownership check; wrap `CancelShiftSignup` call in try/catch for DomainException

## Plan
- **Status:** skipped (bug fixes — no planning needed)

## Implement
- **Status:** complete
- **Iteration:** 1
- **Tasks:**
  - [x] #132: Hide mode checkboxes for Entry Staff scanner type + `updatedFormType()` hook to reset modes (3 tests)
  - [x] #133: Conditional rendering of shifts/gear sections based on scanner modes (4 tests)
  - [x] #138: Remove global arrival button from VA scanner, restrict `canConfirmArrival` to Entry Staff (1 test)
  - [x] #141: Add placeholder `<flux:select.option>` to custom field + gear size dropdowns (2 tests)
  - [x] #142: Fix ownership check via scoped query, add try/catch for DomainException, eager-load relation chain (4 tests)
- **Gate summary:** 5 bugs fixed, 14 new tests added, all 1770 tests pass (3842 assertions)

## Test
- **Status:** pending

## Security Audit
- **Status:** pending

## Decisions

| # | Stage | Decision | Rationale | Affects |
|---|---|---|---|---|

## Cross-Milestone Interface

| Category | Items |
|---|---|

## Reviews

## Feedback Loops

| # | Date | Direction | Trigger | Fix | Resolution |
|---|---|---|---|---|---|
