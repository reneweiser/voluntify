# Security Audit: phase-4-signup-conflict-ux-coverage

## Scope

- Milestone: `phase-4-signup-conflict-ux-coverage`
- Branch: `milestone/phase-4-signup-conflict-ux-coverage`
- Reviewed surfaces:
  - `app/Livewire/Public/EventSignup.php`
  - `resources/views/livewire/public/event-signup.blade.php`
  - `tests/Feature/Public/EventSignupTest.php`
  - `tests/Feature/Actions/SignUpVolunteerForShiftsTest.php`
  - `e2e/signup-conflict-ux.spec.ts`
  - `e2e/setup.sh`
  - `e2e/.generated/fixtures.json`
- Trust boundaries reviewed:
  - Browser / Livewire public properties and actions
  - `?vt=` verification-link resume flow
  - local-only E2E fixture generation

## Verdict

- Gate: **pass with deferred follow-up**
- Critical findings: **0**
- High findings: **0**
- Medium findings: **1**
- Low findings: **1**
- Info findings: **2**

Phase 4 closed the originally targeted tampering gaps around locked wizard state, renderable-shift validation, overlap-step blocking, expired verified-token rejection, and the public E2E fixture leak. The former blocker was remediated by moving generated fixture data out of the web root and having Playwright load it from disk instead. The broader `?vt=` replay model remains a deferred medium concern if a continue URL leaks during its validity window.

## Method

### Version-specific guidance checked

- Livewire `#[Locked]` guidance for authorization-sensitive public properties
- Laravel signed / temporary URL documentation
- Laravel rate limiting documentation

### Automated checks

- `vendor/bin/sail composer audit`: no PHP advisories reported
- `vendor/bin/sail npm audit`: repo-level advisories reported for `axios`, `follow-redirects`, and `postcss`; noted below as broader dependency hygiene, not counted against this milestone gate
- `gitleaks detect --source . --verbose`: tool not installed locally
- `.gitignore`: `.env` is ignored
- `.env`: local-only settings observed (`APP_ENV=local`, `APP_DEBUG=true`)
- No project-level security-header configuration found in the reviewed app code

## Requested Surface Review

### Livewire property exposure / `#[Locked]`

- `EventSignup::$event`, `$state`, `$reservationExpiresAt`, `$verificationTokenId`, `$existingVolunteerId`, `$existingShiftIds`, `$existingGearSelections`, `$isReturningVolunteer`, `$verificationStartedAt`, and messaging fields are locked in `app/Livewire/Public/EventSignup.php:34-89`.
- Server-owned state is therefore not client-mutable through Livewire snapshot tampering.
- User-controlled fields remain mutable by design and are revalidated before use.

Status: **pass**

### Wizard step tampering

- `reserveAndAdvance()`, `advanceToConfirmation()`, `submitSignup()`, and `resendVerification()` enforce explicit step guards via `ensureStateIs()` in `app/Livewire/Public/EventSignup.php:394-399`, `495-499`, `577-581`, and `610-614`.
- `$state` is also `#[Locked]` at `app/Livewire/Public/EventSignup.php:37-39`, so a crafted Livewire payload cannot jump forward by mutating the public property.

Status: **pass**

### Selected shift tampering / hidden shift injection

- `reserveAndAdvance()` validates every submitted shift ID against the current renderable list plus event-owned active shifts in `app/Livewire/Public/EventSignup.php:508-518`.
- Existing returning-volunteer shifts can still be injected into the payload, but they are stripped back out before reservation and submit in `app/Livewire/Public/EventSignup.php:526-531` and `666-670`.
- `SignUpVolunteerForShifts` remains the authoritative overlap and eligibility boundary, as intended.

Status: **pass**

### Verified email token handling, expiry, and replay windows

- Expired `?vt=` resume tokens are rejected in `mount()` via `expires_at` and `verified_at` checks at `app/Livewire/Public/EventSignup.php:98-113`.
- Final submit rechecks token verification state, expiry, project scope, and email match at `app/Livewire/Public/EventSignup.php:629-640`.
- Tests cover expired resume and expired final-submit cases in `tests/Feature/Public/EventSignupTest.php:390-411` and `1165-1185`.
- The broader architecture still uses a reusable bearer token in the query string for up to 30 minutes after verification (`app/Livewire/Public/EmailVerificationPage.php:37`, `app/Livewire/Public/EventSignup.php:98-113`). That remains a real replay surface if the continue URL leaks, but after the Phase 4 hardening it is a **medium** deferred concern rather than a milestone-blocking auth bypass on its own.

Status: **needs follow-up**

### Rate limiting scope

- Initial lookup and resend throttles are event-scoped by email in `app/Livewire/Public/EventSignup.php:335-341` and `412-418`.
- Notification signup is also event-scoped by email in `app/Livewire/Public/EventSignup.php:479-485`.
- Reservation and final-submit throttles remain IP-only in `app/Livewire/Public/EventSignup.php:501-506` and `616-621`; this is an availability concern, not an integrity bypass.

Status: **acceptable with low residual risk**

### XSS / unescaped conflict copy or fixture data reaching public output

- Phase 4 conflict copy is rendered through escaped Blade output in `resources/views/livewire/public/event-signup.blade.php:433-470`.
- Conflict labels originate from job names and formatted shift labels, but there is no unescaped sink in the reviewed surface.
- `e2e/signup-conflict-ux.spec.ts` consumes fixture JSON only inside Playwright; it does not pipe fixture content into browser HTML.

