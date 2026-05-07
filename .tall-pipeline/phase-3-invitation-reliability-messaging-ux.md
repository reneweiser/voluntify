# Milestone: phase-3-invitation-reliability-messaging-ux — Phase 3: Invitation Reliability & Messaging UX

**GitHub Issues:** #217, #218, #220, #219
**Features:** #217, #218, #220, #219
**Dependencies:** m12-guest-lists, m13-polish, m19-non-expiring-magic-links, issue-193-guest-mail-magic-link
**Branch:** `phase-3-invitation-reliability-messaging-ux`

## Plan
- **Status:** complete
- **Gate summary:** separate guest invitation dispatch from delivery, expose organizer-visible failure and resend recovery in the guest-list UI, align guest-list wording with the already-shipped always-on sending semantics, and tighten reminder and guest-pass email CTAs without broadening scope beyond the existing guest-list, reminder, and mail-template systems.

### Scope
- Keep Phase 3 scoped to the four requested issues, implemented in this order: #217, #218, #220, #219.
- Reuse the existing guest-list grouping model of one guest invitation email per recipient address.
- Keep guest-list internal identifiers and persisted status values stable: `confirmed`, `confirmed_at`, `ConfirmGuestList`, and `confirmGuestList()` remain unchanged.
- Limit mail-template work to reminder defaults plus reminder notification rendering; do not migrate or rewrite existing custom event email templates.
- Keep browser coverage focused on organizer-facing guest-list UI transitions and Mailpit-backed reminder rendering; do not add brittle browser tests for SMTP transport failures.

### Invitation State Contract
- `queued` means the grouped recipient invitation job was dispatched successfully for that recipient sibling set. It is a dispatch-time state only.
- `sent` means the mail send call completed inside the queued job without throwing for that recipient sibling set.
- `failed` means the grouped recipient job reached terminal failure handling after exhausting retries for that recipient sibling set.
- Do not treat `sent` as provider-confirmed delivery, inbox placement, open, or click tracking. This milestone only models application-known dispatch, in-job send success, and terminal job failure.

### Grouped Recipient Invariants
- For grouped invitation sends, all `GuestEntry` rows in the same `GuestList` sharing the same recipient email form one recipient sibling set.
- A recipient sibling set transitions through `queued`, `sent`, and `failed` together. Mixed per-row states inside the same sibling set are invalid for grouped sends.
- Correcting a failed recipient email and resending operates on the failed recipient sibling set that shared the original address, not on one arbitrary row.
- Organizer-visible resend affordances are only available for recipient sibling sets currently in terminal `failed` state.

### Legacy / Backfill Interpretation
- Pre-migration rows with only historical `invitation_sent_at` data should be interpreted as legacy-sent for organizer UI badges and should not be treated as failed or backlog by default.
- Pre-migration rows with no invitation timestamps remain backlog/pending unless the surrounding guest-list state proves they were never eligible to send.
- Bulk actions must remain sane against mixed legacy data: legacy-sent rows stay excluded from resend and pending-send actions unless they later enter the new terminal failed flow.

### Breakdown

#### 1. #217 — surface failed guest invitations and allow resend
- **Goal:** make guest invitation delivery outcomes explicit so organizers can see failed rows, correct the address inline, and resend without leaving `GuestListShow`.
- **Implementation areas:**
  - `database/migrations/*guest_entries*`: add additive invitation delivery columns so dispatch, delivery success, and terminal failure are not conflated.
  - `app/Models/GuestEntry.php`: add casts and derived helpers for backlog, queued, sent, and failed invitation states while keeping `guestPassUrl()` behavior intact.
  - `app/Jobs/ConfirmGuestListJob.php`, `app/Jobs/SendGuestInvitationsJob.php`: stop writing sent state at dispatch time; persist `queued` when dispatch succeeds, persist `sent` only after the mail send call returns without exception inside the job, and persist `failed` only from final failure handling after retries are exhausted for the full recipient sibling set.
  - `app/Actions/AddGuestEntry.php`, `app/Actions/UpdateGuestEntry.php`: for confirmed guest lists, queue invitations without marking them delivered; when a failed address is corrected, clear the failed state and requeue for the recipient sibling set that failed together.
  - `app/Livewire/Projects/GuestListShow.php` and `resources/views/livewire/projects/guest-list-show.blade.php`: add visible invitation status, resend recipient-group action, failed-row recovery flow, and keep `Send Pending Invitations` limited to recipient sibling sets that are still backlog and not already queued or failed.
