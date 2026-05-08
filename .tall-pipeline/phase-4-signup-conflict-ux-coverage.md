# Milestone: phase-4-signup-conflict-ux-coverage — Phase 4: Signup Conflict UX & Coverage

**GitHub Issues:** #164, #163
**Features:** #164, #163
**Dependencies:** issue-168-shifts-jobs-active-state, issue-203-priority-shift-gate, issue-206-hide-fully-booked-jobs, issue-207-signup-empty-state-notifications, issue-221-signup-grace-minutes
**Branch:** `milestone/phase-4-signup-conflict-ux-coverage`

## Plan
- **Status:** complete
- **Gate summary:** keep Phase 4 tightly scoped to overlap UX clarity and the cancelled-then-re-signup regression backstop. Reuse the existing public preflight overlap detection in `EventSignup` and the authoritative overlap / cancelled-row reactivation rules in `SignUpVolunteerForShifts`, add only the smallest UI-facing conflict detail surface needed for #163, and finish with focused Pest plus one deterministic public-signup Playwright flow.

### Scope
- Keep Phase 4 scoped only to #164 and #163, implemented in this order: #164, then #163.
- Reuse the current split of responsibilities: `EventSignup` stays the advisory/public UX layer, and `SignUpVolunteerForShifts` remains the final authority for overlap and cancelled-signup reactivation outcomes.
- Do not introduce new overlap persistence, new database fields, or a new overlap service just for this milestone.
- Keep the existing returning-volunteer rules from prior milestones intact: `existingShiftIds` are active-only, existing held shifts stay visible, and public final submit stays bound to the verified token plus the reserved shift set.
- Keep Playwright coverage focused on the public signup flow only; organizer settings and Mailpit are out of scope for this phase.

### Dependency Assumptions
- `prefillFromVolunteer()` continues to preload only active signups into `existingShiftIds`, so cancelled historical shifts are treated as newly selected if the volunteer chooses them again.
- `reserveAndAdvance()` continues to use `overlappingShiftIds()` as the public step gate before any reservation is created.
- `submitSignup()` remains bound to the verified email token plus the active reserved shift set from prior milestone `#221`, so this milestone does not need a second submit-integrity mechanism.
- Returning-volunteer visibility rules from `issue-206-hide-fully-booked-jobs` and `issue-221-signup-grace-minutes` stay intact: existing held shifts remain visible even when new public availability rules would normally hide them.

### Breakdown

#### 1. #164 — test cancelled-then-re-signup overlap edge case
- **Goal:** close the coverage gap around a volunteer re-selecting a previously cancelled shift after they already hold a newer overlapping signup, without changing the authoritative reactivation behavior.
- **Implementation areas:**
  - `tests/Feature/Actions/SignUpVolunteerForShiftsTest.php`: extend the existing cancelled-signup reactivation overlap coverage so both reactivation outcomes stay explicit: allowed when the volunteer's remaining active schedule does not overlap, blocked when a newer active signup now overlaps the cancelled row.
  - `tests/Feature/Public/EventSignupTest.php`: add or tighten wizard coverage that proves a cancelled historical shift is not treated as an existing held shift in the UI, can be re-selected and completed when it no longer overlaps the volunteer's active schedule, and is blocked at the shift-selection step when an active existing shift overlaps it.
- **Coverage intent:**
  - Verify the current public preconditions explicitly before asserting the end-to-end flow: the cancelled shift must not appear in `existingShiftIds`, and the overlap computation must treat it as a newly selected shift.
  - Preserve the current rule that cancelled rows can be reactivated only when they still fit the volunteer's active schedule.
  - Prove both observable public outcomes at the wizard boundary: no warning plus successful progression for the non-overlap reactivation case, and visible conflict plus reserve-step block for the overlap case.
  - Keep this issue test-scoped unless the new public-flow coverage exposes a real mismatch that requires a minimal production fix.

