# Milestone: issue-201-gear-scanner-type — Gear Scanner Type

**GitHub Issue:** [#201](https://github.com/reneweiser/voluntify/issues/201)
**Features:** #201
**Dependencies:** m11-scanner, m12-guest-lists, issue-196-scanner-arrival-duplicate-status
**Branch:** `milestone/phase-4-scanner-gear-expansion`

## Plan
- **Status:** complete
- **Gate summary:** finish the dedicated `gear` scanner path by enforcing pooled volunteer gear access, keeping guest-group filtering intact, trimming the gear payload away from attendance data, and covering the workflow with focused Pest and Playwright tests.

### Scope
- Keep the existing Gear scanner type, management form, migration, and scanner UI additions already present in the codebase
- Restrict volunteer gear pickup to volunteers inside the configured scanner pool
- Return gear scanner volunteer payloads without shift attendance detail
- Verify guest-group filtering and all-confirmed-list fallback for guest gear access
- Add Playwright coverage for gear scanner volunteer and guest lookup flows

## Implement
- **Status:** complete
- **Iteration:** 1
- **Tasks:**
  - [x] RED: expose the remaining mismatch via focused scanner API and management tests
  - [x] GREEN: scope volunteer gear pickup queries to the scanner pool and suppress attendance payloads for `gear` scanner data responses
  - [x] GREEN: fix stale test expectations/imports introduced by the Gear scanner expansion
  - [x] GREEN: add a deterministic Playwright gear scanner fixture and browser spec
  - [x] REFACTOR: keep the changes inside the existing scanner controller, tests, and E2E setup without introducing new abstractions
- **Gate summary:** the Gear scanner now respects `pool_event_ids` for volunteer pickups, keeps guest access filtered by confirmed guest lists and optional guest groups, and exposes only the reduced data contract the Gear UI needs.

## Test
- **Status:** complete
- **Gate summary:** focused backend, Livewire, JS, formatting, and Playwright scanner coverage all passed from a clean E2E seed.

### Verification
- `vendor/bin/sail artisan test --compact --filter=ProjectScanner`
- `vendor/bin/sail artisan test --compact --filter=ScannerDataController`
- `vendor/bin/sail artisan test --compact --filter=ScannerManagement`
- `vendor/bin/sail artisan test --compact --filter=ScannerApp`
- `vendor/bin/sail artisan test --compact --filter=GearPickup`
- `vendor/bin/sail npm run test -- --run tests/js/scanner/alpine-scanner.test.ts tests/js/scanner/idb-store.test.ts`
- `vendor/bin/sail bin pint --dirty --format agent`
- `bash e2e/setup.sh && vendor/bin/sail npm exec playwright test e2e/gear-scanner.spec.ts e2e/entry-staff-volunteer-lookup.spec.ts e2e/entry-staff-scanner-event-split.spec.ts e2e/va-scanner-shift-list.spec.ts e2e/va-scanner-arrival-status.spec.ts e2e/scanner-auth-timezone.spec.ts`

## Security Audit
- **Status:** complete
- **Gate summary:** manual review of the new Gear scanner boundary found no unresolved critical or high issues after tightening volunteer gear pickup to scanner-pool membership and keeping guest gear access constrained to confirmed project guest lists plus optional `guest_group_ids`.

## Cross-Milestone Interface

| Category | Items |
|---|---|
| HTTP | `ScannerDataController::data()` now returns reduced volunteer payloads for `gear` scanners; `gearPickup()` now requires pooled volunteer membership |
| E2E | `e2e/setup.sh` seeds `e2e-gear-scanner-token`; `e2e/gear-scanner.spec.ts` covers volunteer and guest lookup flows |
| Tests | Gear scanner coverage expanded across `ScannerDataControllerTest`, `ScannerDataControllerGuestTest`, `GuestGearPickupApiTest`, and `ScannerManagementTest` |
