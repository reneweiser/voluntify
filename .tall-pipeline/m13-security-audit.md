# M13 Security Audit Report — Voluntify

**Milestone:** m13-polish (Communication & Polish)
**Audit Date:** 2026-04-01
**Auditor:** Automated + Manual Code Review
**Stack:** Laravel 13.2 / Livewire 4.2 / Alpine.js / Tailwind CSS 4.2 / MariaDB
**Scope:** All 19 M13 features — 60+ files reviewed

---

## Summary

| Severity | Count |
|----------|-------|
| Critical | 0 |
| High | 1 |
| Medium | 4 |
| Low | 5 |
| Info | 4 |
| **Total** | **14** |

**Overall Risk Assessment:** LOW-MEDIUM. No critical vulnerabilities found. One high-severity XSS finding in project website markdown rendering. Authorization checks are consistently applied across all Livewire components. The codebase demonstrates strong security practices including `#[Locked]` usage, policy enforcement, parameterized queries, and password-confirmed deletion flows.

---

## Phase 1: Automated Scanning Results

### 1.1 Dependency Audits

**Composer:** No security vulnerability advisories found.

**NPM:** 2 high-severity vulnerabilities found:
- `picomatch <=2.3.1` — ReDoS + Method Injection (build-time dependency only)
- `undici 7.0.0–7.23.0` — Multiple HTTP smuggling / DoS issues (Node runtime dependency)

Both are fixable via `npm audit fix`. These are build-time/dev dependencies and do not directly affect production PHP runtime, but should be updated.

### 1.2 Static Analysis

- **PHPStan/Larastan:** Not installed. *Recommendation: add `larastan/larastan` for static type analysis.*
- **Psalm Taint Analysis:** Not installed. *Recommendation: add for data-flow tracking.*
- **roave/security-advisories:** Not installed. *Recommendation: `composer require --dev roave/security-advisories:dev-latest`*

### 1.3 Secret Detection

- `.env` is properly listed in `.gitignore` (`.env`, `.env.backup`, `.env.production`).
- No `.env` files found committed in git history.

### 1.4 Environment Configuration

- `.env.example` has `APP_DEBUG=true` (appropriate for example file).
- `SESSION_DRIVER=database` (good — not `file`).
- `BCRYPT_ROUNDS=12` (good).
- Production password rules enforced: min 12, mixed case, letters, numbers, symbols, uncompromised.

### 1.5 Security Headers

No security headers middleware detected (no CSP, HSTS, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy configuration found).

### 1.6 Debug Tool Exposure

No Telescope, Horizon, or Debugbar detected in dependencies. Clean.

---

## Phase 2: Findings

### Findings Table

| # | Severity | Category | File(s) | Finding | Recommendation |
|---|----------|----------|---------|---------|----------------|
| 1 | HIGH | xss | `ProjectWebsite.php:33`, `project-website.blade.php:25` | Stored XSS via markdown rendering of `website_description` | Sanitize HTML output from `Str::markdown()` |
| 2 | MEDIUM | xss | `EmailTemplateEditor.php:95`, `email-template-editor.blade.php:95` | Stored XSS in email template preview via `Str::markdown()` | Sanitize or limit preview to authenticated organizers (current state) |
| 3 | MEDIUM | rate-limiting | `AppServiceProvider.php:59-71` | `magic-link-request` and `email-verification-resend` rate limiters defined but NOT wired to any route or component | Wire limiters to actual endpoints or remove dead code |
| 4 | MEDIUM | livewire-exposure | `AnnouncementComposer.php:55-69` | Filter queries use user-controllable `selectedEventId`/`selectedJobId` without verifying they belong to the project | Scope queries through project relationship |
| 5 | MEDIUM | livewire-exposure | `EventSettings.php:25`, `ProjectShow.php:24`, `ProjectWebsiteEditor.php:16`, `Dashboard.php:25` | Model-bound public properties without `#[Locked]` | Add `#[Locked]` to prevent snapshot tampering |
| 6 | LOW | input-validation | `EmailTemplateEditor.php:97` | Email template `body` field has no `max` validation rule | Add `'max:10000'` to prevent oversized payloads |
| 7 | LOW | input-validation | `Dashboard.php:163-165` | `#[Url]` search property used directly in LIKE clauses without sanitizing `%` and `_` wildcards | Escape LIKE wildcards in search input |
| 8 | LOW | data-leakage | `VolunteerPortal.php:127-131` | Announcements query returns ALL project announcements, not filtered to volunteer's signed-up events | Filter to volunteer's relevant events |
| 9 | LOW | cascade | `announcements.created_by` FK | `on_delete: restrict` on `created_by` prevents deleting users who created announcements | Change to `SET NULL` or `RESTRICT` with documented workflow |
| 10 | LOW | other | `PromoteVolunteer.php:94` | Temporary password stored in plaintext in memory and sent in notification | Acceptable for one-time temp passwords with `must_change_password` flag |
| 11 | INFO | other | No security headers middleware | Missing CSP, HSTS, X-Frame-Options, X-Content-Type-Options | Add security headers middleware |
| 12 | INFO | other | `composer.json` | Missing `roave/security-advisories` | Add `composer require --dev roave/security-advisories:dev-latest` |
| 13 | INFO | other | `composer.json` | Missing static analysis tooling (Larastan/Psalm) | Add for CI pipeline |
| 14 | INFO | other | `package.json` | npm high-severity vulnerabilities in picomatch and undici | Run `npm audit fix` |