Status: **pass**

### Public E2E fixture exposure risk

- `e2e/setup.sh` now writes fixture data to `e2e/.generated/fixtures.json`, outside the public web root.
- The affected Playwright specs now load fixture JSON from the local filesystem through `e2e/fixtures.js` instead of fetching `/e2e-fixtures.json` over HTTP.
- `EventSignup::mount()` still accepts `?vt=` hashes as resume credentials, but the hashes are no longer published from a publicly reachable file.

Status: **pass after remediation**

## Findings

### [MEDIUM] `?vt=` resume links are still reusable bearer tokens within the post-verification window

**OWASP Category:** A07:2021 -- Identification and Authentication Failures  
**Location:** `app/Livewire/Public/EmailVerificationPage.php:37`, `app/Livewire/Public/EventSignup.php:98-113`, `app/Livewire/Public/EventSignup.php:629-640`

**Evidence:** After email verification, the app issues a continue URL with `?vt={token_hash}`. The same hash remains reusable for resume for 30 minutes after `verified_at` as long as `expires_at` has not passed.

**Risk:** If the continue URL leaks through browser history, copied URLs, screenshots, support logs, or shared devices, another person can resume the signup as that verified email during the replay window.

**Remediation:** Convert the resume flow from a replayable query-string bearer token to a one-time or short-lived exchange, for example by consuming the token into a session-bound nonce before rendering the wizard.

**References:**

- Laravel URL validation docs

### [LOW] Reservation and final-submit throttles are still global to IP instead of event-scoped

**OWASP Category:** A04:2021 -- Insecure Design  
**Location:** `app/Livewire/Public/EventSignup.php:501-506`, `app/Livewire/Public/EventSignup.php:616-621`

**Evidence:** `signup-reserve:{ip}` and `signup-submit:{ip}` do not include the event identifier, unlike the email lookup and resend throttles.

**Risk:** Users behind a shared NAT can throttle each other across different events. This is an availability and UX issue, not a data-integrity bypass.

**Remediation:** Include the event ID in the throttle bucket if you want consistency with the Phase 4 event-scoped lookup/resend hardening.

## Additional Notes

### [INFO] Phase 4 hardening areas that reviewed cleanly

- `#[Locked]` coverage for server-owned wizard state is appropriate in the reviewed surface.
- Step tampering is blocked server-side with explicit guards and visible errors.
- Hidden shift injection is blocked by renderable-shift validation plus authoritative action-level checks.
- Conflict copy is escaped; no XSS sink was found in the reviewed milestone UI.

### [INFO] Broader repo hygiene outside the milestone gate

- `npm audit` reported one high (`axios`) and two moderate advisories (`follow-redirects`, `postcss`) from the frontend dependency tree.
- `gitleaks` is not installed locally; adding it to CI would strengthen secret scanning.
- `roave/security-advisories`, PHPStan/Larastan, and Psalm taint analysis were not present in `composer.json`.

## STRIDE Summary

- **Spoofing:** No current high-severity spoofing issue remains in the reviewed Phase 4 surface after the fixture exposure remediation.
- **Tampering:** Livewire state and selected shifts are appropriately guarded in Phase 4.
- **Repudiation:** No new audit trail gap was introduced by the reviewed change surface.
- **Information Disclosure:** No public fixture publication remains after moving generated data to `e2e/.generated/fixtures.json`.
- **Denial of Service:** Reserve/submit rate limits remain broader than necessary but are not blocker severity.
- **Elevation of Privilege:** No step-jump or hidden-shift privilege escalation survived the current guards.

## OWASP Top 10 Coverage

- **A01 Broken Access Control:** no remaining milestone-specific finding after moving fixture data out of the web root
- **A02 Cryptographic Failures:** no new milestone-specific finding; replay window covered separately under auth
- **A03 Injection:** no Phase 4 XSS or injection finding in the reviewed surface
- **A04 Insecure Design:** one low finding on global IP-scoped reserve/submit throttles
- **A05 Security Misconfiguration:** fixture publication under `public/` contributes to the high finding
- **A06 Vulnerable and Outdated Components:** npm advisories noted outside milestone gate
- **A07 Identification and Authentication Failures:** one medium finding on reusable `?vt=` resume links
- **A08 Software and Data Integrity Failures:** no new milestone-specific finding
- **A09 Security Logging and Monitoring Failures:** no new milestone-specific finding in reviewed surface
- **A10 SSRF:** no finding in reviewed surface

## Recommended Next Steps

1. Follow up on the deferred `?vt=` architecture with one-time or session-bound resume semantics.
2. Backlog event-scoping for reserve/submit throttles if shared-IP signup environments matter.
3. Backlog repo hygiene work: `axios` upgrade, CI secret scanning, and optional static analysis.

## Executive Summary

Phase 4 itself is materially safer than the pre-review version: the overlap UX additions did not introduce a Livewire tampering or XSS regression, wizard state is locked, final submit no longer trusts expired verified tokens, and the E2E setup no longer publishes live verified signup-resume hashes from the web root. No critical or high issues remain in the reviewed milestone surface. The deferred `?vt=` replay architecture still deserves follow-up, but after the Phase 4 expiry, scoping fixes, and fixture-removal remediation it is best classified as **medium**, not a release blocker by itself.
