# Phase 3 Invitation Reliability Messaging UX -- Security Audit Report

**Milestone:** phase-3-invitation-reliability-messaging-ux
**Audit Date:** 2026-05-07
**Auditor:** TALL Security Audit (automated + manual)
**Scope:** Phase 3 changes for #217, #218, #220, and #219, plus the trust boundaries they touch in guest invitation dispatch, organizer resend/recovery, reminder portal links, and guest-pass browser surfaces.

---

## Executive Summary

The Phase 3 implementation holds its core security boundaries well. Organizer-only Livewire mutators now re-authorize inside each public method, sensitive Livewire identifiers are locked, invitation delivery timestamps are no longer mass assignable, queued invitation jobs re-bind to current state so stale jobs no-op cleanly, and guest-pass browser surfaces keep `no-store` / `noindex` protections on both valid and invalid signed-link responses.

No unresolved critical or high milestone-specific findings were identified.

**Overall Risk: LOW-MEDIUM**

| Severity | Count |
|---|---:|
| Critical | 0 |
| High | 0 |
| Medium | 1 |
| Low | 1 |
| Info | 5 |

**Immediate conclusion:** the milestone can advance past security audit without an implementation loop-back. Two reminder-delivery hardening items should be queued as follow-up work, but they do not block completion.

---

## Phase 1: Automated Scanning Results

### 1.1 Dependency Audits

**Composer:** `vendor/bin/sail composer audit`

- No security vulnerability advisories found.

**NPM:** `vendor/bin/sail npm audit --audit-level=low`

- High: `axios` advisory set reported by `npm audit`
- Moderate: `follow-redirects`
- Moderate: `postcss`

These are repo-level dependency findings, not introduced by this milestone's PHP / Livewire changes. They should still be remediated separately.

**`roave/security-advisories`:** not present in `composer.json`.

### 1.2 Static Analysis

- PHPStan / Larastan not installed.
- Psalm taint analysis not installed.

### 1.3 Secret Detection

- `gitleaks` is not installed in this workspace.
- `.env` is ignored in `.gitignore`.
- `git log --all --diff-filter=A -- '.env' '.env.*'` shows a historical `.env` add in the initial project commit. This audit did not inspect the historical payload, but the repository should verify no meaningful secrets were ever published and rotate anything non-disposable if needed.

### 1.4 Environment / Configuration Checks

`.env` reviewed for local development settings:

- `APP_ENV=local`
- `APP_DEBUG=true`
- `SESSION_DRIVER=database`

These values are acceptable for local development. Production still needs `APP_DEBUG=false` and a production-appropriate mailer.

### 1.5 Header / Surface Checks

- Guest-pass valid responses set `Cache-Control: no-store, private` and `X-Robots-Tag: noindex, nofollow, noarchive` in `app/Http/Controllers/GuestPassController.php:17-24`.
- Guest-pass invalid / expired responses set the same headers in `bootstrap/app.php:42-49`.
- No global CSP / HSTS / X-Frame-Options / Referrer-Policy middleware was found. This is a pre-existing application-level gap, not introduced by Phase 3.

### 1.6 Debug Tool Exposure

- No Telescope, Horizon, or Debugbar configuration was found in the current app packages / config.

---

## Phase 2: Manual Code Review (OWASP Top 10)

### A01:2021 -- Broken Access Control

#### Positive: Organizer Livewire Mutators Re-Authorize In-Method

**Location:** `app/Livewire/Projects/GuestListShow.php:108-350`

All state-changing public methods call `authorizeGuestListManagement()` before mutating guest-list state. This closes the earlier post-mount permission-revocation gap and is the correct Livewire pattern.

#### Positive: Authorization-Sensitive Livewire Properties Are Locked

**Location:** `app/Livewire/Projects/GuestListShow.php:34-38,57-58`

`$projectId`, `$guestListId`, and `$editingEntryId` are `#[Locked]`, which prevents client-side property tampering from widening record access.

#### Positive: Record Lookups Stay Scoped To The Locked Guest List / Project

**Location:** `app/Livewire/Projects/GuestListShow.php:131,148,178,207,239,253,265,277,290,316`

Mutating actions resolve entities through the current project / guest-list boundary instead of trusting naked IDs.

### A02:2021 -- Cryptographic Failures

#### Positive: Reminder Portal Links Reuse Hashed, High-Entropy Magic Tokens

**Location:** `app/Actions/GenerateMagicLink.php:17-31`, `app/Actions/VerifyMagicLink.php:12-26`