#### 2. #163 — overlap warning should identify which specific shifts conflict
- **Goal:** upgrade the public overlap warning from a generic banner plus highlighted cards to explicit conflict identification so volunteers can tell which selected shifts collide before continuing.
- **Implementation areas:**
  - `app/Livewire/Public/EventSignup.php`: add one shared computed conflict map per render, tentatively `overlapConflictMap`, derived from the already loaded selected and existing timed shifts. Keep `overlappingShiftIds()` as a thin derived list from that shared result so the highlight and banner semantics cannot drift.
  - `resources/views/livewire/public/event-signup.blade.php`: render the generic warning with specific shift labels, making clear when a newly selected shift conflicts with another newly selected shift versus an already-held existing signup.
  - `tests/Feature/Public/EventSignupTest.php`: add focused assertions for the new conflict detail text for both new-vs-new and new-vs-existing overlap cases, including cross-midnight coverage where the time comparison is easy to regress.
- **UX rules:**
  - Keep conflict detection client-visible only for the current shift-selection step; do not add a new modal, toast system, or multi-step detour.
  - Identify the conflicting shifts by the same volunteer-visible label source already used in the list today: `job name`, `shift month/day`, and `displayTimeRange($tz)`, matching the existing `shiftTimeLabel` convention.
  - For returning volunteers, the warning names both shifts for clarity but frames the newly selected shift as the actionable item because the existing held shift is locked in this step.
  - Treat the detailed warning as advisory UX only; the final overlap source of truth remains `SignUpVolunteerForShifts` during submit.

### Focused Test Strategy
- `tests/Feature/Actions/SignUpVolunteerForShiftsTest.php`
  - Keep authoritative overlap coverage here, especially cancelled-row reactivation against newer active signups and cross-midnight overlap behavior.
  - Prefer extending the existing overlap section instead of creating a new action test file.
- `tests/Feature/Public/EventSignupTest.php`
  - Reuse one canonical schedule shape for the cancelled-then-re-signup timeline across action and feature coverage so both layers prove the same overlap boundaries.
  - Add public-step assertions for detailed conflict wording when two newly selected shifts overlap.
  - Add public-step assertions for detailed conflict wording when a newly selected shift overlaps an already-held existing shift.
  - Add a success-path regression test for cancelled-then-re-signup when the volunteer's remaining active shift does not overlap.
  - Add a shift-step regression test for cancelled-then-re-signup when a newer active shift does overlap, proving the warning appears and the wizard cannot reserve the conflicting re-selected shift.
- Do not add broad new Livewire or E2E infrastructure if the current feature tests can express the behavior directly.

### Playwright Plan
- Add one deterministic public-signup spec, preferably `e2e/signup-conflict-ux.spec.ts`, rather than overloading `e2e/signup-grace-minutes.spec.ts`.
- Extend `e2e/setup.sh` and the local generated fixture file at `e2e/.generated/fixtures.json` with a dedicated overlap fixture event, verified-token hash, and volunteer state that covers isolated scenario blocks for:
  - two newly selectable overlapping shifts for #163
  - one returning-volunteer scenario where an active existing shift conflicts with a newly selected shift
  - one cancelled historical signup that can be re-selected in the UI but is still blocked by the authoritative overlap rule for #164
- Browser assertions should prove end to end that:
  - the public shift-selection step shows the specific conflicting shift names in the warning
  - deselecting the named conflicting shift clears the warning and allows progression
  - the returning-volunteer conflict message names both the held shift and the newly selected shift while making the new selection the actionable one
  - the cancelled-then-re-signup path succeeds when the re-selected cancelled shift no longer overlaps an active held shift
  - the cancelled-then-re-signup path cannot silently complete into an overlapping schedule when a newer active held shift still conflicts
- Keep this as a single public-flow browser spec with deterministic fixtures; no Mailpit assertions or organizer-side setup UI are needed.

## Implement
- **Status:** complete
- **Iteration:** 1
- **Tasks:**
  - [x] RED: add authoritative action coverage for cancelled-row reactivation succeeding after active overlaps are gone and failing when a newer active signup still overlaps
  - [x] RED: add public wizard coverage proving `prefillFromVolunteer()` stays active-only and cancelled historical shifts behave like new selections
  - [x] GREEN: add one shared `overlapConflictMap` computed surface in `EventSignup` and keep `overlappingShiftIds()` as a thin derivative of it
  - [x] GREEN: render volunteer-visible conflict details with the existing `shiftTimeLabel` convention for new-vs-new and new-vs-existing warnings
  - [x] REFACTOR: keep `SignUpVolunteerForShifts` unchanged as the final overlap authority and reuse one canonical cancelled-re-signup timeline across the new action and feature coverage
