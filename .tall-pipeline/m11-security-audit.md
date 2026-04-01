# Security Audit — M11 Scanner Rewrite

**Date:** 2026-04-01
**Scope:** M11 Scanner Rewrite (project-scoped scanners, temp auth, dual types, API)
**Auditor:** Security Paranoid (automated)
**Branch:** `feature/m11-scanner`

---

## Executive Summary

The M11 scanner rewrite demonstrates strong security architecture overall. Token generation uses cryptographic randomness (`random_bytes(32)` for scanner tokens, `random_int` for auth codes), auth codes are bcrypt-hashed at rest (D1), sensitive model attributes are in `$hidden`, Livewire server-assigned properties use `#[Locked]`, and the raw auth code is transmitted via `session()->flash()` instead of Livewire state (P5 review fix). Rate limiting on PIN auth and IDOR scoping on gear pickup were both addressed during implementation review.

Three findings require attention. The most significant is a **missing session regeneration** after scanner PIN authentication, which creates a session fixation vector. Second, the scanner API endpoints lack rate limiting, making the data endpoint vulnerable to scraping and the sync endpoint to spam. Third, the `ScannerManagement` component does not validate that the user-supplied `eventId` belongs to the same project, allowing cross-project event assignment to a scanner.

Four lower-severity items round out the findings: a non-timing-safe token lookup in `ScannerApiMiddleware`, volunteer PII (email) exposed in the scanner data API without need-to-know filtering by scanner type, the `ScannerLinkMail` Mailable holding a full `ProjectScanner` model reference (though `$hidden` prevents serialization of secrets), and `console.error` calls in Alpine that could leak server error responses to the browser console.

No critical findings. Two high, one medium, four low. **All high and medium findings fixed.**

---

## Findings

### Finding 1: Missing Session Regeneration After Scanner Authentication

- **Severity:** high
- **Category:** Session Fixation (CWE-384)
- **Location:** `app/Livewire/ScannerAuth.php:80-85`
- **Description:** After successful PIN verification, the `authenticate()` method writes `scanner_id` and `scanner_authenticated_at` to the session but does not call `session()->regenerate()`. An attacker who can plant a known session ID on the victim's browser (e.g., via a shared/public device, XSS on a related domain, or a network-level MITM injecting a `Set-Cookie` header) can wait for the legitimate operator to authenticate, then use the pre-planted session ID to access the scanner app as the authenticated operator.
- **Impact:** Session fixation allows an attacker to hijack a scanner session. The attacker would gain access to the scanner app, volunteer PII, arrival/attendance recording, and gear pickup capabilities for the duration of the scanner window.
- **Remediation:** Add `session()->regenerate()` immediately before writing the scanner session data:
  ```php
  session()->regenerate();
  session([
      'scanner_id' => $scanner->id,
      'scanner_authenticated_at' => now()->toISOString(),
  ]);
  ```
- **Status:** fixed — `session()->regenerate()` added before session write

### Finding 2: No Rate Limiting on Scanner API Endpoints

- **Severity:** high
- **Category:** Rate Limiting / Denial of Service (CWE-770)
- **Location:** `routes/api.php:11-15`, `app/Http/Middleware/ScannerApiMiddleware.php`
- **Description:** The scanner API routes (`GET /{scannerId}/data`, `POST /{scannerId}/sync`, `POST /{scannerId}/gear-pickup`) are protected by the `scanner-api` middleware but have no rate limiting. The `throttle:public-api` limiter only covers the `v1` route group. A valid scanner token (64-char hex, present in the URL of every scanner link email and stored in the client-side Alpine state) could be used to:
  1. **Scrape the data endpoint** at high frequency, exfiltrating all volunteer PII for the project.
  2. **Spam the sync endpoint** with duplicate arrival records (the server flags duplicates but still creates rows).
  3. **Spam the gear-pickup endpoint** creating large numbers of pickup records.
