# M12 Guest Lists -- Security Audit Report

**Milestone:** m12-guest-lists
**Audit Date:** 2026-04-01
**Auditor:** TALL Security Audit (automated + manual)
**Scope:** All code added or modified in M12 (Guest Lists feature)

---

## Executive Summary

The M12 Guest Lists feature is well-implemented with strong authorization patterns, proper IDOR protections on most surfaces, and good use of cryptographic randomness for QR tokens. The scanner API endpoints inherit the existing `scanner-api` middleware and rate limiting from M11.

**Overall Risk: LOW-MEDIUM**

| Severity | Count |
|----------|-------|
| Critical | 0 |
| High | 1 |
| Medium | 2 |
| Low | 3 |
| Info | 3 |

**Top risks requiring attention:**
1. **[HIGH] Unvalidated `entryGear` array** -- Livewire public property passed directly to `updateOrCreate()` without validation; enables IDOR on `project_gear_item_id` across projects.
2. **[MEDIUM] Unvalidated `gearItemIds`/`editGearItemIds` arrays** -- gear item IDs passed to guest list creation/update without existence or project-scoping validation.
3. **[MEDIUM] `ConfirmGuestListJob` lacks `ShouldBeUnique`** -- double-dispatch could generate duplicate invitation emails.

---

## Phase 1: Automated Scanning Results

### 1.1 Dependency Audits

**Composer:** No security vulnerability advisories found.

**NPM:** 2 high-severity vulnerabilities found:
- `picomatch <=2.3.1 || 4.0.0-4.0.3` -- ReDoS via extglob quantifiers (build-time dependency only)
- `undici 7.0.0-7.23.0` -- Multiple HTTP smuggling and WebSocket issues (build-time dependency)

Both are fixable via `npm audit fix`. These are dev/build dependencies, not runtime, so risk is **low** in production.

**`roave/security-advisories`:** Not installed. Recommend adding as a dev dependency.

### 1.2 Static Analysis

PHPStan/Larastan: Not installed. Recommend adding for type-safety and taint analysis.

### 1.3 Secret Detection

No `.env` files committed (only initial commit included standard scaffolding). `.env` is in `.gitignore`.

### 1.4 Environment Configuration

`.env.example` reviewed:
- `APP_DEBUG=true` -- correct for example file; production must set `false`
- `SESSION_DRIVER=database` -- good
- `MAIL_MAILER=log` -- correct for example; production must use real mailer
- `APP_KEY=` -- empty in example; correctly generated per environment

### 1.5 Header Security

No explicit security header middleware found. This is a pre-existing condition, not M12-specific. Noted as INFO.

### 1.6 Debug Tool Exposure

No Telescope, Horizon, or Debugbar detected. Clean.

---

## Phase 2: Manual Code Review (OWASP Top 10)

### A01:2021 -- Broken Access Control

#### [HIGH] F-1: Unvalidated `entryGear` Array Enables Cross-Project IDOR

**Location:** `app/Livewire/Projects/GuestListShow.php:61,211-228`
**Evidence:** The `$entryGear` public property is a raw `array` bound to the Livewire component. When `saveEntry()` is called, it is passed directly to `UpdateGuestEntry::execute()` without any validation:

```php
// GuestListShow.php:211-228
public function saveEntry(): void
{
    $this->validate([
        'entryName' => ['nullable', 'string', 'max:255'],
        'entryEmail' => ['nullable', 'email', 'max:255'],
    ]);
    // entryGear is NOT validated at all

    $action = new UpdateGuestEntry;
    $action->execute($entry, [
        'gear' => $this->entryGear,  // raw unvalidated array
    ]);
}
```

In `UpdateGuestEntry::execute()`:
```php
$entry->gear()->updateOrCreate(
    ['project_gear_item_id' => $gearData['project_gear_item_id']],
    ['quantity' => $gearData['quantity'] ?? 1, 'selection' => $gearData['selection'] ?? null]
);
```

**Risk:** An attacker can manipulate the Livewire snapshot to inject arbitrary `project_gear_item_id` values from other projects. This creates guest entry gear records linked to gear items belonging to other organizations' projects, which is a cross-project IDOR. Additionally, `quantity` and `selection` are completely unvalidated, allowing negative quantities or arbitrarily long selection strings.