---

## Phase 3: Detailed Findings

### [HIGH] #1 — Stored XSS via Project Website Markdown Rendering

**OWASP Category:** A03:2021 — Injection
**Location:** `app/Livewire/Public/ProjectWebsite.php:33`, `resources/views/livewire/public/project-website.blade.php:25`

**Evidence:**
```php
// ProjectWebsite.php:33
return Str::markdown($this->project->website_description);
```
```blade
{{-- project-website.blade.php:25 --}}
{!! $renderedDescription !!}
```

`Str::markdown()` converts Markdown to HTML but does NOT sanitize the output. An organizer can write arbitrary HTML/JS in `website_description` that will execute in every visitor's browser on the public project website. While the input comes from an authenticated organizer, this creates a Stored XSS vector where:

1. A compromised organizer account could inject malicious scripts affecting all public visitors.
2. A privilege escalation chain: if an attacker gains organizer access (e.g., via the promote-to-organizer flow), they can inject scripts on the public-facing page.

**Risk:** Stored XSS on a public (unauthenticated) page. Any visitor to `p/{publicToken}` would execute the injected script. Could steal session cookies of logged-in organizers who visit the same page.

**Remediation:**
```php
// Option A: Use a sanitizer (recommended)
// composer require tgalopin/html-sanitizer
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

public function renderedDescription(): ?string
{
    if (! $this->project->website_description) {
        return null;
    }

    $html = Str::markdown($this->project->website_description);
    return app(HtmlSanitizerInterface::class)->sanitize($html);
}

// Option B: Use strip_tags with allowed tags
public function renderedDescription(): ?string
{
    if (! $this->project->website_description) {
        return null;
    }

    $html = Str::markdown($this->project->website_description);
    return strip_tags($html, '<p><br><strong><em><ul><ol><li><h1><h2><h3><h4><h5><h6><a><blockquote><code><pre>');
}
```

---

### [MEDIUM] #2 — XSS in Email Template Preview

**OWASP Category:** A03:2021 — Injection
**Location:** `app/Livewire/Events/EmailTemplateEditor.php:127-136`, `resources/views/livewire/events/email-template-editor.blade.php:95`

**Evidence:**
```php
// EmailTemplateEditor.php:135
$this->previewBody = $rendered['body'];
```
```blade
{{-- email-template-editor.blade.php:95 --}}
{!! \Illuminate\Support\Str::markdown($previewBody) !!}
```