- **Gate summary:** `SignUpVolunteerForShifts` remains the only authoritative overlap / cancelled-row reactivation boundary, the public wizard now exposes one shared computed conflict map for both highlight and warning semantics, returning volunteers see actionable conflict copy that names the newly selected shift plus the held conflicting shift, and Phase 4 now explicitly covers both cancelled re-signup outcomes without adding persistence or new services.

## Test
- **Status:** complete
- **Gate summary:** Phase 4 acceptance coverage is now closed across focused Pest and one deterministic public-signup Playwright spec. Authoritative overlap / reactivation enforcement remains covered by focused Pest at the action and feature layers. The browser spec proves the public preflight UX contract only: cancelled shifts reappear as selectable options, overlap warnings name specific conflicts, deselection clears conflicts and restores progression, and blocked overlap cases remain visibly blocked before confirmation.

### Verification
- Ran `vendor/bin/sail bin pint --dirty --format agent`
- Ran `vendor/bin/sail artisan test --compact tests/Feature/Public/EventSignupTest.php tests/Feature/Actions/SignUpVolunteerForShiftsTest.php`
- Ran `bash e2e/setup.sh && vendor/bin/sail npm exec playwright test e2e/signup-conflict-ux.spec.ts`

### Test Results
- `vendor/bin/sail bin pint --dirty --format agent`: passed
- `vendor/bin/sail artisan test --compact tests/Feature/Public/EventSignupTest.php tests/Feature/Actions/SignUpVolunteerForShiftsTest.php`: `124 passed (500 assertions)`
- `bash e2e/setup.sh && vendor/bin/sail npm exec playwright test e2e/signup-conflict-ux.spec.ts`: `4 passed`

### Story Traceability
| Story | Acceptance Criterion | Tests | Status |
|---|---|---|---|
| #164 | Cancelled A + active non-overlapping B can be re-selected without warning and succeeds via reactivation | `tests/Feature/Actions/SignUpVolunteerForShiftsTest.php` — `it('reactivates a cancelled row when the volunteers active schedule no longer overlaps', ...)`; `tests/Feature/Public/EventSignupTest.php` — `it('reactivates a cancelled historical shift when the volunteers active schedule no longer overlaps', ...)`; `e2e/signup-conflict-ux.spec.ts` — `cancelled shift can be re-selected and reactivated when the active schedule no longer overlaps` | covered |
| #164 | Cancelled A + newer overlapping active C is recognized and blocked | `tests/Feature/Actions/SignUpVolunteerForShiftsTest.php` — `it('skips a reactivated shift that now overlaps a newer active signup', ...)` (authoritative); `tests/Feature/Public/EventSignupTest.php` — `it('blocks re-selecting a cancelled historical shift when a newer active signup still overlaps it', ...)`; `e2e/signup-conflict-ux.spec.ts` — `cancelled shift stays blocked when a newer active signup still overlaps it` (public preflight block) | covered |
| #164 | `prefillFromVolunteer()` excludes cancelled shift A from `existingShiftIds` | `tests/Feature/Public/EventSignupTest.php` — `it('reactivates a cancelled historical shift when the volunteers active schedule no longer overlaps', ...)`; `tests/Feature/Public/EventSignupTest.php` — `it('blocks re-selecting a cancelled historical shift when a newer active signup still overlaps it', ...)` | covered |
| #164 | Re-selected cancelled A is treated as a new shift during overlap computation | `tests/Feature/Public/EventSignupTest.php` — `it('blocks re-selecting a cancelled historical shift when a newer active signup still overlaps it', ...)`; `tests/Feature/Public/EventSignupTest.php` — `it('reactivates a cancelled historical shift when the volunteers active schedule no longer overlaps', ...)` | covered |
| #163 | 2+ newly selected overlapping shifts identify which shift(s) they conflict with | `tests/Feature/Public/EventSignupTest.php` — `it('blocks reserveAndAdvance when selected shifts overlap in time', ...)`; `e2e/signup-conflict-ux.spec.ts` — `newly selected overlapping shifts show specific conflict names and clear after deselection` | covered |
| #163 | With 3+ selected shifts where only 2 conflict, only conflicting shifts are marked | `tests/Feature/Public/EventSignupTest.php` — `it('marks only the conflicting shifts when three selected shifts include one non-overlapping option', ...)` | covered |
| #163 | A shift that conflicts with multiple others references all conflicting shifts | `tests/Feature/Public/EventSignupTest.php` — `it('references all newly selected conflicting shifts when one shift overlaps multiple others', ...)`; `tests/Feature/Public/EventSignupTest.php` — `it('uses plural existing-shift wording when one selected shift conflicts with multiple existing shifts', ...)` | covered |