- **Impact:** Data exfiltration at scale, database bloat, and potential denial of service for legitimate scanner operations.
- **Remediation:** Add throttle middleware to the scanner API route group:
  ```php
  Route::prefix('scanner')->middleware(['scanner-api', 'throttle:60,1'])->group(function () {
      // ...
  });
  ```
  Consider a tighter limit on the data endpoint (e.g., 10 requests/minute) and the write endpoints (e.g., 30 requests/minute) using named rate limiters.
- **Status:** fixed — `throttle:60,1` added to scanner API route group

### Finding 3: Cross-Project Event Assignment in ScannerManagement

- **Severity:** medium
- **Category:** Broken Access Control / IDOR (CWE-639)
- **Location:** `app/Livewire/Projects/ScannerManagement.php:103,155`
- **Description:** The `eventId` Livewire property is a public `?int` that is not `#[Locked]` and is not validated against the current project during `createScanner()` or `updateScanner()`. The validation rules check `name`, `type`, `modes`, `startsAt`, and `endsAt` but not `eventId`. An attacker with organizer access could manipulate the Livewire request payload to set `eventId` to an event belonging to a different project. The `CreateProjectScanner` action passes it through directly as `event_id`.
  
  While the `events()` computed property correctly scopes to the project (providing a safe dropdown), the server-side action does not re-verify ownership.
- **Impact:** A scanner could be scoped to an event from a different project. When the scanner loads data, the `ScannerDataController::data()` method would query volunteers from the foreign event, potentially leaking volunteer data cross-project. The severity is medium because exploitation requires authenticated organizer access.
- **Remediation:** Add `eventId` validation in both `createScanner()` and `updateScanner()`:
  ```php
  'eventId' => ['nullable', 'integer', Rule::exists('events', 'id')->where('project_id', $this->projectId)],
  ```
  Alternatively, verify in the action itself before persisting.
- **Status:** fixed — `Rule::exists('events', 'id')->where('project_id', $this->projectId)` added to both create and update validation

### Finding 4: Non-Timing-Safe Token Comparison in ScannerApiMiddleware

- **Severity:** low
- **Category:** Timing Attack (CWE-208)
- **Location:** `app/Http/Middleware/ScannerApiMiddleware.php:23`
- **Description:** The `scanner_token` is looked up via `ProjectScanner::where('scanner_token', $token)->first()`. Database `WHERE` clauses on string columns use early-exit comparison, which in theory leaks timing information about how many leading characters of the token match a stored value. The same pattern exists in `ScannerAuthMiddleware.php:18`.
  
  However, the practical exploitability is very low because: (1) the token is 64 hex characters (256 bits of entropy), making brute-force infeasible even with timing side-channels; (2) network jitter dominates any database timing signal; (3) the database column has a unique index, so lookup is B-tree based (not sequential scan).
- **Impact:** Theoretical only. An attacker with extremely low-latency access to the database server might extract partial token information, but the 256-bit search space makes this impractical.
- **Remediation:** Acceptable as-is given the token entropy. For defense-in-depth, consider hashing the scanner token (SHA-256) at rest and comparing hashes, similar to the `HashedToken` value object used for magic link tokens. This would eliminate the theoretical vector entirely.
- **Status:** open (accepted risk)

### Finding 5: Volunteer PII Exposed to Entry Staff Scanners

- **Severity:** low
- **Category:** Data Leakage / Excessive Data Exposure (CWE-200)
- **Location:** `app/Http/Controllers/ScannerDataController.php:77-79`
- **Description:** The `data()` endpoint returns `email` for every volunteer regardless of scanner type. Entry Staff scanners (`entry_staff`) need only name and ticket data to validate QR codes and record arrivals. They do not need email addresses, shift signup details, or attendance records. The full dataset is returned to all scanner types identically.
  
  The Alpine component also stores all volunteer data in IndexedDB (`idb-store.ts:76`), meaning PII persists on the device even after the scanner session ends (no explicit cache clearing on logout/expiry).