The `$previewBody` contains organizer-authored template content rendered via `Str::markdown()` and output unescaped. An organizer could inject HTML/JS into the template body, which would execute in their own (or another organizer's) browser when previewing.

**Risk:** Self-XSS or intra-org XSS. Mitigated by the fact that this is behind authentication and only accessible to project organizers. Severity is lower than #1 because the attack surface is limited to authenticated users who already have project access.

**Remediation:** Same as #1 — sanitize the output of `Str::markdown()` before unescaped rendering. Apply the same sanitizer consistently across the application.

---

### [MEDIUM] #3 — Dead Rate Limiters for Magic Links and Email Verification

**OWASP Category:** A04:2021 — Insecure Design
**Location:** `app/Providers/AppServiceProvider.php:59-71`

**Evidence:**
```php
RateLimiter::for('magic-link-request', function (Request $request) {
    return [
        Limit::perHour(3)->by('magic-link:'.$request->input('email', '')),
        Limit::perMinute(10)->by('magic-link-ip:'.$request->ip()),
    ];
});

RateLimiter::for('email-verification-resend', function (Request $request) {
    return [
        Limit::perHour(3)->by('verify-resend:'.$request->input('email', '')),
        Limit::perMinute(10)->by('verify-resend-ip:'.$request->ip()),
    ];
});
```

These rate limiters are defined but grep confirms they are NOT referenced anywhere in routes, middleware, or Livewire components. The actual magic link and email verification endpoints are unthrottled (beyond the general `throttle:60,1` on the public event signup route).

**Risk:** An attacker can trigger unlimited magic link emails or verification emails, potentially used for email bombing a target address or consuming email sending quota.

**Remediation:**
Wire the rate limiters to the relevant Livewire actions or route middleware. For example, if magic links are requested from a Livewire component:

```php
// In the Livewire component that sends magic links:
public function requestMagicLink(): void
{
    $key = 'magic-link:' . $this->email;
    if (RateLimiter::tooManyAttempts($key, 3)) {
        $this->addError('email', 'Zu viele Anfragen. Bitte warte.');
        return;
    }
    RateLimiter::hit($key, 3600);
    // ... send magic link
}
```

---

### [MEDIUM] #4 — Announcement Composer IDOR on Filter IDs

**OWASP Category:** A01:2021 — Broken Access Control
**Location:** `app/Livewire/Projects/AnnouncementComposer.php:55-69`

**Evidence:**
```php
// jobs() computed — line 55
return VolunteerJob::where('event_id', (int) $this->selectedEventId)
    ->orderBy('name')
    ->get();

// shifts() computed — line 67
return Shift::where('volunteer_job_id', (int) $this->selectedJobId)
    ->orderBy('shift_date')
    ->get();
```

The `selectedEventId` and `selectedJobId` are public Livewire properties that a client can modify via snapshot tampering. These IDs are used to query `VolunteerJob` and `Shift` without scoping through the project relationship. An attacker could set `selectedEventId` to an event ID from a different project/organization to enumerate jobs and shifts.

The actual `send()` method does scope recipients through `project_id` (line 75 in `recipientCount`), so the announcement itself is correctly scoped. However, the computed properties leak organizational structure data (job names, shift dates) from other organizations.

**Risk:** Information disclosure — job names and shift dates from other organizations could be leaked through the Livewire snapshot. Does not affect the actual announcement delivery scope.

**Remediation:**
```php
#[Computed]
public function jobs(): Collection
{
    if (! $this->selectedEventId) {
        return new Collection;
    }

    return VolunteerJob::where('event_id', (int) $this->selectedEventId)
        ->whereHas('event', fn ($q) => $q->where('project_id', $this->project->id))
        ->orderBy('name')
        ->get();
}

#[Computed]
public function shifts(): Collection
{
    if (! $this->selectedJobId) {
        return new Collection;
    }

    return Shift::where('volunteer_job_id', (int) $this->selectedJobId)
        ->whereHas('volunteerJob', fn ($q) => $q->whereHas(
            'event', fn ($eq) => $eq->where('project_id', $this->project->id)
        ))
        ->orderBy('shift_date')
        ->get();
}
```

---

### [MEDIUM] #5 — Model Properties Without `#[Locked]`

**OWASP Category:** A01:2021 — Broken Access Control
**Location:** Multiple components

**Evidence:** Several Livewire components expose Eloquent model instances as public properties without the `#[Locked]` attribute:

| Component | Property | Risk |
|-----------|----------|------|
| `EventSettings.php:25` | `public Event $event` | Model ID tamper |
| `ProjectShow.php:24` | `public Project $project` | Model ID tamper |
| `ProjectWebsiteEditor.php:16` | `public Project $project` | Model ID tamper |
| `Dashboard.php:25` | `public string $search` (via `#[Url]`) | Validated but unlocked — acceptable |
| `EmailTemplateEditor.php:18` | `public Event $event` | Model ID tamper |
| `AnnouncementComposer.php:19` | `public Project $project` | Model ID tamper |
| `GearSummary.php:18` | `public Project $project` | Model ID tamper |

Livewire model binding provides some built-in protection — when a model is used as a public property, Livewire validates the model exists on hydration. However, without `#[Locked]`, an attacker can modify the model ID in the wire:snapshot to point to a different model. The authorization checks in `mount()` will NOT re-run on subsequent requests.

The mitigation here is that most of these components have `Gate::authorize()` calls in each public method body (e.g., `saveEvent()`, `saveProject()`), which will catch unauthorized access. However, computed properties like `availableProjects()` in `EventSettings` do NOT re-check authorization.

**Risk:** Without `#[Locked]`, an attacker could tamper with the model ID to access computed data from other models. The per-method authorization checks mitigate state-changing operations, but read-only computed properties are exposed.

**Remediation:**
Add `#[Locked]` to all Eloquent model public properties:

```php
#[Locked]
public Event $event;

#[Locked]
public Project $project;
```

This is a simple, low-risk change. Components that already use `#[Locked]` correctly: `HintTextSettings`, `VolunteerDetail`, `EventSignup`, `ScannerAuth`.

---

### [LOW] #6 — Email Template Body Missing Max Length Validation

**OWASP Category:** A03:2021 — Injection
**Location:** `app/Livewire/Events/EmailTemplateEditor.php:97`

**Evidence:**
```php
$this->validate([
    'subject' => ['required', 'string', 'max:255'],
    'body' => ['required', 'string'],  // No max length
]);
```

The `body` field accepts a string of unlimited length. The database column is `text` type which can hold up to 65KB. While this isn't a critical vulnerability, it could be used for DoS by submitting extremely large payloads.

Compare with `AnnouncementComposer.php:129` which correctly limits to `max:10000`.

**Remediation:**
```php
'body' => ['required', 'string', 'max:10000'],
```

---

### [LOW] #7 — Dashboard Search LIKE Wildcard Injection

**OWASP Category:** A03:2021 — Injection
**Location:** `app/Livewire/Dashboard.php:163-165`

**Evidence:**
```php
$q->where('first_name', 'like', "%{$this->search}%")
    ->orWhere('last_name', 'like', "%{$this->search}%")
    ->orWhere('email', 'like', "%{$this->search}%");
```

The `$search` property is bound via `#[Url]` and used directly in LIKE clauses. While this is parameterized (not SQL injection), the `%` and `_` characters in user input are interpreted as LIKE wildcards, potentially causing unexpected search results or performance degradation with patterns like `%%%%%`.

**Risk:** Low — no data exposure beyond what the user is authorized to see. Minor performance risk with wildcard-heavy search terms.

**Remediation:**
```php
$escaped = str_replace(['%', '_'], ['\%', '\_'], $this->search);
$q->where('first_name', 'like', "%{$escaped}%")
    ->orWhere('last_name', 'like', "%{$escaped}%")
    ->orWhere('email', 'like', "%{$escaped}%");
```

---

### [LOW] #8 — Volunteer Portal Shows All Project Announcements

**OWASP Category:** A01:2021 — Broken Access Control
**Location:** `app/Livewire/Public/VolunteerPortal.php:127-131`

**Evidence:**
```php
public function announcements(): Collection
{
    // ...
    return Announcement::where('project_id', $this->volunteer->project_id)
        ->whereNotNull('sent_at')
        ->with('event')
        ->latest('sent_at')
        ->get();
}
```

This query returns all sent announcements for the project, regardless of whether they were targeted at specific events/jobs/shifts. A volunteer who is only signed up for Event A could see announcements that were specifically targeted at Event B volunteers.

**Risk:** Low — announcements are informational and the volunteer already belongs to the project. However, targeted announcements were designed to reach specific recipients, not all project volunteers.

**Remediation:**
```php
#[Computed]
public function announcements(): Collection
{
    if (! $this->volunteer) {
        return new Collection;
    }

    $eventIds = $this->volunteerEventIds();

    return Announcement::where('project_id', $this->volunteer->project_id)
        ->whereNotNull('sent_at')
        ->where(function ($q) use ($eventIds) {
            $q->whereNull('event_id')  // Project-wide announcements
                ->orWhereIn('event_id', $eventIds);  // Event-specific for their events
        })
        ->with('event')
        ->latest('sent_at')
        ->get();
}
```

---

### [LOW] #9 — Announcements `created_by` FK Prevents User Deletion

**OWASP Category:** A04:2021 — Insecure Design
**Location:** Database schema — `announcements.created_by` foreign key

**Evidence:** The `announcements.created_by` FK uses `on_delete: restrict`. If an organization tries to delete a user who has created announcements, the deletion will fail with a foreign key constraint violation.

**Risk:** Low — operational inconvenience, not a security vulnerability. The system would throw a 500 error if attempted.

**Remediation:** Change to `SET NULL` in a migration:
```php
$table->foreignId('created_by')->nullable()->change();
// And update FK to set null on delete
```

---

### [LOW] #10 — Temporary Password in Memory

**OWASP Category:** A07:2021 — Identification and Authentication Failures
**Location:** `app/Actions/PromoteVolunteer.php:88-127`

**Evidence:**
```php
$temporaryPassword = Str::random(16);
// ... later ...
$promotion->user->notify(new VolunteerPromoted(
    $organization,
    'Organizer',
    $temporaryPassword,
));
```

The temporary password is held in memory and passed through the notification system. The `must_change_password` flag is set correctly, forcing a password change on first login.

**Risk:** Low — acceptable pattern for one-time temporary passwords. The password is never logged or persisted in plaintext. The `must_change_password` flag ensures it cannot be used long-term.

**Remediation:** No change required. This is an accepted pattern. The `User::create()` call properly hashes the password via Laravel's model mutator.

---

## Phase 3: STRIDE Threat Model Summary

### Trust Boundaries

```
[Browser / Alpine.js]  ← UNTRUSTED
        |
  ══ Livewire AJAX / wire:snapshot ══
        |
[Livewire Public Properties]  ← PARTIALLY TRUSTED
        |  - #[Locked] prevents tampering
        |  - #[Url] is client-controlled
        |  - Gate::authorize() in public methods
        |
[Actions / Services]  ← TRUSTED
        |
  ══ Eloquent / Database ══
        |
[MariaDB with FK cascades]
```

### Key STRIDE Analysis

| Feature | Spoofing | Tampering | Repudiation | Info Disclosure | DoS | Elevation |
|---------|----------|-----------|-------------|-----------------|-----|-----------|
| Scanner Auth (#80) | Rate limited (5/30min) | `#[Locked]` on token | Lockout event logged | - | Rate limited | Code + token required |
| Announcements (#87) | Auth + Gate | Gate in `send()` | `created_by` tracked | **#4 IDOR on filters** | `max:10000` body | Gate enforced |
| Data Deletion (#79) | Auth + password confirm | Password verified | - | - | 30-day grace period | `delete` policy required |
| Project Website (#83) | N/A (public) | Auth for editing | - | **#1 XSS** | - | - |
| Clone (#78) | Auth + Gate | Validates offset range | Event logged | - | DB transaction | `create` policy required |
| Promote (#66) | Auth + Gate | `#[Locked]` on models | Promotion logged | - | - | Scanner scoped to org |
| Dashboard (#76) | Auth + org middleware | `#[Url]` search | - | Scoped to user's projects | **#7 LIKE wildcards** | Role-filtered queries |
| Hint Texts (#74) | Auth + Gate | `#[Locked]` on project | - | Escaped in Blade `{{ }}` | `max:2000` text | - |
| Volunteer Portal | Magic link auth | Signup ownership check | - | **#8 all project announcements** | - | `abort(403)` on mismatch |

---

## Clean Areas (No Findings)

The following areas passed review with no security issues:

1. **Scanner Auth (`ScannerAuth.php`)** — Properly rate limited (5 attempts / 30 min), `#[Locked]` on `scannerToken`, session regeneration on success, lockout event dispatch. Well implemented.

2. **Data Deletion Flow** — Password confirmation via `Hash::check()`, 30-day grace period, restore capability, policy checks on all operations. `PurgePendingDeletionsCommand` correctly scopes by `deletion_requested_at` timestamp.

3. **Clone Operations (`CloneProject.php`, `CloneEvent.php`)** — Wrapped in DB transactions, properly excludes sensitive fields (`public_token`, `scanner_token`, `title_image_path`, `deletion_requested_at`), regenerates unique tokens. Status reset to Draft.

4. **MATCH AGAINST SQL Injection (#67)** — The fulltext search in `Volunteer.php:139-153` is safe. User input is sanitized via `preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $search)` which strips all special characters. The cleaned term is passed as a parameterized binding `[$booleanTerm]`.

5. **Hint Text Display** — All hint text output uses escaped Blade syntax `{{ }}`, not unescaped `{!! !!}`. XSS-safe.

6. **Cancellation Rework (`CancelShiftSignup.php`)** — Checks cancellation enabled, cutoff hours, existing cancellation state. `VolunteerPortal.php:180` verifies `$signup->volunteer_id === $this->volunteer->id` before cancelling.

7. **Email Templates (#81, #55)** — Template rendering uses simple string replacement (`str_replace`), not eval or dynamic code execution. Placeholders are pre-defined per template type.

8. **Re-publish Notification (#84)** — Job is `ShouldQueue`, correctly scopes to volunteers with active signups for the specific event, only notifies verified emails.

9. **Cancellation Digest (#85)** — Command correctly groups by project, uses `notification_email` or `contact_email` fallback, only sends for non-empty cancellation sets.

10. **FK Cascades** — Properly configured cascade deletes for all parent-child relationships. `hint_texts`, `announcements`, `announcement_templates` all cascade on project/org delete.

11. **File Uploads** — Both `EventSettings.php:91` and `ProjectShow.php:109` validate: `'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'`.

12. **CSRF** — All state-changing operations go through Livewire's AJAX endpoint which includes CSRF protection automatically.

13. **Rate Limiting on Public Signup** — `EventSignup.php` applies inline rate limiting: 15/min for shift reservation, 5/5min for signup submission.

14. **Event Signup Gear/Field Filtering** — `EventSignup.php:294-303` correctly strips gear selections and custom field responses to only valid IDs for the current event, preventing snapshot injection of foreign IDs.

15. **Activity Log (#64)** — `ActivityFeed.php:33` checks `hasAccessToOrganization()` and all queries scope through `currentOrganization()->id`.

16. **Gear Summary (#77)** — Read-only computed properties with `Gate::authorize('view')` in mount. CSV export requires `Gate::authorize('update')`.

17. **Optional Shift Times (#86)** — `Shift.php:78-81` `hasDefinedTimes()` correctly checks for null. `attendanceStatusAt()` returns `OnTime` when times are undefined. No null pointer exceptions.

18. **Notification ShouldQueue** — All notifications (`AnnouncementNotification`, `EventRepublishedNotification`, `CancellationDigestNotification`) implement `ShouldQueue`.

19. **Job Idempotency** — `SendAnnouncement.php:13` checks `$announcement->isSent()` at the start, preventing double-sends on retry. `SendAnnouncementJob` has `tries: 3` with backoff.

---

## Recommended Actions

### Immediate (Before Release)

1. **Fix #1 (HIGH)** — Sanitize `Str::markdown()` output in `ProjectWebsite.php`. Install `symfony/html-sanitizer` or use `strip_tags()` with an allowlist.

### Short-Term (Within 30 Days)

2. **Fix #4 (MEDIUM)** — Scope `AnnouncementComposer` filter queries through project relationship.
3. **Fix #5 (MEDIUM)** — Add `#[Locked]` to all Eloquent model public properties in Livewire components.
4. **Fix #3 (MEDIUM)** — Wire `magic-link-request` and `email-verification-resend` rate limiters to their actual endpoints, or remove the dead definitions.
5. **Fix #2 (MEDIUM)** — Sanitize email template preview markdown output.

### Backlog (Within 90 Days)

6. **Fix #6-#9 (LOW)** — Validation tightening, LIKE wildcard escaping, announcement scoping, FK constraint update.
7. **Tooling (#11-#14 INFO)** — Add security headers middleware, `roave/security-advisories`, static analysis, npm audit fix.

---

## Quality Gate Checklist

- [x] **OWASP coverage** — All 10 OWASP 2021 categories reviewed
- [x] **STRIDE analysis** — Completed for each major feature
- [x] **Automated scans** — `composer audit`, `npm audit`, secret detection documented
- [x] **Findings complete** — Every finding has severity, evidence (file:line), and remediation
- [x] **No unclassified findings** — All 14 findings assigned severity levels
- [x] **Executive summary** — Written with risk assessment and priority actions
- [x] **Remediation code** — Secure code examples provided for all actionable findings
- [x] **Report written** — `.tall-pipeline/m13-security-audit.md`
