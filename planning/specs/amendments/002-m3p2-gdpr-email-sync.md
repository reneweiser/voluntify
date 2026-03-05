# Amendment 002: M3 Part 2 Completion, GDPR Double Opt-In, Branded Email Templates

**Date**: 2026-03-05
**Affects**: `specs/status.md`, `specs/project.md`
**Reason**: Features 11 and 12 shipped (M3 Part 2). GDPR double opt-in email verification and branded email templates were added as cross-cutting features not tracked in the original spec.

---

## 1. M3 Part 2: Scanner PWA Frontend & Manual Lookup (Features 11 & 12)

Features 11 and 12 are now **Done**. The frontend PWA and manual lookup UI shipped in commits `605d835` and `6a25047`.

### Scanner PWA (Feature 11)

**Livewire Components:**
- `App\Livewire\Scanner\QrScanner` -- Parent scanner component; checks auth (Organizer/EntranceStaff), provides event list to Alpine
- `App\Livewire\Scanner\ManualLookup` -- Server-side volunteer search by name (LIKE query), eager-loads `shiftSignups.shift.volunteerJob` and `eventArrivals`, calls `RecordArrival` with `ArrivalMethod::ManualLookup`

**Scanner Layout:**
- `resources/views/layouts/scanner.blade.php` -- Fullscreen dark-themed layout (`bg-zinc-900`), no sidebar, includes manifest.json link and scanner.ts Vite entry

**TypeScript Modules** (`resources/js/scanner/`):

| Module | Purpose |
|---|---|
| `types.ts` | Shared interfaces: `Volunteer`, `Ticket`, `ShiftSignup`, `ArrivalRecord`, `ScannerKeys`, `OutboxEntry`, `ScannerData` |
| `jwt-validator.ts` | Client-side JWT validation via Web Crypto API HMAC-SHA256; dual-key fallback (current + previous period) |
| `idb-store.ts` | IndexedDB wrapper (`voluntify-scanner` DB); 3 stores: `volunteers` (compound key `[eventId, id]`), `outbox` (auto-increment), `keys` (by eventId) |
| `camera.ts` | Camera access via `getUserMedia` (rear-facing preferred); integrates with jsQR for on-canvas QR detection |
| `sync.ts` | POSTs pending outbox entries to sync endpoint; clears on success, retains on failure |
| `alpine-scanner.ts` | Alpine.js `scannerApp(config)` component; state machine: `idle -> loading -> scanning -> result/duplicate/invalid -> confirmed` |

**PWA Infrastructure:**
- `public/sw.js` -- Service Worker: cache-first for static assets, network-first for `/scanner/api/` and navigation, pre-caches `/admin/scanner` and `/admin/scanner/lookup`
- `public/manifest.json` -- Standalone display, portrait orientation, emerald theme (`#059669`)

**Vitest Test Suite** (`tests/js/scanner/`):

| Test File | Coverage |
|---|---|
| `jwt-validator.test.ts` | Current key, previous key, expiry, malformed tokens, wrong keys |
| `idb-store.test.ts` | Volunteer CRUD, search, outbox operations, key storage; uses `fake-indexeddb/auto` |
| `sync.test.ts` | Outbox POST, clear on success, retain on failure, empty no-op |
| `camera.test.ts` | Camera init (rear-facing), permission denial, track stopping |

### Manual Lookup (Feature 12)

- `ManualLookup` Livewire component at `/admin/scanner/lookup`
- Server-side name search via `Volunteer::forEvent()` scope + LIKE
- Eager-loads relationships to prevent N+1
- `confirmArrival()` calls `RecordArrival` action with `ArrivalMethod::ManualLookup`

### Scanner Routes

All scanner routes are within the authenticated `/admin/` prefix (not `/api/scanner/` as originally discussed):

```
GET  /admin/scanner                              -> QrScanner component
GET  /admin/scanner/lookup                       -> ManualLookup component
GET  /admin/scanner/api/events/{eventId}/data    -> ScannerApiController@data
POST /admin/scanner/api/events/{eventId}/sync    -> ScannerApiController@sync
```

**Design divergence**: The scanner API lives at `/admin/scanner/api/` rather than a separate `/api/scanner/` prefix. This keeps scanner routes under the same auth middleware group as the scanner UI.

---

## 2. GDPR Double Opt-In Email Verification (Feature 25)

Commit `c37fa63`. A GDPR-compliant email verification step was added to the volunteer signup flow. This is a **new feature** not in the original spec.

### Signup Flow Change

The signup flow is now a 3-action chain:

```
EventSignup component
  -> ProcessVolunteerSignup (new orchestrator)
      -> If volunteer already verified: SignUpVolunteerForShifts -> SignUpVolunteer (per shift)
      -> If not verified: SendEmailVerification (stores token, sends notification)
          -> Volunteer clicks email link
              -> CompleteEmailVerification (validates token, marks verified, calls SignUpVolunteerForShifts)
```

`ProcessVolunteerSignup` replaced `SignUpVolunteerForShifts` as the entry point from `EventSignup`. It checks `volunteer->hasVerifiedEmail()` and branches accordingly.

### New Entities

**Model**: `EmailVerificationToken`
- Fields: `volunteer_id`, `event_id`, `shift_ids` (JSON array), `token_hash` (SHA-256), `expires_at` (24 hours)
- Relationships: `belongsTo` Volunteer, `belongsTo` Event
- Migration: `create_email_verification_tokens_table`

### New Actions

| Action | Responsibility |
|---|---|
| `ProcessVolunteerSignup` | Orchestrates: check verification status, branch to signup or send verification |
| `SendEmailVerification` | Generates 64-char random token, hashes with SHA-256, stores `EmailVerificationToken`, dispatches `EmailVerification` notification |
| `CompleteEmailVerification` | Hash-lookup token, check expiry + event status, mark volunteer verified, delete token, execute `SignUpVolunteerForShifts` |

### New Value Objects & Enums

- `App\ValueObjects\SignupOutcome` -- Readonly VO with `type` (enum), optional `batchResult` and `pendingEmail`; static factories `completed()` and `pendingVerification()`
- `App\Enums\SignupOutcomeType` -- String enum: `Completed`, `PendingVerification`

### New Exceptions

- `App\Exceptions\ExpiredVerificationException` -- Extends `DomainException`; thrown when verification token is expired

### New Route

```
GET /verify-email/{token} -> EmailVerificationPage (Livewire, public layout, no auth)
```

### New Livewire Component

- `App\Livewire\Public\EmailVerificationPage` -- Mounts with token parameter, executes `CompleteEmailVerification`, catches exceptions, displays verification status and event details

### New Notification

- `App\Notifications\EmailVerification` -- Queued notification; uses `EmailTemplateRenderer` with `email_verification` template type; placeholders: `volunteer_name`, `event_name`

### Email Template Type Addition

`EmailTemplateType` enum gained a new case: `EmailVerification = 'email_verification'`. The `EmailTemplateRenderer` service has a corresponding default template for the verification email.

---

## 3. Voluntify-Branded Email Templates (Feature 26)

Commit `1866419`. Custom Mailable styling applied to all outgoing notifications.

All queued notifications (`SignupConfirmation`, `EmailVerification`) now use Voluntify-branded email templates with consistent visual styling. The `StaffInvitation` notification uses a simpler inline format.

This is a presentation-layer change. The `EmailTemplateRenderer` service and `EmailTemplate` model (from M2.1) were already in place; this feature adds branded HTML layouts to the rendered output.

---

## 4. Domain Model Update

The domain model grows by 1 entity to **15 entities**:

```
[volunteers] 1---N [email_verification_tokens]
```

The `email_verification_tokens` table stores pending verification tokens with a compound relationship to both `volunteers` and `events`, plus the `shift_ids` JSON array to remember which shifts were selected at signup time.

---

## 5. Summary of New Code Artifacts

| Category | Artifacts Added |
|---|---|
| Models | `EmailVerificationToken` |
| Actions | `ProcessVolunteerSignup`, `SendEmailVerification`, `CompleteEmailVerification` |
| Livewire Components | `Scanner\QrScanner`, `Scanner\ManualLookup`, `Public\EmailVerificationPage` |
| Value Objects | `SignupOutcome` |
| Enums | `SignupOutcomeType`; new case on `EmailTemplateType` |
| Exceptions | `ExpiredVerificationException` |
| Notifications | `EmailVerification` |
| TypeScript Modules | 6 modules in `resources/js/scanner/` |
| Vitest Tests | 4 test files in `tests/js/scanner/` |
| PWA Assets | `sw.js`, `manifest.json`, `layouts/scanner.blade.php` |

## Items Flagging for Human Review

1. **Entity count**: The project spec says "14 entities" (amended from 13 in amendment 001). With `email_verification_tokens`, the count is now 15. The domain model diagram in `project.md` should be updated if desired.
2. **Signup flow complexity**: The original tracer bullet was `SignUpVolunteer`. It is now a 3-action chain (`ProcessVolunteerSignup -> SignUpVolunteerForShifts -> SignUpVolunteer`). The project spec's description of the tracer bullet in M2 should note this evolution.
3. **Feature numbering**: Features 25 and 26 are unnumbered in the original spec. They have been assigned #25 and #26 in `status.md` to continue the sequence.