### Remaining Low-Risk Gaps
- The browser spec intentionally does not assert exact localized date/time strings inside conflict copy; those exact labels remain covered in the focused feature tests.
- The browser spec stays public-signup scoped and does not assert confirmation email delivery or organizer-side flows, which matches the milestone boundary.

## Security Audit
- **Status:** complete
- **Gate summary:** Phase 4 passes the reviewed Livewire hardening goals for locked wizard state, step tampering resistance, renderable-shift validation, overlap-step blocking, expired verified-token rejection, and the E2E fixture publication remediation. Live signup-resume hashes are no longer written under `public/`, so the previous public credential leak is closed. The broader `?vt=` replay model remains a deferred medium concern for the security stage.
- **Findings summary:** 0 critical, 0 high, 1 medium, 1 low, 2 info. Medium: the broader `?vt=` replay architecture remains reusable for the post-verification window if the continue URL leaks. Low: reserve/submit throttles remain IP-global rather than event-scoped.
- **Artifact:** `.tall-pipeline/phase-4-signup-conflict-ux-coverage-security-audit.md`

## Decisions

| # | Stage | Decision | Rationale | Affects |
|---|---|---|---|---|
| 1 | plan | Implement #164 before #163 | The cancelled-row overlap invariant is already authoritative in `SignUpVolunteerForShifts`; proving that first gives the UI work a fixed behavioral target and keeps the milestone anchored to the higher-risk correctness case | implement, test |
| 2 | plan | Keep `SignUpVolunteerForShifts` as the final overlap authority and treat any new `EventSignup` conflict detail as advisory only | Prevents UX copy improvements from becoming a second business-rules engine and avoids drift between preflight warnings and final signup enforcement | implement, test, security-audit |
| 3 | plan | Reuse the existing overlap computation and loaded shift collections in `EventSignup` for conflict-detail rendering instead of adding new persistence or a separate overlap service | #163 is a UX clarity change, and the smallest correct path is to enrich the current computed overlap surface rather than invent new infrastructure | implement, test |
| 4 | plan | Returning-volunteer conflict detail should name only the newly selected shift as conflicting with an already-held shift | The existing shift is locked in the UI and not actionable in this step, so the warning should guide the volunteer toward the removable choice | implement, test |
| 5 | plan | Cover #164 through both action-level and public-flow tests, but keep production changes optional unless the new coverage exposes a real behavior gap | The issue is explicitly about edge-case coverage, and current code already suggests the intended rule exists in the action | implement, test |
| 6 | plan | Use a dedicated public-signup Playwright spec with deterministic fixtures for both issues | The overlap UX is a public wizard concern and is clearer to verify in one focused browser flow than by extending the unrelated organizer grace-minutes spec | test |
| 7 | implement | Store overlap warning details as a map keyed by each newly selected shift, with existing conflicting shifts represented only inside that new shift's conflict list | This keeps the UI actionable for returning volunteers, lets `overlappingShiftIds()` derive directly from the same source, and avoids separate list/highlight overlap logic | implement, test |
| 8 | implement | Reuse the same cancelled/overlapping/non-overlapping August 15 timeline in both action and public tests | A shared schedule shape makes the reactivation expectations easier to compare across the advisory wizard layer and the authoritative signup action | implement, test |

## Cross-Milestone Interface