- **State and retry rules:**
  - Retryable attempts remain queued/in-flight from the organizer perspective; they must not surface as failed during intermediate retries.
  - The organizer-visible failed badge and resend path appear only after terminal failure.
  - Resend actions target only failed recipient sibling sets.
  - Bulk pending send excludes recipient sibling sets already queued, already sent, or terminally failed.
  - State persistence should be expressed as set-based updates per recipient sibling set, not row-by-row loops that can drift within a grouped send.
- **Focused tests:**
  - `tests/Feature/Jobs/SendGuestInvitationsJobTest.php`
  - `tests/Feature/Jobs/ConfirmGuestListJobTest.php`
  - `tests/Feature/Livewire/GuestListShowTest.php`
  - `tests/Feature/GuestListLifecycleTest.php`
  - `tests/Feature/Models/GuestEntryTest.php`
- **Playwright:** add a new organizer-focused spec for guest-list invitation reliability that seeds deterministic failed and backlog rows, verifies failed-state visibility, inline email correction, resend, and that `Send Pending Invitations` excludes failed rows. Do not try to reproduce SMTP failure in-browser.

#### 2. #218 — rename guest-list confirm flow to active sending semantics
- **Goal:** make the guest-list UI describe the real behavior already in production: activating sending is an ongoing state, not a one-time finalization step.
- **Implementation areas:**
  - `app/Enums/GuestListStatus.php`: localize label output and change visible confirmed wording to active-sending semantics.
  - `app/Livewire/Projects/GuestListShow.php`: update the post-activation flash message only.
  - `resources/views/livewire/projects/guest-list-show.blade.php`: update the activation button and confirm dialog copy.
  - `resources/views/livewire/projects/guest-list-index.blade.php`: consume the updated enum label output for index badges.
  - `lang/de.json` and English locale files already used by the repo for UI strings.
- **Focused tests:**
  - `tests/Feature/Livewire/GuestListShowTest.php`
  - `tests/Feature/Livewire/GuestListIndexTest.php`
  - `tests/Feature/GuestListLifecycleTest.php` where it currently relies on old confirm wording or behavior expectations.
- **Playwright:** cover the detail-page activation wording and the index/detail badge wording in the same organizer guest-list spec introduced for #217 instead of creating a second browser test.

#### 3. #220 — add portal link to pre-shift reminder mails
- **Goal:** let reminder recipients jump directly into their volunteer portal from both 24-hour and 4-hour reminders using the same non-expiring token pattern already used elsewhere.
- **Implementation areas:**
  - `app/Actions/SendPreShiftReminders.php`: generate a fresh plain magic-link token per reminder send and pass it into the notification.
  - `app/Notifications/PreShiftReminder.php`: populate `portal_link`, render the reminder body with that URL, and add a `MailMessage::action()` CTA consistent with `SignupConfirmation` so the reminder always includes a portal link even when the custom body does not render the placeholder.
  - `app/Services/EmailTemplateRenderer.php`: extend only the default `pre_shift_reminder_24h` and `pre_shift_reminder_4h` bodies with `{{portal_link}}`; custom templates stay body-unchanged unless they already use `{{portal_link}}`.
- **Focused tests:**
  - `tests/Feature/Actions/SendPreShiftRemindersTest.php`
  - `tests/Feature/Notifications/PreShiftReminderTest.php`
  - `tests/Feature/Services/EmailTemplateRendererTest.php`
- **Playwright:** extend `e2e/pre-shift-reminder-relative-day.spec.ts` or add a sibling reminder-mail Mailpit spec to assert the reminder email contains the portal CTA/link for both reminder windows. Keep this as Mailpit/API-level browser coverage, not a portal-auth E2E.