**Remediation:**
```php
// In GuestListShow::saveEntry()
$this->validate([
    'entryName' => ['nullable', 'string', 'max:255'],
    'entryEmail' => ['nullable', 'email', 'max:255'],
    'entryGear' => ['nullable', 'array'],
    'entryGear.*.project_gear_item_id' => ['required', 'integer', Rule::exists('project_gear_items', 'id')->where('project_id', $this->projectId)],
    'entryGear.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
    'entryGear.*.selection' => ['nullable', 'string', 'max:255'],
]);
```

---

#### [MEDIUM] F-2: Unvalidated `gearItemIds` / `editGearItemIds` Arrays

**Location:** `app/Livewire/Projects/GuestListIndex.php:35,88` and `app/Livewire/Projects/GuestListShow.php:46,128`
**Evidence:** Both `$gearItemIds` and `$editGearItemIds` are public array properties passed to `CreateGuestList` / `UpdateGuestList` actions without validation that the IDs belong to the current project's gear items.

```php
// GuestListIndex.php:77-91
public function createGuestList(): void
{
    $this->validate([
        'name' => ['required', 'string', 'max:255'],
        'scannerId' => ['required', Rule::exists(...)],
    ]);
    // gearItemIds NOT validated
    $action->execute($this->project, [
        'gear_items' => !empty($this->gearItemIds) ? $this->gearItemIds : null,
    ]);
}
```

**Risk:** An attacker can manipulate the Livewire snapshot to inject gear item IDs from other projects. These IDs are stored in the `gear_items` JSON column on the guest list. While this doesn't directly corrupt data across projects (the JSON is informational), it could cause confusion if gear items from other projects appear in guest list gear setup.

**Remediation:**
```php
$this->validate([
    'name' => ['required', 'string', 'max:255'],
    'scannerId' => ['required', Rule::exists(...)],
    'gearItemIds' => ['nullable', 'array'],
    'gearItemIds.*' => ['integer', Rule::exists('project_gear_items', 'id')->where('project_id', $this->projectId)],
]);
```

Apply the same pattern in `GuestListShow::updateGuestList()` for `editGearItemIds`.

---

#### Verified Fixed: `$editingEntryId` is `#[Locked]`

**Location:** `app/Livewire/Projects/GuestListShow.php:55`
**Status:** VERIFIED-FIXED (P-2 from implement review)

```php
#[Locked]
public ?int $editingEntryId = null;
```

---

#### Verified Fixed: `$projectId` and `$guestListId` are `#[Locked]`

**Location:** `app/Livewire/Projects/GuestListShow.php:31-33` and `GuestListIndex.php:23`
**Status:** VERIFIED-FIXED

Both ID properties that control data scoping are locked against client tampering.

---

#### Positive: Proper IDOR Protection on Entity Operations

All Livewire public methods that accept entity IDs (`removeGroup`, `removeEntry`, `startEditEntry`, `saveEntry`, `addEntry`) properly scope lookups through the locked `$guestListId`:

```php
// Example: removeEntry properly scopes through guest_list_id
$entry = GuestEntry::whereHas('group', fn ($q) => $q->where('guest_list_id', $this->guestListId))
    ->findOrFail($entryId);
```

---

#### Positive: Authorization Gate Checks in mount()

Both `GuestListIndex` and `GuestListShow` call `Gate::authorize('manageGuestLists', $this->project)` in `mount()`. The `manageGuestLists` policy correctly restricts to Organizer role only.

---

#### Positive: Scanner API IDOR Protection

All scanner API endpoints (guestCheckin, guestGearPickup, guestSync) properly scope guest entries through scanner ownership:

```php
$entry = GuestEntry::whereHas('group.guestList', function ($q) use ($scanner) {
    $q->confirmed()->where('scanner_id', $scanner->id);
})->findOrFail($request->integer('guest_entry_id'));
```

---

### A02:2021 -- Cryptographic Failures

#### Positive: QR Token Generation Uses Strong Randomness

**Location:** `app/Jobs/ConfirmGuestListJob.php:40`, `app/Actions/AddGuestEntry.php:32`
**Evidence:** `bin2hex(random_bytes(32))` generates 64-character hex tokens (256 bits of entropy). This is cryptographically secure and provides sufficient entropy to prevent brute-force guessing.