Reminder emails use the same 64-character random magic-link tokens already established in M19. Tokens are hashed at rest and compared by hash lookup.

#### Positive: Guest-Pass Browser Links Remain Temporary Signed Routes

**Location:** `app/Models/GuestEntry.php:136-144`

The CTA rendering change in #219 did not widen guest-pass route semantics. The browser fallback still uses Laravel temporary signed URLs with expiry tied to the scanner window or a 7-day fallback.

#### Residual Risk: Bearer-Link Replay Remains An Accepted Tradeoff

**Location:** `app/Notifications/PreShiftReminder.php:47-84`, `routes/web.php:66-68`

Reminder portal links and guest-pass URLs are bearer credentials. Anyone with the email can use the link until the signed URL expires or, for portal links, until the token is otherwise invalidated. This is consistent with prior milestone design and was not broadened beyond the existing portal model, but it remains an architectural replay risk.

### A03:2021 -- Injection

#### Positive: No SQL Injection In Phase 3 Code Paths

The Phase 3 files use Eloquent query builder constraints only. No new `DB::raw`, `whereRaw`, or string-built SQL paths were introduced in the audited scope.

#### Positive: No New XSS Sink On User-Controlled Content

**Locations:** `resources/views/livewire/projects/guest-list-show.blade.php`, `resources/views/mail/guest-invitation.blade.php`, `resources/views/public/guest-pass.blade.php`

User-facing Phase 3 strings are escaped with `{{ }}`. The only raw HTML remains QR SVG output via `{!! $entry->qrCodeSvg() !!}`, which is generated from server-side QR token input rather than user HTML.

### A04:2021 -- Insecure Design

#### [MEDIUM] F-1: Reminder Delivery Is Marked Sent Before Notification Success

**OWASP Category:** A04:2021 -- Insecure Design
**Location:** `app/Actions/SendPreShiftReminders.php:33-54`

**Evidence:**

```php
foreach ($signups as $signup) {
    $signup->update([$window->flagColumn() => true]);

    try {
        ['plainToken' => $plainToken] = $this->generateMagicLink->execute($signup->volunteer);

        $signup->volunteer->notify(new PreShiftReminder(...));
        $count++;
    } catch (\Throwable $e) {
        Log::error('Failed to send pre-shift reminder', [...]);
    }
}
```

**Risk:** a transient queue, database, token-generation, or mail transport failure permanently flips the `notification_24h_sent` / `notification_4h_sent` flag anyway. That suppresses future retries for the same signup and can silently drop reminder delivery. Because #220 now mints fresh portal links for every reminder send, this also creates a state mismatch between "reminder marked sent" and "no reminder actually delivered".

**Remediation:** claim the signup atomically, but only persist the final sent flag after successful token generation and notification dispatch. If an early claim marker is required for concurrency control, restore it on failure inside the catch path.

### A05:2021 -- Security Misconfiguration

#### Positive: Guest-Pass Responses Explicitly Disable Browser / Proxy Storage

**Location:** `app/Http/Controllers/GuestPassController.php:17-24`, `bootstrap/app.php:42-49`

Both success and invalid-signature guest-pass surfaces carry `no-store` headers and `X-Robots-Tag`, which is the right hardening for emailed bearer links.

### A06:2021 -- Vulnerable And Outdated Components

#### [LOW] F-2: Frontend Dependency Advisories Are Present In `npm audit`

**OWASP Category:** A06:2021 -- Vulnerable and Outdated Components
**Location:** `package.json:12-37` and `vendor/bin/sail npm audit --audit-level=low`

**Evidence:** `npm audit` reports a high advisory set on `axios` plus moderate advisories on `follow-redirects` and `postcss`.

**Risk:** this is not specific to Phase 3 guest invitation / reminder code, but the repository is carrying known vulnerable frontend packages.

**Remediation:** run `vendor/bin/sail npm audit fix`, verify lockfile and runtime behavior, and pin any remaining unresolved packages deliberately.

### A07:2021 -- Identification And Authentication Failures

#### Positive: Reminder Sends Stay Limited To Verified Volunteers

**Location:** `app/Actions/SendPreShiftReminders.php:18-29`

The reminder query scopes to active signups, published events, and volunteers with `email_verified_at` present.

### A08:2021 -- Software And Data Integrity Failures

#### Positive: Invitation Jobs Re-Bind To Current Claimed Rows And No-Op When Stale

**Location:** `app/Actions/QueueGuestInvitationSiblingSet.php:71-126`, `app/Jobs/SendGuestInvitationsJob.php:29-69`

