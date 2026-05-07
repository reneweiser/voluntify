# Milestone: issue-222-portal-jwt-refresh — Volunteer Portal JWT Refresh

**GitHub Issue:** [#222](https://github.com/reneweiser/voluntify/issues/222)
**Features:** #222
**Dependencies:** m15-volunteer-portal-enhancements, m11-scanner, m19-non-expiring-magic-links
**Branch:** `milestone/phase-1-2-closeout`

## Plan
- **Status:** complete
- **Gate summary:** fix the stale portal QR refresh path by refreshing ticket JWTs on volunteer portal mount with the existing action pattern, then cover the path with focused Pest and Playwright scanner verification.

### Scope
- Refresh persisted ticket JWTs when volunteers open the portal and have a project ticket
- Keep `Ticket::qrCodeSvg()` side-effect free
- Reuse the existing `RefreshTicketJwt` action instead of moving refresh into the model
- Add deterministic E2E data for a stale portal QR and scanner verification

## Implement
- **Status:** complete
- **Iteration:** 1
- **Tasks:**
  - [x] RED: add a portal regression test that proves stale JWTs are not refreshed today
  - [x] GREEN: refresh the volunteer portal ticket JWT during mount when a ticket exists
  - [x] GREEN: add deterministic E2E fixture coverage for portal QR refresh and scanner verification
  - [x] REFACTOR: keep the refresh logic in the existing action/component pattern without adding new abstractions
- **Gate summary:** the volunteer portal now refreshes stale ticket JWTs on mount, the ticket page keeps the same behavior with locked public state, and the refresh action skips redundant writes when a JWT already verifies for the current period.

## Test
- **Status:** complete
- **Gate summary:** focused Livewire, ticket, JWT, and browser verification all passed, including a deterministic stale-portal QR path that successfully scans after the portal refreshes the token.

### Verification
- `vendor/bin/sail artisan test --compact tests/Feature/Livewire/VolunteerPortalTest.php tests/Feature/Public/VolunteerTicketTest.php tests/Feature/Actions/RefreshTicketJwtTest.php tests/Feature/Models/TicketTest.php`
- `vendor/bin/sail bin pint --dirty --format agent`
- `bash e2e/setup.sh && vendor/bin/sail npm exec playwright test e2e/volunteer-portal-jwt-refresh.spec.ts e2e/va-scanner-arrival-status.spec.ts`

## Security Audit
- **Status:** complete
- **Gate summary:** no critical or high findings remain. The public ticket component state is now locked, and JWT refresh writes are limited to stale or invalid current tokens instead of every page load.

## Decisions

| # | Stage | Decision | Rationale | Affects |
|---|---|---|---|---|
| 1 | plan | Use the existing `RefreshTicketJwt` action in `VolunteerPortal::mount()` | Minimal fix that mirrors `VolunteerTicket::mount()` and avoids model render side effects | implement, test |

## Cross-Milestone Interface

| Category | Items |
|---|---|
| Livewire | `VolunteerPortal::mount()` now refreshes existing project tickets; `VolunteerTicket` public state is locked |
| Actions | `RefreshTicketJwt::execute()` now short-circuits when the existing token already verifies for the current period |
| E2E | `e2e/setup.sh` seeds `e2e-portal-refresh-token`; `e2e/volunteer-portal-jwt-refresh.spec.ts` verifies the portal-to-scanner stale JWT flow |

## Reviews

### plan — 2026-05-07

| # | Perspective | Concern | Severity | Resolution | Rationale |
|---|---|---|---|---|---|
| 1 | Security Paranoid | Moving refresh into `Ticket::qrCodeSvg()` would hide writes inside rendering | medium | accepted | Keep refresh at the component boundary |

### security-audit — 2026-05-07

| # | Perspective | Concern | Severity | Resolution | Rationale |
|---|---|---|---|---|---|
| 1 | Security Paranoid | `VolunteerTicket` exposed public model and token state on a public Livewire component | medium | accepted | Added `#[Locked]` to the ticket page's public volunteer, ticket, and token properties |
| 2 | Security Paranoid | JWT refresh rewrote the ticket on every mount | medium | accepted | `RefreshTicketJwt` now returns early when the current token already verifies against today's key |
| 3 | Security Paranoid | Refreshing a ticket does not revoke previously copied QR tokens before key rotation | medium | rejected | This is an existing scanner-platform design choice tied to the current + previous key window, outside the scope of `#222` |
| 4 | Security Paranoid | Expired-token project resolution reveals project re-entry context | low | rejected | The current UX intentionally exposes the re-request path for holders of an expired valid token |
| 5 | Security Paranoid | Coverage could expand further around public ticket tampering | low | deferred | Existing invalid/expired/no-ticket coverage is already present; broader tampering tests can be added during future public-portal hardening |

## Feedback Loops

| # | Date | Direction | Trigger | Fix | Resolution |
|---|---|---|---|---|---|