| Category | Items |
|---|---|
| Livewire | `EventSignup::jobs()`, `EventSignup::overlappingShiftIds()`, `EventSignup::reserveAndAdvance()`, `EventSignup::submitSignup()`, `EventSignup::prefillFromVolunteer()` |
| Actions | `SignUpVolunteerForShifts` remains the authoritative overlap and cancelled-signup reactivation boundary; `ProcessVolunteerSignup` continues to reach it from the public wizard |
| Views | `resources/views/livewire/public/event-signup.blade.php` overlap badges and warning copy |
| Tests | `tests/Feature/Public/EventSignupTest.php`, `tests/Feature/Actions/SignUpVolunteerForShiftsTest.php` |
| E2E | planned `e2e/signup-conflict-ux.spec.ts`; `e2e/setup.sh`; `e2e/.generated/fixtures.json` |
| Prior Milestones | `issue-168-shifts-jobs-active-state`, `issue-203-priority-shift-gate`, `issue-206-hide-fully-booked-jobs`, `issue-207-signup-empty-state-notifications`, `issue-221-signup-grace-minutes` |

## Reviews

### plan — 2026-05-07

| # | Perspective | Concern | Severity | Resolution | Rationale |
|---|---|---|---|---|---|
| 1 | Devil's Advocate | UI conflict-detail messaging could drift from the authoritative overlap rules once exact conflicts become user-visible | high | accepted | The plan now requires one shared computed conflict map in `EventSignup`, keeps `overlappingShiftIds()` as a thin derivative, and explicitly validates covered semantics against the submit-path authority |
| 2 | Devil's Advocate | #164 outcome expectations were ambiguous about whether cancelled re-selection should succeed, fail, or be blocked in the wizard | high | accepted | The plan now names both expected public outcomes explicitly: successful reactivation when schedules no longer overlap, and shift-step conflict blocking when an active held shift still overlaps |
| 3 | Scalability Skeptic | Repeating overlap computations and test timelines across layers could create render-time waste and cross-test drift | high | accepted | The plan now uses one computed conflict map per render and calls for one canonical cancelled-re-signup schedule shape reused across action and feature coverage |
| 4 | Junior Developer Lens | The new conflict-detail surface lacked a naming and label-format contract, making implementation and assertions guessy | medium | accepted | The plan now expects a shared `overlapConflictMap`-style surface and anchors labels to the existing `shiftTimeLabel` convention already visible in the UI |
| 5 | Scalability Skeptic | One browser spec covering three overlap cases could become brittle if scenarios are not isolated clearly | medium | accepted | The plan keeps one focused public-signup spec but now requires isolated scenario blocks inside deterministic fixture data so each path remains diagnosable |
| 6 | Devil's Advocate | Phase 4 depended on prior signup contracts that were not stated explicitly, making regressions harder to attribute | medium | accepted | Added a `Dependency Assumptions` section covering active-only preload, reserve-step gating, reserved-shift submit integrity, and returning-volunteer visibility contracts from earlier milestones |

### implement-review — 2026-05-07

| # | Perspective | Concern | Severity | Resolution | Rationale |
|---|---|---|---|---|---|
| 1 | Security Paranoid | The public `state` property could be mutated to call reserve/details/submit methods out of order | high | accepted | Locked `state` and added explicit server-side step guards for `reserveAndAdvance()`, `advanceToConfirmation()`, and `submitSignup()` |
| 2 | Security Paranoid | Shift validation accepted event-owned active IDs that were not actually renderable in the current wizard state | medium | accepted | Reservation validation now requires submitted shift IDs to be in the current renderable public/returning-volunteer-visible shift list as well as event-owned |
| 3 | Accessibility Specialist | The conflict warning was not announced as a live update and conflicting checkboxes were not tied to their specific warning text | high | accepted | Added alert/live-region semantics to the warning block and `aria-describedby` links from conflicting selected checkboxes to per-shift conflict text |
| 4 | Accessibility Specialist | Conflict wording became unclear when one selected shift overlapped multiple other shifts, especially with existing-shift phrasing | medium | accepted | Added grouped conflict phrasing that distinguishes existing held shifts from other selected shifts and uses singular/plural wording for both |

