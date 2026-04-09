# Milestone: m17-reliability-quick-wins — Reliability & Quick Wins

**GitHub Milestone:** [#2](https://github.com/reneweiser/voluntify/milestone/2)
**Features:** #112, #113, #114, #122, #135, #136, #140, #143
**Dependencies:** m16 (all Phase 2 complete)

## Plan
- **Status:** complete
- **Gate summary:** 8 issues across 4 batches; no schema changes; mostly action/notification/frontend work

### Batch 1: Mail Reliability (#112, #113)

**#112 — Immediate cancellation notification for organizers**
- New notification: `ImmediateCancellationNotification` (queued, uses org mailer)
- New listener: `NotifyOrganizersOfCancellation` (handles `SignupCancelled`, runs after commit)
- Recipients: event `notification_email` addresses, fallback to `project->contact_email`
- Content: volunteer name, event, job, shift date/time — single cancellation (not digest)
- Tests: listener dispatches notification, notification content, recipient resolution

**#113 — Retry/failure strategy for queued notifications**
- New trait: `HasRetryStrategy` — adds `$tries = 3`, `$backoff = [30, 60, 300]`, `failed()` logging
- Apply to all 10 `ShouldQueue` notifications
- `failed()` method: logs error with notification class, notifiable info, exception
- Tests: trait properties accessible, failed() logs correctly

### Batch 2: Data Integrity (#114, #143)

**#114 — Idempotency guards on event listeners**
- `RecordActivityListener`: add duplicate-check guard per handler (check existing ActivityLog by event+subject)
- `SendCancellationConfirmation`: add `cancelled_at` re-check guard
- Low risk — defensive hardening, not fixing active bugs
- Tests: verify duplicate events don't create duplicate logs/notifications

**#143 — Block profile deletion with non-cancellable shifts**
- Add `hasNonCancellableActiveShifts()` check to `DeleteVolunteerProfile` action
- Logic: active signups where shift not completed AND not cancellable → block
- "Completed" = `ends_at < now()` or `shift_date` in the past
- Throw `DomainException` with German message from issue spec
- Update `VolunteerPortal` to catch and display the blocking message
- Tests: deletion blocked/allowed scenarios, completed shifts don't block

### Batch 3: Scanner UX (#135, #136)

**#135 — Quantity gear pickup counter with cooldown in VA scanner**
- Update `scanner-app.blade.php`: show `X / Y abgeholt` counter, disable button at max
- Update `alpine-scanner.ts`: add 2-second cooldown after pickup, counter calculation from pickups array
- `ScannerDataController` already sends `quantity_entitled` and `pickups` — no backend change needed
- Tests: Vitest for counter logic, cooldown behavior

**#136 — Auto-pause camera after inactivity**
- Update `alpine-scanner.ts`: add inactivity timer (2 min default)
- On timeout: stop camera tracks, show "Tap to resume" overlay
- On tap/click on viewfinder: restart camera, reset timer
- Page Visibility API: pause immediately on background, show overlay on return
- Reset timer on: successful scan, tab switch, any interaction
- Update `scanner-app.blade.php`: add pause overlay markup
- Tests: Vitest for timer logic, visibility change handling

### Batch 4: General UX (#122, #140)

**#122 — Searchable timezone picker**
- Replace native `<select>` in `project-show.blade.php` with Alpine-powered searchable dropdown
- Typeahead filtering of ~400 timezones
- Keyboard navigable (arrow keys + enter)
- Dark mode compatible
- Wire to `projectForm.timezone` via `wire:model`
- Tests: Livewire test for timezone persistence

**#140 — Back navigation after completed signup**
- Add "Back to project" link in EventSignup Complete state
- Links to `/p/{slug}` (project public page)
- Project slug available from `$event->project->slug`
- Tests: Livewire test verifying link presence in complete state

## Implement
- **Status:** complete
- **Gate summary:** 4 batches, 5 commits, all 1811 tests green
- **Tasks:**
  - [x] Batch 1: #112 ImmediateCancellationNotification + #113 HasRetryStrategy trait
  - [x] Batch 2: #114 idempotency guards + #143 deletion blocking
  - [x] Batch 3: #135 gear counter + #136 camera auto-pause
  - [x] Batch 4: #122 timezone picker + #140 signup back nav

## Test
- **Status:** complete (covered inline with implementation)

## Security Audit
- **Status:** complete
- **Gate summary:** 0 critical, 1 high (fixed), 1 medium (fixed), 2 low (accepted), 1 info

## Decisions

| # | Stage | Decision | Rationale | Affects |
|---|---|---|---|---|
| 1 | plan | Trait for retry strategy vs per-class config | DRY — 10 notifications share identical config | implement |
| 2 | plan | Immediate per-cancellation email (not shorter digest) | Organizers need real-time signal; digest still runs for summary | implement |
| 3 | plan | 2-min camera inactivity timeout (not configurable) | Simple first pass; can make configurable later | implement |

## Cross-Milestone Interface

| Category | Items |
|---|---|
| Traits | HasRetryStrategy (queued notification retry config) |
| Notifications | ImmediateCancellationNotification |
| Listeners | NotifyOrganizersOfCancellation |

## Reviews

### Security Audit — 2026-04-09

| # | Perspective | Concern | Severity | Resolution | Rationale |
|---|---|---|---|---|---|
| 1 | Security Paranoid | VolunteerPortal.$volunteer not #[Locked] | high | accepted | Added #[Locked] attribute |
| 2 | Security Paranoid | VolunteerPortal.$magicToken not #[Locked] | medium | accepted | Added #[Locked] attribute |
| 3 | Security Paranoid | Deletion guard TOCTOU race condition | low | accepted | Volunteer-initiated only, low concurrency, acceptable risk |
| 4 | Security Paranoid | DomainException messages shown verbatim | low | accepted | Messages are hand-crafted German strings, no internal details |
| 5 | Security Paranoid | @entangle on timezone picker is safe | info | n/a | Server-side validation via form object gates persistence |

## Feedback Loops

| # | Date | Direction | Trigger | Fix | Resolution |
|---|---|---|---|---|---|