#### 4. #219 — render guest-pass fallback link as CTA button
- **Goal:** upgrade the guest invitation fallback link introduced in #193 from a raw URL to a clear, localized mail CTA.
- **Implementation areas:**
  - `resources/views/mail/guest-invitation.blade.php`: replace the raw `<a>` with `<x-mail::button>` and localize the hint copy and button label.
  - Guest invitation locale strings in the same translation files used for #218.
- **Focused tests:**
  - `tests/Feature/Mail/GuestInvitationMailTest.php`
  - `tests/Feature/Jobs/SendGuestInvitationsJobTest.php` where mail rendering assumptions are asserted
  - `tests/Feature/GuestListLifecycleTest.php` as a regression check on grouped invitation flow
  - `tests/Feature/Http/GuestPassControllerTest.php` stays as the route-behavior backstop from #193
- **Playwright:** no dedicated new browser test required if the reminder Mailpit coverage from #220 and the focused mail rendering tests stay green; this issue is primarily HTML mailable markup, not interactive app behavior.

## Implement
- **Status:** complete
- **Tasks:**
  - [x] Execute #217 first: additive queued/failed invitation persistence, sibling-set resend handling, organizer failed visibility, and deterministic browser coverage.
  - [x] Review fix pass for #217: atomic pending/failed claims, exact-row success/failure updates, in-method Livewire authorization, internal-only invitation state writes, failed-set-only correction, and recovery-flow accessibility polish.
  - [x] Finalize #217: confirmed-list email change recovery for sent/queued rows, dispatch-rollback safety, stale-job no-op behavior, reliable save feedback focus target, and no-op resend messaging.
  - [x] Execute #218: rename visible guest-list confirm semantics to sending-active wording while keeping lifecycle identifiers stable.
  - [x] Execute #220: fresh non-expiring reminder portal links, default reminder `{{portal_link}}` support, and consistent reminder CTA button rendering.
  - [x] Execute #219: replace raw guest-pass fallback URLs with a localized guest-pass CTA button and cover it through focused Pest + Mailpit Playwright verification.
  - [x] Keep #218 scoped to visible wording, localized labels via existing `__()` usage, and the existing organizer guest-list browser spec.
  - [x] Preserve existing grouped guest invitation mail behavior and existing guest-pass route semantics.

## Test
- **Status:** complete
- **Gate summary:** combined focused Pest coverage and both milestone Playwright specs passed for #217, #218, #220, and #219. The milestone is ready to advance to security audit with organizer resend/recovery, sending-active wording, reminder portal links, and guest-pass CTA coverage verified end to end.

### Verification
- `vendor/bin/sail artisan test --compact tests/Feature/Actions/QueueGuestInvitationSiblingSetTest.php tests/Feature/Actions/AddGuestEntryTest.php tests/Feature/Actions/UpdateGuestEntryTest.php tests/Feature/Actions/SendPreShiftRemindersTest.php tests/Feature/Jobs/SendGuestInvitationsJobTest.php tests/Feature/Jobs/ConfirmGuestListJobTest.php tests/Feature/Livewire/GuestListShowTest.php tests/Feature/Livewire/GuestListIndexTest.php tests/Feature/GuestListLifecycleTest.php tests/Feature/Models/GuestEntryTest.php tests/Feature/Notifications/PreShiftReminderTest.php tests/Feature/Services/EmailTemplateRendererTest.php tests/Feature/Mail/GuestInvitationMailTest.php tests/Feature/Http/GuestPassControllerTest.php`
- `bash e2e/setup.sh && vendor/bin/sail npm exec playwright test e2e/guest-list-invitation-reliability.spec.ts e2e/pre-shift-reminder-relative-day.spec.ts`

### Test Results
- Focused Pest coverage passed: 155 tests, 474 assertions.
- Playwright coverage passed: 4 tests across `e2e/guest-list-invitation-reliability.spec.ts` and `e2e/pre-shift-reminder-relative-day.spec.ts`.