#### Positive: QR Token in `$hidden`

**Location:** `app/Models/GuestEntry.php:17-19`
**Status:** VERIFIED-FIXED (P-1 from implement review)

```php
protected $hidden = ['qr_token'];
```

This prevents accidental serialization of the token in API responses or Livewire snapshots via standard Eloquent serialization.

#### Note: QR Token Intentionally Exposed to Scanner

**Location:** `app/Http/Controllers/ScannerDataController.php:340`
The `loadGuestEntries()` method manually includes `qr_token` in the JSON response for EntryStaff scanners. This bypasses `$hidden` intentionally because the scanner needs the raw token for client-side QR matching. The scanner API is protected by `scanner-api` middleware (token auth + active window check), which is appropriate.

---

### A03:2021 -- Injection

#### No SQL Injection

No raw queries (`DB::raw`, `whereRaw`, etc.) found in any M12 code. All queries use Eloquent parameterized queries.

#### No XSS in Blade Views

All user-controlled data in Blade views uses `{{ }}` (escaped) or `x-text` (Alpine, text-only). The only `{!! !!}` usage in M12 views is for `$entry->qrCodeSvg()` in the email template, which outputs SVG generated by the `QrCodeGenerator` service using `chillerlan/php-qrcode`. The input to this is the `qr_token` (hex string), which cannot contain HTML injection vectors.

#### No Command Injection

No `exec()`, `system()`, `shell_exec()`, or similar found in M12 code.

---

### A04:2021 -- Insecure Design

#### [MEDIUM] F-3: `ConfirmGuestListJob` Lacks `ShouldBeUnique`

**Location:** `app/Jobs/ConfirmGuestListJob.php`
**Evidence:** The job implements `ShouldQueue` but not `ShouldBeUnique`. If `ConfirmGuestList::execute()` is called rapidly (e.g., double-click, network retry), the job could be dispatched twice. While the job checks `whereNull('qr_token')` (idempotent for token generation), the email dispatch loop will send duplicate invitation emails:

```php
// Line 44-51: emails are sent for ALL entries with email + qr_token
$emails = $this->guestList->entries()
    ->whereNotNull('email')
    ->distinct()
    ->pluck('email');
foreach ($emails as $email) {
    SendGuestInvitationsJob::dispatch($this->guestList, $email);
}
```

**Risk:** Users receive duplicate invitation emails, which degrades trust and could trigger spam filters.

**Remediation:**
```php
class ConfirmGuestListJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public function uniqueId(): string
    {
        return (string) $this->guestList->id;
    }

    // ... rest of class
}
```

Additionally, consider adding `ShouldBeUnique` to `SendGuestInvitationsJob` with a composite unique ID of `guestList.id:email`.

---

#### [LOW] F-4: Double-Confirm Race Condition

**Location:** `app/Actions/ConfirmGuestList.php:14`
**Evidence:** The `ConfirmGuestList` action checks `$guestList->isConfirmed()` and then updates the status, but without database-level locking:

```php
if ($guestList->isConfirmed()) {
    throw new DomainException('Guest list is already confirmed.');
}
$guestList->update(['status' => GuestListStatus::Confirmed, ...]);
```

**Risk:** In a race condition (two concurrent requests), both could pass the `isConfirmed()` check before either updates the status. This would dispatch `ConfirmGuestListJob` twice. The impact is mitigated by the job's `whereNull('qr_token')` idempotency check, but duplicate emails would still be sent.

**Remediation:**
```php
public function execute(GuestList $guestList): GuestList
{
    return DB::transaction(function () use ($guestList) {
        $guestList = GuestList::lockForUpdate()->findOrFail($guestList->id);

        if ($guestList->isConfirmed()) {
            throw new DomainException('Guest list is already confirmed.');
        }

        $guestList->update([
            'status' => GuestListStatus::Confirmed,
            'confirmed_at' => now(),
        ]);

        ConfirmGuestListJob::dispatch($guestList);

        return $guestList->fresh();
    });
}
```

---

#### Verified Fixed: Status Uses Enum

**Location:** `app/Actions/ConfirmGuestList.php:18`
**Status:** VERIFIED-FIXED (S-4 from implement review)