### implement-review-rerun — 2026-05-07

| # | Perspective | Concern | Severity | Resolution | Rationale |
|---|---|---|---|---|---|
| 1 | Privacy Reviewer | `restartSignup()` still preserved volunteer-facing personal data, so restart was not actually fresh | medium | accepted | `restartSignup()` now clears first name, last name, email, and phone in addition to reservation and lookup state |
| 2 | Accessibility Specialist | The conflict description wiring reused the same DOM ID for the hidden checkbox description and visible alert item | high | accepted | Split the IDs into deterministic hidden `shift-conflict-description-*` nodes and visible `shift-conflict-message-*` nodes while keeping `aria-describedby` on the hidden description only |
| 3 | Security Paranoid | `resendVerification()` lacked a step guard, fresh email validation, event-scoped throttles, and binding to an active token | medium | accepted | Resend now requires the pending-verification step, revalidates the email, scopes throttles by event, and requires a live unexpired token for the current event/project/email |
| 4 | Security Paranoid | The broader `?vt=` verification-link architecture still needs hardening | medium | deferred | Explicitly left out of this pass per milestone instruction so it can be addressed as a separate security-stage concern |
| 5 | Accessibility Specialist | The shift picker lacked group-level association with current warning/error text | low | accepted | Added group semantics tying the shift picker to overlap, warning, and validation messages without redesigning the layout |

### implement-review-final-local-pass — 2026-05-07

| # | Perspective | Concern | Severity | Resolution | Rationale |
|---|---|---|---|---|---|
| 1 | Security Paranoid | The initial email lookup throttle was still global to the email instead of event-scoped | medium | accepted | Scoped the submit-email lookup throttle to the current event to match the resend hardening |
| 2 | Security Paranoid | Expired verification tokens could still be trusted in local resume or final submit paths | medium | accepted | Expired `?vt=` tokens no longer resume the wizard and expired verified tokens no longer pass final submit validation |
| 3 | UX Reviewer | Clicking Continue with known overlap conflicts still failed silently from the volunteer’s perspective | medium | accepted | `reserveAndAdvance()` now adds a visible validation error when overlap conflicts already exist |
| 4 | UX Reviewer | Step-guard failures were server-enforced but not visible in the template | medium | accepted | Added a visible `state` error surface so server-side guard failures are shown to the volunteer |
| 5 | Accessibility Specialist | The step-transition live-region label map did not cover all server-driven terminal/interstitial states | medium | accepted | Added minimal labels for `PendingVerification`, `Complete`, and `Expired` without changing the broader flow |

### test-review — 2026-05-07

| # | Perspective | Concern | Severity | Resolution | Rationale |
|---|---|---|---|---|---|
| 1 | QA Skeptic | Cancelled historical shifts were covered logically, but not yet shown as visibly selectable instead of locked/already-held | medium | accepted | Tightened feature coverage to assert the cancelled shift renders as a selectable checkbox without the locked/disabled state while the true active shift remains held |
| 2 | QA Skeptic | Returning-volunteer browser coverage stopped at the conflict warning and did not prove recovery after removing the new conflicting shift | medium | accepted | The Playwright flow now removes the conflicting new shift, proves the warning clears and progression resumes, then reintroduces the conflict to verify the visible block |
| 3 | QA Skeptic | The 3-shift scenario only proved internal Livewire overlap structures, not the visible warning contract | medium | accepted | Added visible-warning assertions showing only the two conflicting shifts appear in the rendered conflict copy while the non-conflicting third shift is omitted |
| 4 | QA Skeptic | Browser/feature blocked re-selection coverage should assert the visible failure contract, not just lack of advancement | medium | accepted | Tightened both layers to assert the volunteer-visible overlap-block message and absence of confirmation progression |
| 5 | QA Skeptic | Reactivation-success coverage did not prove the cancelled row was reactivated instead of duplicated | medium | accepted | Added database assertions that exactly one active signup row exists for the re-selected cancelled shift after success |
| 6 | QA Skeptic | `force: true` checkbox usage weakened confidence in real user actionability | low | accepted | Removed forced checkbox interaction from the Playwright spec after confirming normal interaction succeeds reliably |
| 7 | QA Skeptic | Browser wording could overclaim submit-path authority for blocked reactivation | medium | accepted | Updated the Test gate summary and traceability matrix so Playwright is described as proving public preflight blocking only, with submit authority attributed to focused Pest |
| 8 | QA Skeptic | Browser-level exact full-label ambiguity still looked underspecified | low | deferred | Exact localized full-label formatting is already covered at the feature-test layer; duplicating that exact contract in Playwright would add brittleness without improving the public-flow confidence target for this milestone |