### #219 Results
- The guest invitation markdown mail now renders the browser fallback as a localized `Open Guest Pass` CTA button using Laravel's existing `<x-mail::button>` mail component instead of exposing the raw signed URL as link text.
- The browser-fallback hint copy is now wrapped in `__()` to follow the repo's existing source-string localization convention, without introducing new route or token behavior.
- Focused Pest coverage now asserts the localized hint text and CTA button label in `GuestInvitationMailTest`, `SendGuestInvitationsJobTest`, and `GuestListLifecycleTest`.
- The organizer guest-list Playwright suite now also verifies a deterministic guest invitation email in Mailpit contains the CTA button and signed guest-pass browser URL, giving #219 an end-to-end verification path without changing guest-pass route behavior.

### #220 Results
- `SendPreShiftReminders` now generates a fresh non-expiring magic-link token per reminder send and passes the plain token into `PreShiftReminder`.
- `PreShiftReminder` now renders `portal_link` with the volunteer portal URL and always appends a `Portal öffnen` CTA button, so custom reminder bodies stay unchanged unless they already use `{{portal_link}}` while still guaranteeing a portal entry point.
- `EmailTemplateRenderer` now adds `{{portal_link}}` only to the default `pre_shift_reminder_24h` and `pre_shift_reminder_4h` bodies.
- Focused Pest coverage now proves token generation, non-expiring token persistence, default-body portal-link rendering, custom-template opt-in placeholder rendering, and persistent CTA-button behavior.
- Mailpit Playwright coverage now checks both 24-hour and 4-hour reminder emails for relative-day wording plus the rendered portal CTA/link without adding a portal-auth browser flow.

### #217 Results
- Focused invitation-state coverage added in `SendGuestInvitationsJobTest`, `ConfirmGuestListJobTest`, `GuestListShowTest`, `GuestListLifecycleTest`, `GuestEntryTest`, `AddGuestEntryTest`, and `UpdateGuestEntryTest`.
- Added `QueueGuestInvitationSiblingSetTest` coverage for atomic pending/failed claim behavior and duplicate-dispatch prevention.
- New browser coverage added in `e2e/guest-list-invitation-reliability.spec.ts` with deterministic failed, pending, and sent recipient-group fixtures.
- Livewire review-fix coverage now includes post-mount permission revocation and exact claimed-row persistence for mixed same-email groups.
- Final #217 coverage now also proves confirmed-list email changes from sent/queued rows requeue correctly, stale queued jobs no-op after rows move, dispatch failures restore rows out of queued state, and failed-group correction to an already-used address only reclaims the intended failed rows.

### #218 Results
- Updated guest-list status labels from raw lifecycle wording to localized sending-state wording via `GuestListStatus::label()` while keeping `confirmed` and related internals unchanged.
- Updated the draft detail-page action button, confirm dialog copy, and post-activation flash message to describe ongoing sending semantics instead of one-time confirmation semantics.
- Extended `GuestListShowTest`, `GuestListIndexTest`, and `e2e/guest-list-invitation-reliability.spec.ts` to cover draft and active wording on both detail and index views, including the activation dialog and success flash.

## Security Audit
- **Status:** complete
- **Gate summary:** no unresolved critical or high findings were identified across the Phase 3 invitation-state, resend/recovery, reminder portal-link, and guest-pass CTA surfaces. The milestone can advance to complete with two non-blocking follow-up items on reminder-send acknowledgement timing and overlap hardening.

### Audit Artifact
- `.tall-pipeline/phase-3-invitation-reliability-messaging-ux-security-audit.md`

### Findings Summary
- Medium: `SendPreShiftReminders` flips reminder-sent flags before successful token generation / notification dispatch, so transient failures can suppress future reminders for that signup.
- Low: repo-level `npm audit` still reports known frontend dependency advisories (`axios`, `follow-redirects`, `postcss`); these are not introduced by Phase 3 but remain open.
- Info: organizer Livewire mutators re-authorize correctly, invitation-state fields are no longer mass assignable, stale invitation jobs no-op cleanly, guest-pass surfaces keep `no-store` / `noindex` headers, and reminder / guest-pass bearer-link replay remains the accepted residual risk from prior magic-link architecture.

## Decisions