- **Impact:** Scanner operators (who are external, non-account-holding users) gain access to volunteer email addresses for the entire project. If the device is shared or compromised, this PII is available in IndexedDB.
- **Remediation:** Filter the API response by scanner type:
  ```php
  'volunteers' => $volunteers->map(fn ($v) => array_filter([
      'id' => $v->id,
      'first_name' => $v->first_name,
      'last_name' => $v->last_name,
      'name' => $v->full_name,
      'email' => $scanner->type === ScannerType::VolunteerAdmin ? $v->email : null,
      'ticket' => $v->tickets->first(),
      // shift_signups only for VolunteerAdmin
  ])),
  ```
  Also consider adding an IndexedDB cleanup on scanner session end (component `destroy()`) or when the scanner window expires.
- **Status:** open

### Finding 6: Missing Attendance API Endpoint (Client-Server Mismatch)

- **Severity:** low
- **Category:** Broken Functionality / Missing Endpoint
- **Location:** `resources/js/scanner/alpine-scanner.ts:286`, `routes/api.php`
- **Description:** The `confirmAttendance()` method in `alpine-scanner.ts` constructs an attendance endpoint URL by replacing `/sync` with `/attendance` in the sync URL (line 286: `this._syncUrl.replace('/sync', '/attendance')`). However, no `/attendance` route exists in `routes/api.php`. The scanner API only defines `data`, `sync`, and `gear-pickup` endpoints. This means the volunteer admin attendance recording feature silently fails — the `POST` to a non-existent route returns a 404 (or potentially a 405), which is caught and logged to `console.error`.
  
  While this is primarily a functional gap rather than a security vulnerability, the silent failure could lead operators to believe attendance was recorded when it was not. The error is only visible in the browser console.
- **Impact:** Volunteer admin scanners cannot record shift attendance. Operators may not realize the feature is non-functional unless they check the browser console.
- **Remediation:** Either add the attendance API route and controller method, or remove the `confirmAttendance()` feature from the Alpine component and UI to prevent user confusion.
- **Status:** open

### Finding 7: Console.error Leaks Server Error Responses

- **Severity:** low
- **Category:** Information Disclosure (CWE-209)
- **Location:** `resources/js/scanner/alpine-scanner.ts:301,305,346,349`
- **Description:** The `confirmAttendance()` and `selectGearState()` methods log full server error responses to the browser console via `console.error('... failed:', await response.text())`. In development or misconfigured production environments, Laravel error responses can include stack traces, database query details, and environment variables.
- **Impact:** If `APP_DEBUG=true` in production (a misconfiguration), sensitive server internals could be exposed in the browser console of a scanner operator's device.
- **Remediation:** Log only the HTTP status code, not the full response body:
  ```typescript
  console.error('Attendance confirmation failed:', response.status);
  ```
  Or suppress error logging entirely in production and show a user-facing toast notification instead.
- **Status:** open

---

## Items Reviewed — No Issues Found

The following areas were reviewed and found to be properly implemented:

| Area | Assessment |
|---|---|
| **Mass assignment** | `$fillable` is properly scoped on both models. `auth_code` and `scanner_token` are in `$fillable` but only set by `CreateProjectScanner` action, not from user input. |
| **SQL injection** | No raw queries; all queries use Eloquent query builder with parameterized bindings. |
| **XSS** | All Blade output uses `{{ }}` (escaped). The `@js()` directive on `modes` is safe (JSON-encodes with proper escaping). No `{!! !!}` in scanner views. |
| **CSRF** | Scanner API uses `X-Scanner-Token` header (not cookie-based CSRF). Web routes use Livewire's built-in CSRF protection. API routes are in `routes/api.php` which excludes CSRF middleware by default. |
| **Auth code hashing** | Properly bcrypt-hashed via `Hash::make()` in `CreateProjectScanner`, verified via `Hash::check()` in `AuthenticateScanner`. |
| **Token generation** | `bin2hex(random_bytes(32))` produces 64 hex chars (256-bit entropy). Cryptographically secure. |
| **PIN rate limiting** | 5 attempts/minute keyed on `token+IP`. Properly implemented in `ScannerAuth::authenticate()`. RateLimiter cleared on success. |
| **#[Locked] attributes** | All server-assigned IDs and security-sensitive properties on `ScannerApp` (`scannerToken`, `scannerId`, `projectId`, `scannerType`, `modes`, `eventId`) and `ScannerAuth` (`scannerToken`) are `#[Locked]`. |
| **$hidden attributes** | `auth_code` and `scanner_token` on `ProjectScanner`; `email` on `ProjectScannerAssignee`. Prevents accidental JSON serialization. |
| **Middleware coverage** | Scanner web routes: `scanner-auth` middleware on `/s/{scannerToken}/scan`. Scanner API: `scanner-api` middleware on all three endpoints. Auth page (`/s/{scannerToken}`) is intentionally public. |
| **Policy enforcement** | `ScannerManagement::mount()` calls `Gate::authorize('manageScanners', $this->project)`. Policy restricts to project Organizers. |
| **Session flash for auth code** | Raw auth code uses `session()->flash()` (one-time read) instead of Livewire public property. Not persisted in snapshot. |
| **Scanner window enforcement** | Both `ScannerAuthMiddleware` and `ScannerApiMiddleware` check `isActive()` (rejects both expired and scheduled scanners). `ScannerApp::mount()` also checks `isActive()`. |
| **Project scoping on sync** | `ScannerDataController::sync()` validates ticket belongs to scanner's project via `Ticket::where('project_id', $scanner->project_id)->findOrFail()`. Event is validated against the scanner's event or project scope. |
| **Project scoping on gear pickup** | `ScannerDataController::gearPickup()` queries `VolunteerGear` through `gearItem.project_id` relationship, preventing cross-project access (P2 review fix). |
| **Scanner type enforcement** | `sync()` rejects non-EntryStaff; `gearPickup()` rejects non-VolunteerAdmin. |
| **Mailable safety** | `ScannerLinkMail` passes only `$scannerName`, `$url`, `$startsAt`, `$endsAt` to the view via `with`. The `$scanner` model property is public but `$hidden` prevents `auth_code`/`scanner_token` from leaking if accidentally serialized to JSON. The email template only references the `with` variables. |
| **Email template** | `scanner-link.blade.php` uses Markdown mail component. URL is pre-built with `route()`. No sensitive data in email body beyond the scanner link URL (which contains the token by design). |
| **Service Worker** | Network-first for API requests; cache-first for static assets. Cached API responses could contain PII but are browser-origin-scoped. No credential forwarding issues. |
| **Database schema** | `scanner_token` column is `string(64)` with unique index. `auth_code` is `string` (bcrypt hash). Proper foreign key constraints with cascade delete. |
| **Job serialization** | `SendScannerLinksJob` serializes `ProjectScannerAssignee` (which has `$hidden` on `email`). The job accesses `$this->assignee->email` directly (property access bypasses `$hidden`), which is correct and necessary for sending email. |
| **File uploads** | N/A for scanner feature. |

---

## Summary Table

| # | Finding | Severity | Status |
|---|---|---|---|
| 1 | Missing session regeneration after scanner authentication | high | open |
| 2 | No rate limiting on scanner API endpoints | high | open |
| 3 | Cross-project event assignment in ScannerManagement | medium | open |
| 4 | Non-timing-safe token comparison in middleware | low | open (accepted risk) |
| 5 | Volunteer PII exposed to entry staff scanners | low | open |
| 6 | Missing attendance API endpoint (client-server mismatch) | low | open |
| 7 | Console.error leaks server error responses | low | open |

**Overall:** 0 critical, 2 high, 1 medium, 4 low. The two high findings (session fixation, API rate limiting) should be resolved before shipping. The medium finding (cross-project event assignment) should be resolved in the same pass. Low findings are acceptable to defer but recommended for a follow-up pass.