The new claim-and-send flow persists exact claimed entry IDs, only updates those rows, and skips stale work once rows move or lose queued eligibility. This materially improves integrity over the older broad same-email update pattern.

### A09:2021 -- Security Logging And Monitoring Failures

#### Positive: Reminder Failures Are Logged With Context

**Location:** `app/Actions/SendPreShiftReminders.php:46-53`

Failure logs include volunteer, shift, signup, and reminder-window context without logging raw portal tokens.

### A10:2021 -- Server-Side Request Forgery

No SSRF-relevant outbound user-controlled URL flow was introduced in the audited Phase 3 code.

### Open Redirects

No user-controlled redirect target was introduced in the audited Phase 3 code.

---

## Phase 3: Threat Modeling

### STRIDE Summary

| Feature / Boundary | Spoofing | Tampering | Repudiation | Info Disclosure | DoS / Abuse | Elevation |
|---|---|---|---|---|---|---|
| Organizer -> `GuestListShow` Livewire mutators | Protected by auth session + per-method Gate | Locked IDs + validation reduce snapshot tampering | Session flashes / tests provide basic traceability | Invitation state is organizer-only | Organizer can intentionally queue sends, but recipient claims prevent duplicate pending/failed dispatch | No authz gap found in scoped record lookups |
| Claim action -> `SendGuestInvitationsJob` | Job input comes from server-side dispatcher | Exact claimed IDs prevent sibling drift | Queue retries rely on worker logs | No raw QR token exposure beyond existing mail / guest pass | Stale jobs no-op cleanly; queued/failed transitions are bounded | No privilege change surface |
| Reminder scheduler -> portal-link notification | Volunteer identity is bound by hashed magic token lookup | Token creation is server-side only | Reminder failures are logged | Portal links are bearer URLs by design | Overlap / early sent-flag persistence can duplicate or suppress delivery | No auth bypass beyond possession of the token |
| Recipient -> guest-pass browser route | Signed URL proves integrity | Query tampering rejected by `signed` middleware | Invalid links return generic guest-safe response | `no-store` and `noindex` headers reduce passive leakage | Replay is limited to the signed URL validity window | No server-side privilege escalation |

### Trust Boundaries

1. **Organizer browser -> Livewire component**
   Data crossing: entry IDs, guest names/emails, resend/edit actions.
   Validation: `#[Locked]` on scoping IDs, explicit validation in `saveEntry()` / `updateGuestList()`, in-method `Gate::authorize()`.
   Attacker control: all unlocked public properties and method calls.

2. **Action layer -> queue jobs**
   Data crossing: guest-list ID, recipient email, claimed entry IDs.
   Validation: row claim happens inside DB transaction before dispatch.
   Attacker control: indirect only through organizer-authorized mutations.

3. **Email recipient -> guest-pass / portal routes**
   Data crossing: signed guest-pass URL or plain portal bearer token.
   Validation: `signed` middleware for guest pass, hash lookup for portal token.
   Attacker control: possession / forwarding / replay of the link.

### Abuse Cases

1. As a malicious organizer, I try to tamper with Livewire IDs to resend or edit another guest list. The locked IDs and scoped queries prevent cross-list access.
2. As a stale queue worker, I try to send to an old guest email after the organizer corrected it. The job re-checks queued state and current email, so stale work no-ops.
3. As an attacker with access to a forwarded reminder email, I can replay the contained volunteer portal bearer link. This remains an accepted residual risk of the non-expiring magic-link model.

---

## Residual Risks

These are not new critical / high findings, but they remain true after Phase 3:

1. Reminder portal links are long-lived bearer credentials by design from M19. Forwarded or compromised emails still grant portal access until the token is otherwise invalidated.
2. Guest-pass browser links are bearer links within their signature lifetime. They are hardened against tampering and caching, but not against intentional forwarding.
3. The app still lacks a centralized security-header policy (CSP, HSTS, frame policy). That is broader than this milestone.

---

## Recommended Next Steps

1. Backlog a follow-up hardening change for `SendPreShiftReminders` so sent flags are only persisted after successful notification dispatch or are rolled back on failure.
2. Add overlap protection and/or atomic claiming for the scheduled reminder command to avoid duplicate sends and duplicate portal-token minting under concurrent runs.
3. Triage the current `npm audit` advisories and consider adding `roave/security-advisories` plus PHPStan/Larastan in CI.

---

## Final Verdict

- No unresolved critical or high Phase 3 findings.
- Security-audit stage can be marked **complete**.
- No implementation loop-back is required before milestone completion.