| # | Stage | Decision | Rationale | Affects |
|---|---|---|---|---|
| 1 | plan | Keep grouped guest invitation delivery per recipient email and persist delivery outcomes back onto each matching `GuestEntry` | Preserves the current mail UX and minimizes scope while still making failure recovery visible per row | implement, test |
| 2 | plan | Treat `invitation_sent_at` as actual successful delivery time going forward, and introduce separate queued and failed timestamps instead of a new enum table or raw provider error UI | The current bug comes from conflating queue dispatch with delivery; additive timestamps are the smallest forward path | implement, test, security-audit |
| 3 | plan | `queued`, `sent`, and `failed` reflect application-known dispatch, in-job send success, and terminal job failure only; they do not imply provider-confirmed inbox delivery | Keeps the model truthful to what the app can actually observe and avoids over-promising delivery certainty in the UI | implement, test |
| 4 | plan | Recipient sibling sets are the unit of invitation state transition, correction, resend, and batch persistence for grouped guest invitation sends | Preserves grouped-email semantics and prevents row-level drift within one recipient send | implement, test |
| 5 | plan | `Send Pending Invitations` continues to target only backlog recipient groups and must not auto-resend failed or already queued groups | Keeps failed invitations visible instead of hiding them inside a bulk catch-all action and avoids duplicate dispatch | implement, test |
| 6 | plan | Correcting the email of a failed recipient group clears its failed marker and immediately requeues delivery if the guest list is already sending-active | Matches organizer expectations from #217 and reuses the existing inline edit flow instead of adding a second repair UI | implement, test |
| 7 | plan | Keep internal guest-list lifecycle identifiers (`confirmed`, `confirmed_at`, `ConfirmGuestList`, `confirmGuestList`) stable and change only visible copy | #218 is a semantics and translation fix, not an internal API rename | implement, test |
| 8 | plan | Legacy rows with only historical `invitation_sent_at` data are interpreted as legacy-sent for badges and excluded from failed/backlog recovery actions unless they later enter the new failure flow | Prevents old rows from showing nonsensical failed/pending UI after the additive migration ships | implement, test |
| 9 | plan | Reminder portal links use freshly generated non-expiring magic-link tokens per send, default reminder templates gain `{{portal_link}}`, and custom templates remain body-unchanged unless they already render the placeholder while the notification still appends the CTA button | Aligns reminder behavior with existing portal-link notifications and avoids mutating user-authored templates while guaranteeing a portal link is still present | implement, test, security-audit |
| 10 | plan | Use existing repo conventions plus AGENTS and Boost guidance for this milestone plan because no repo-local `tall-foundations.md` reference exists in the project search | Prevents the milestone from blocking on a missing local reference file while keeping the plan anchored to current repo patterns | plan |

## Cross-Milestone Interface

| Category | Items |
|---|---|
| Models | `GuestEntry`, `GuestGroup`, `GuestList`, `MagicLinkToken`, `Volunteer`, `ShiftSignup` |
| Actions | `AddGuestEntry`, `UpdateGuestEntry`, `ConfirmGuestList`, `SendPreShiftReminders`, `GenerateMagicLink` |
| Jobs | `ConfirmGuestListJob`, `SendGuestInvitationsJob` |
| Livewire | `Projects\GuestListShow`, `Projects\GuestListIndex` |
| Mail / Notifications | `GuestInvitationMail`, `PreShiftReminder`, `SignupConfirmation`, `EmailTemplateRenderer`, `resources/views/mail/guest-invitation.blade.php` |
| Browser / Mailpit | `e2e/pre-shift-reminder-relative-day.spec.ts`, planned `e2e/guest-list-invitation-reliability.spec.ts` |
| Prior Milestones | `m12-guest-lists`, `m13-polish`, `m19-non-expiring-magic-links`, `issue-193-guest-mail-magic-link` |

## Reviews

### plan — 2026-05-07