```php
'status' => GuestListStatus::Confirmed,
```

Uses the `GuestListStatus` enum, not a raw string.

---

### A05:2021 -- Security Misconfiguration

#### [LOW] F-5: npm Audit Vulnerabilities (Build Dependencies)

**Evidence:** `npm audit` reports 2 high-severity vulnerabilities in `picomatch` and `undici`. These are build-time dependencies (Vite ecosystem), not served to users at runtime.

**Risk:** Low -- these affect the build process only. An attacker with access to the build environment could potentially exploit them.

**Remediation:** Run `npm audit fix` and update lock file.

---

### A06:2021 -- Vulnerable and Outdated Components

See F-5 above. PHP dependencies are clean.

---

### A07:2021 -- Identification and Authentication Failures

No new authentication surfaces in M12. Scanner API authentication via `X-Scanner-Token` header is inherited from M11 and was previously audited.

---

### A08:2021 -- Software and Data Integrity Failures

No `unserialize()` usage. No file uploads in M12. Queue jobs use Laravel's built-in serialization which is safe.

---

### A09:2021 -- Security Logging and Monitoring

#### [LOW] F-6: No Audit Logging for Guest List Confirmation

**Location:** `app/Actions/ConfirmGuestList.php`, `app/Jobs/ConfirmGuestListJob.php`
**Evidence:** Guest list confirmation and QR token generation are not logged. Since these are significant security events (generating authentication credentials), they should be auditable.

**Risk:** Low -- inability to investigate when/who confirmed a guest list or when tokens were generated.

**Remediation:** Add logging in the action:
```php
Log::info('Guest list confirmed', [
    'guest_list_id' => $guestList->id,
    'project_id' => $guestList->project_id,
    'entry_count' => $guestList->entries()->count(),
]);
```

---

### A10:2021 -- Server-Side Request Forgery

No HTTP client calls or user-controlled URLs in M12 code.

---

### Open Redirect Detection

No redirects using user-supplied input found in M12 code. The `GuestListIndex` redirect uses `route()` with fixed route names.

---

## Phase 3: Threat Modeling

### 3.1 STRIDE Analysis

| Threat | Category | Finding | Mitigation Status |
|--------|----------|---------|-------------------|
| Spoofing | Authentication | Scanner API uses token auth; Livewire uses session auth | Mitigated |
| Tampering | Integrity | `$projectId`, `$guestListId`, `$editingEntryId` use `#[Locked]`; `$entryGear` is unvalidated (F-1) | Partially mitigated |
| Repudiation | Logging | No audit trail for guest list confirmation (F-6) | Not mitigated |
| Info Disclosure | Confidentiality | `qr_token` in `$hidden`; intentionally exposed to scanner API (appropriate) | Mitigated |
| DoS | Availability | `newGroupCount` capped at 100; scanner API rate-limited at 60/min | Mitigated |
| EoP | Authorization | `manageGuestLists` policy restricts to Organizer; scanner type checks on API | Mitigated |

### 3.2 Data Flow & Trust Boundaries

```
Browser (Alpine/Livewire)           -- UNTRUSTED
    |
    | wire:click, wire:model, $wire
    |
=== BOUNDARY: Client -> Livewire Component ===
    |
GuestListShow (auth + Gate + #[Locked])   -- TRUSTED (after validation)
    |                                        EXCEPT: $entryGear, $gearItemIds (F-1, F-2)
    | ->execute()
    |
Actions (CreateGuestList, etc.)     -- TRUSTED
    |
=== BOUNDARY: App -> Database ===
    |
Database (Eloquent parameterized)
```

```
Scanner PWA (IndexedDB + Alpine)    -- UNTRUSTED
    |
    | fetch() with X-Scanner-Token
    |
=== BOUNDARY: Client -> Scanner API ===
    |
ScannerApiMiddleware (token lookup + active check)  -- PARTIALLY TRUSTED
    |
ScannerDataController (scanner_id match + type check + scoping)  -- TRUSTED
    |
=== BOUNDARY: App -> Database ===
```

### 3.3 Abuse Cases

