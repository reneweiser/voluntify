# Milestone: m19-non-expiring-magic-links — Non-Expiring Volunteer Magic Links

**GitHub Issue:** [#185](https://github.com/reneweiser/voluntify/issues/185)
**Features:** #185
**Dependencies:** m15-volunteer-portal-enhancements, m17-reliability-quick-wins

## Plan
- **Status:** complete
- **Gate summary:** remove fixed expiry from volunteer magic links, preserve expired-state handling for explicit past timestamps, backfill existing rows

### Scope
- Make new volunteer magic links non-expiring by storing `expires_at = null`
- Treat nullable expiry as valid during verification
- Backfill existing `magic_link_tokens` rows to non-expiring
- Remove the 72-hour sentence from volunteer-facing access-link emails
- Keep explicit expired-state behavior for any non-null past timestamps

## Implement
- **Status:** complete
- **Iteration:** 1
- **Tasks:**
  - [x] AC-1: Write failing tests for non-expiring token generation and verification
  - [x] AC-2: Write failing tests for volunteer portal/ticket access with nullable expiry tokens
  - [x] AC-3: Write failing notification assertions for removed expiry sentence
  - [x] AC-4: Implement nullable expiry behavior in generation, verification, factory, and notifications
  - [x] AC-5: Add migration to backfill existing magic links to non-expiring
  - [x] Refactor: keep tests readable for explicit expired-token scenarios
  - [x] Verify: run targeted Sail tests and Pint

## Test
- **Status:** complete
- **Gate summary:** targeted RED/GREEN loop complete, related caller tests green, full suite green (`1920` tests)

## Decisions

| # | Stage | Decision | Rationale | Affects |
|---|---|---|---|---|
| 1 | plan | Treat `magic_link_tokens` as volunteer-only and backfill all rows | All current model and action call sites are volunteer portal/ticket related | implement |
| 2 | plan | Prove backfill through behavior tests, not migration-runner tests | Repo uses `RefreshDatabase`; acceptance is retained access, not migration engine behavior | implement |
| 3 | plan | `down()` restores null expiries to `created_at + 72 hours` before making column non-nullable again | Practical reversible path matching previous production semantics | implement |

## Cross-Milestone Interface

| Category | Items |
|---|---|
| Actions | GenerateMagicLink, VerifyMagicLink, RequestPortalAccessLink |
| Livewire | Public\VolunteerPortal, Public\VolunteerTicket |
| Notifications | PortalAccessLink, TicketResendNotification, SignupConfirmation, CancellationConfirmation |
| Traits | HasRetryStrategy |