| # | Perspective | Concern | Severity | Resolution | Rationale |
|---|---|---|---|---|---|
| 1 | Devil's Advocate | Row-level writes could let grouped-recipient siblings drift into contradictory invitation states or allow resend from the wrong row | high | accepted | The plan now defines the recipient sibling set as the invariant unit for queue, sent, failed, correction, resend, and set-based persistence so grouped invitation behavior cannot fragment |
| 2 | Scalability Skeptic | Retry handling and bulk send paths could create duplicate dispatches or noisy failed badges if queued vs failed semantics stay ambiguous | medium | accepted | The plan now keeps retryable attempts in queued/in-flight state, surfaces failed only after terminal failure, excludes failed and already queued groups from bulk pending send, and prefers set-based updates over row loops |
| 3 | Junior Developer Lens | Reminder portal-link work could be misread as permission to rewrite custom template bodies or as proof of inbox delivery once the send call succeeds | medium | accepted | The plan now spells out that `sent` is only in-job send success, not provider-confirmed delivery, and that only default reminder templates gain `{{portal_link}}` while custom bodies stay unchanged and still receive the appended CTA button |

### implement-review — 2026-05-07

| # | Perspective | Concern | Severity | Resolution | Rationale |
|---|---|---|---|---|---|
| 1 | Concurrency Review | Queue claims were dispatching before queued-state persistence and allowed duplicate replay of the same recipient set | high | accepted | The implementation now uses explicit pending-vs-failed claim methods that atomically claim rows before dispatch and make duplicate follow-up claims no-ops |
| 2 | Delivery Accounting Review | Success/failure writes were broader than the actually mailed rows and could mutate unrelated same-email rows | high | accepted | The job now carries the exact claimed entry IDs and persists sent/failed state only for that claimed set, including mixed same-email cases where another row lacked a QR token |
| 3 | Authorization Review | `GuestListShow` relied on `mount()` authorization only, so later Livewire actions could ignore revoked permissions | high | accepted | All state-changing public methods now re-authorize guest-list management and tests cover permission revocation after mount |
| 4 | Security Review | Invitation state timestamps were mass assignable | medium | accepted | Invitation delivery timestamps are now internal-only writes on `GuestEntry` |
| 5 | Data Integrity Review | Failed-email correction could clear unrelated sent or queued rows during state drift | medium | accepted | Failed-set correction is now scoped to the exact failed rows only and requeueing uses the explicit failed-claim path |
| 6 | Accessibility Review | Recovery flow feedback and focus behavior were not robust for keyboard and screen-reader users | medium | accepted | The guest-list UI now adds live-region semantics, explicit labels for icon-only controls, failed-email side-effect description, and deliberate focus movement on edit/recovery interactions |

### final-217-review — 2026-05-07

| # | Perspective | Concern | Severity | Resolution | Rationale |
|---|---|---|---|---|---|
| 1 | Delivery Consistency Review | Confirmed-list email changes from sent or queued rows could keep the moved row blocked by stale delivery state and let stale queued jobs target the old address | high | accepted | Changed rows now clear their prior invitation state before re-claiming the new address, and jobs re-bind to current queued email/state at execution so stale jobs no-op |
| 2 | Queue Robustness Review | Dispatch-time exceptions could leave rows stuck in queued state | high | accepted | Claiming now restores rows out of queued state if dispatch throws, preserving pending rows as pending and failed resend rows as failed |
| 3 | UX Review | Successful inline saves could dispatch focus feedback before a stable target existed | medium | accepted | `saveEntry()` now flashes confirmation before dispatching the feedback-focus event, and browser coverage asserts the success message appears |
| 4 | Product Edge Case Review | Correcting a failed group to an email already used elsewhere in the same guest list could unintentionally merge state across recipient sets | medium | accepted | Exact-id failed claims now requeue only the corrected failed rows, so the concern is resolved cleanly inside #217 without broadening product behavior |

### implement-review-218 — 2026-05-07

| # | Perspective | Concern | Severity | Resolution | Rationale |
|---|---|---|---|---|---|
| 1 | Product Copy Review | Renaming the visible confirm flow could accidentally leak into internal lifecycle names or broaden behavior beyond wording | medium | accepted | The implementation changed only user-facing button, dialog, badge, and flash copy while preserving `confirmed`, `confirmed_at`, `ConfirmGuestList`, and `confirmGuestList()` unchanged |
| 2 | Localization Review | New status wording could bypass the repo's existing translation convention if hard-coded outside views | medium | accepted | The enum labels and flash message now use `__()` so the new wording follows the same translation-keyless convention already used across the repo |
| 3 | Browser Coverage Review | The new activation wording could regress on the organizer UI if only covered in PHP tests | low | accepted | The existing guest-list Playwright spec now also asserts index/detail badges, activation button text, confirm dialog copy, and the post-activation flash message |