### security-audit-review — 2026-05-08

| # | Perspective | Concern | Severity | Resolution | Rationale |
|---|---|---|---|---|---|
| 1 | Security Paranoid | `?vt=` resume links remain reusable bearer credentials during the post-verification window | medium | deferred | The public fixture leak is fixed and no critical/high finding remains, but the broader session-binding / one-time-use redesign is outside Phase 4 scope and stays documented in the audit artifact |
| 2 | Scalability Skeptic | Pending-verification polling still issues one token lookup every 3 seconds per open tab for up to 10 minutes | medium | deferred | This is a residual availability concern rather than a Phase 4 integrity bypass, so it is recorded for later optimization instead of blocking the milestone |
| 3 | Scalability Skeptic | Reserve / submit throttles remain IP-scoped instead of event-scoped | low | deferred | The milestone already event-scoped lookup and resend throttles; reserve / submit scoping remains a non-blocking availability improvement noted in the audit artifact |
| 4 | Scalability Skeptic | The E2E harness still uses one destructive setup and one shared generated fixture file for all runs | low | rejected | This is test-harness operational debt, not a production security issue in the shipped Phase 4 surface, so it does not affect the milestone security gate |

## Feedback Loops

| # | Date | Direction | Trigger | Fix | Resolution |
|---|---|---|---|---|---|
| 1 | 2026-05-07 | harden | Accepted implementation-review findings for step-boundary hardening, renderable-shift validation, and conflict accessibility semantics | Locked the server-owned wizard state, added explicit step guards, narrowed reservation validation to renderable shift IDs, and linked live conflict text to the conflicting inputs with clearer grouped wording | Fixed in the implement stage; focused Pest coverage added, Playwright and security follow-up still pending |
| 2 | 2026-05-07 | harden | Second implementation-review fix pass covering fresh restart semantics, unique conflict-description IDs, resend verification hardening, and shift-group associations | Cleared volunteer-facing fields on restart, split visible/hidden conflict IDs, bound resend to the pending verification flow with event-scoped throttles and active-token checks, and linked the shift group to its current warning/error surfaces | Fixed in the implement stage; the broader `?vt=` architecture remains intentionally deferred to a later security concern |
| 3 | 2026-05-07 | harden | Final local review pass highlighted event-scoped lookup throttling, expired-token trust, silent overlap blocking, missing visible state-guard errors, and incomplete step announcements | Scoped lookup throttles by event, rejected expired verified tokens in resume/submit, surfaced overlap-block and state-guard errors in the UI, and added live-region labels for pending, complete, and expired states | Fixed in the implement stage; only the broader `?vt=` replay architecture remains intentionally deferred to security audit |
| 4 | 2026-05-07 | tighten | Accepted test-review follow-up for selectable cancelled-shift UX, visible 3-shift conflict scope, blocked-flow messaging, reactivation non-duplication, and browser recovery after deselection | Tightened feature assertions for fresh cancelled-shift rendering, visible conflict-copy scope, explicit overlap-block messaging, and non-duplicated reactivation; updated Playwright to use normal checkbox interaction, prove recovery after unselecting a conflicting new shift, and describe blocked browser coverage as preflight-only | Fixed in the test stage; exact localized full-label verification remains intentionally concentrated in feature tests to avoid brittle browser assertions |
| 5 | 2026-05-07 | remediate | Security audit high finding on publicly exposed E2E fixtures | Moved generated Playwright fixtures from `public/e2e-fixtures.json` to local-only `e2e/.generated/fixtures.json`, switched affected specs to load fixtures from disk, and ignored the generated directory | Fixed in the security remediation pass; the broader `?vt=` replay model remains intentionally deferred |