| Abuse Case | Mitigated? |
|---|---|
| Attacker crafts POST to call any Livewire public method | Yes -- `mount()` enforces `Gate::authorize('manageGuestLists')` |
| Attacker modifies `$projectId` in snapshot | Yes -- `#[Locked]` prevents tampering |
| Attacker modifies `$editingEntryId` in snapshot | Yes -- `#[Locked]` prevents tampering |
| Attacker injects cross-project gear IDs via `$entryGear` | **No** -- F-1 (unvalidated) |
| Attacker injects cross-project gear item IDs via `$gearItemIds` | **Partially** -- F-2 (stored as JSON only) |
| Attacker uses scanner token from another project to check in guests | Yes -- scanner-to-guest-list scoping via `scanner_id` match |
| Attacker brute-forces QR tokens | Mitigated -- 256-bit entropy, rate limiting |
| Attacker re-confirms guest list to spam emails | Partially -- domain exception prevents re-confirm, but race condition (F-4) |

---

## Phase 4: Findings Summary

### All Findings

| # | Category | Severity | Description | Status |
|---|----------|----------|-------------|--------|
| F-1 | A01 Broken Access Control | **HIGH** | `$entryGear` array passed to `updateOrCreate()` without validation; cross-project IDOR on `project_gear_item_id` | New |
| F-2 | A01 Broken Access Control | **MEDIUM** | `$gearItemIds`/`$editGearItemIds` arrays not validated against project scope | New |
| F-3 | A04 Insecure Design | **MEDIUM** | `ConfirmGuestListJob` lacks `ShouldBeUnique`; duplicate emails on double-dispatch | New |
| F-4 | A04 Insecure Design | **LOW** | Race condition in `ConfirmGuestList` (TOCTOU on status check) | New |
| F-5 | A06 Vulnerable Components | **LOW** | npm audit: picomatch + undici (build-time only) | New |
| F-6 | A09 Logging Failures | **LOW** | No audit logging for guest list confirmation | New |
| P-1 | A02 Crypto | -- | `qr_token` in `$hidden` on GuestEntry | Verified-fixed |
| P-2 | A01 Access Control | -- | `$editingEntryId` uses `#[Locked]` | Verified-fixed |
| P-4 | A03 Injection | -- | `status`/`selection` validation has `max:255` | Verified-fixed |
| S-4 | A04 Insecure Design | -- | `ConfirmGuestList` uses `GuestListStatus` enum | Verified-fixed |

### Positive Security Findings

1. **Strong QR token entropy** -- 256 bits via `bin2hex(random_bytes(32))`, unique constraint in DB.
2. **Consistent IDOR protection** -- All Livewire methods scope entity lookups through locked parent IDs (`$guestListId`, `$projectId`).
3. **Proper authorization model** -- `manageGuestLists` policy + `Gate::authorize()` in `mount()` on both components.
4. **Scanner API scoping** -- Guest check-in and gear pickup endpoints scope through scanner ownership chain (`scanner_id` or `project_id`).
5. **No XSS vectors** -- All Blade output uses `{{ }}` or `x-text`; `{!! !!}` only for server-generated SVG.
6. **Rate limiting** -- Scanner API endpoints are rate-limited (`throttle:60,1`).
7. **Cascade deletes** -- Foreign keys use `cascadeOnDelete` ensuring orphaned records are cleaned up.
8. **Group count cap** -- `newGroupCount` validated `max:100` prevents unbounded entry creation.
9. **Scanner type enforcement** -- Each API endpoint checks `$scanner->type` before processing.
10. **CSRF protection** -- All POST endpoints are either Livewire (automatic CSRF) or scanner API (token-authed, no cookies).

---

## Recommended Next Steps

1. **Fix F-1 immediately** -- Add validation for `$entryGear` in `GuestListShow::saveEntry()` before next deployment.
2. **Fix F-2 and F-3** -- Add gear item ID validation and `ShouldBeUnique` to the confirmation job.
3. **Add `roave/security-advisories`** -- `composer require --dev roave/security-advisories:dev-latest`.
4. **Run `npm audit fix`** -- Address build-time dependency vulnerabilities.
5. **Consider adding PHPStan/Larastan** -- For ongoing type-safety and potential taint analysis.
6. **Add security headers middleware** -- CSP, HSTS, X-Frame-Options (project-wide, not M12-specific).