### test-review-hardening — 2026-05-07

| # | Perspective | Concern | Severity | Resolution | Rationale |
|---|---|---|---|---|---|
| 1 | Determinism Review | Mailpit assertions were polling the global inbox or taking the first recipient match, which could mask stale or duplicate messages | medium | accepted | The Playwright specs now poll for exactly one message per expected recipient and fail if duplicate recipient mail exists, so the checks bind to deterministic fixture mail instead of generic inbox activity |
| 2 | Reminder UX Review | The reminder E2E only checked mail content and did not prove that a rendered portal CTA leads to a usable destination | medium | accepted | The reminder Playwright spec now extracts the portal URL from the Mailpit HTML, opens it in the browser, and asserts the volunteer portal loads with the expected volunteer context |
| 3 | Invitation Recovery Review | The failed-address repair E2E proved row-state change but not that the repaired recipient actually received a usable invitation | medium | accepted | The guest-list Playwright spec now waits for the repaired recipient email, asserts guest-pass CTA links were rendered for both repaired rows, and opens a repaired guest-pass URL to confirm the page loads successfully |
| 4 | Isolation Review | Extra Mailpit resets inside the individual specs or after fixture generation could further isolate tests | low | rejected | `e2e/setup.sh` already clears Mailpit before deterministic fixture mail is generated; clearing again inside this combined run would erase the seeded reminder and sent-invite evidence that the specs are intentionally validating |

### security-audit — 2026-05-07

| # | Perspective | Concern | Severity | Resolution | Rationale |
|---|---|---|---|---|---|
| 1 | Reminder Delivery Review | `SendPreShiftReminders` marks reminder flags as sent before notification success and does not restore them on failure | medium | backlog | A transient token-generation or mail failure can permanently suppress later reminder retries for the same signup, so the delivery acknowledgement needs to move after successful send or be rolled back on exception |
| 2 | Dependency Hygiene Review | `npm audit` still reports known frontend advisories in `axios`, `follow-redirects`, and `postcss` | low | backlog | These findings are repo-level rather than Phase 3-specific, so they do not block the milestone but should be remediated separately |
| 3 | Boundary Review | Organizer resend / edit actions, signed guest-pass CTAs, and reminder portal-link rendering could have widened access or leaked state | info | accepted | Manual review found per-method Livewire authorization, locked identifiers, internal-only invitation state writes, exact claimed-row job persistence, and `no-store` / `noindex` hardening intact across the new surfaces |

## Feedback Loops

| # | Date | Direction | Trigger | Fix | Resolution |
|---|---|---|---|---|---|
| 1 | 2026-05-07 | implement -> plan alignment | The pre-existing inline edit flow only updated one row, which would violate the sibling-set repair invariant for failed grouped recipients | Applied failed-email correction to the full recipient sibling set before requeueing the corrected address | resolved |
| 2 | 2026-05-07 | implement-review -> implement | Accepted review findings identified queue claiming races, overly broad success/failure updates, missing in-method authorization, mass-assignable invitation state, and accessibility gaps | Reworked queue claiming around explicit atomic pending/failed claim paths, scoped job persistence to claimed IDs, re-authorized every mutator, removed invitation state from mass assignment, and added recovery-flow accessibility/focus handling | resolved |
| 3 | 2026-05-07 | final-review -> implement | Final review found confirmed-list email changes could strand sent/queued rows, dispatch exceptions could leave queued rows stuck, stale jobs could still target moved rows, and same-list failed-address correction needed exact targeting | Added exact-id pending/failed claim methods, restored state on dispatch exceptions, rebound jobs to current queued email/state at execution time, flashed save confirmation before focus handoff, and targeted failed corrections by exact claimed IDs | resolved |
