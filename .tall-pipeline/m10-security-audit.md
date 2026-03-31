# Security Audit — M10 Signup Flow Rewrite

**Date:** 2026-03-31
**Scope:** M10 Signup Flow Rewrite
**Auditor:** Security Paranoid (automated)

---

## Executive Summary

The M10 signup flow rewrite is well-structured with several strong security properties: shift-ID ownership is double-validated at both the Livewire layer and the action layer, token hashing uses SHA-256 with `hash_equals` for timing-safe comparison, and reservations are correctly protected with database-level row locks inside transactions. Two notable gaps stand out: the public signup wizard has no rate limiting, enabling bot-driven reservation exhaustion and volunteer record spam; and the `ManualEnrollment` component performs a cross-project `Volunteer::find()` lookup that allows an authenticated organizer to enroll a volunteer from a different project into their event. A low-severity missing index on `token_hash` in `email_verification_tokens` is also noted.

---

## Findings

### [HIGH] No Rate Limiting on Public Signup Wizard

**File:** `app/Livewire/Public/EventSignup.php` / `routes/web.php:54`
**Category:** Rate Limiting
**Severity:** High

**Description:**
The public event signup route (`events/{publicToken}`) and all Livewire action methods (`reserveAndAdvance`, `submitSignup`) are completely unthrottled. The `throttle:public-api` rate limiter defined in `AppServiceProvider` is applied only to `routes/api.php`, not to the public Livewire routes. The `RateLimiter::for('public-api')` at 60 req/min is never referenced for these routes.

This creates two concrete abuse vectors:

1. **Reservation exhaustion:** An attacker can send rapid requests to `reserveAndAdvance()` with different session IDs (each HTTP request gets a new unauthenticated session), consuming all slots for every shift of a high-demand event before real volunteers can sign up. With a 20-minute TTL and a 1-minute cleanup cycle, capacity can stay locked for up to 20 minutes at a time.

2. **Volunteer record spam:** `submitSignup()` → `ProcessVolunteerSignup` calls `Volunteer::firstOrCreate()`. A bot submitting random email addresses will create hundreds of unverified volunteer records in the project's namespace with no friction.

**Proof of Concept:**

```bash
# Session enumeration: each request uses a distinct session cookie, creating a new reservation
for i in $(seq 1 100); do
  curl -s -X POST https://example.com/livewire/update \
    -H "Cookie: laravel_session=sess_$i" \
    -d '{"components":[{"calls":[{"method":"reserveAndAdvance"}]}]}'
done
```

Or more realistically: a bot script POSTing the Livewire update endpoint with `selectedShiftIds: [1,2,3]` from a pool of proxied IPs will exhaust shift reservations.

**Remediation:**
Apply throttle middleware to the public event signup route:

```php
// routes/web.php
Route::livewire('events/{publicToken}', EventSignup::class)
    ->name('events.public')
    ->middleware('throttle:30,1');  // 30 req/min per IP
```

Additionally, consider adding per-IP rate limiting inside `reserveAndAdvance()` using `RateLimiter::attempt()`, scoped by IP + publicToken. For `submitSignup()`, a more restrictive limit per email address (e.g. 5 attempts per 10 minutes) would limit volunteer record spam.

---

### [MEDIUM] Cross-Project Volunteer Enrollment in ManualEnrollment

**File:** `app/Livewire/Events/ManualEnrollment.php:94` and `:112`
**Category:** Auth/Authz
**Severity:** Medium

**Description:**
The `selectedVolunteer` computed property uses a bare `Volunteer::find($this->selectedVolunteerId)` with no project or organization scope. Separately, the `enroll()` method calls `Volunteer::findOrFail($this->selectedVolunteerId)` again without scoping. Because `selectedVolunteerId` is `#[Locked]` (cannot be tampered with by a client), the initial population via `selectVolunteer(int $volunteerId)` is the exposure point.

`selectVolunteer()` accepts any arbitrary integer `$volunteerId` directly from the client (it is a public Livewire action parameter). An authenticated Organizer can call `wire:click="selectVolunteer(9999)"` from the browser dev console, supplying the ID of a volunteer belonging to a completely different project/organization. The `#[Locked]` attribute on `selectedVolunteerId` only prevents the *property value* from being changed after hydration — it does not protect the *method argument* passed to `selectVolunteer()`.

Once a cross-project volunteer is selected, `enroll()` will call `SignUpVolunteerForShifts::execute()` which does validate that shift IDs belong to the event — but the volunteer object itself is never cross-checked against the event's project.

**Proof of Concept:**

```javascript
// In browser console on /admin/events/5/enroll
// Suppose volunteer ID 999 belongs to a completely different organization
await Livewire.find('...').call('selectVolunteer', 999)
// selectedVolunteerId is now 999 (Locked from further tampering)
// Click "Enroll" — the attacker's volunteer is enrolled in their victim event
```

**Remediation:**
Scope the lookup to the event's project in `selectVolunteer()` and in both find calls:

```php
public function selectVolunteer(int $volunteerId): void
{
    $volunteer = Volunteer::where('project_id', $this->event->project_id)
        ->findOrFail($volunteerId);

    $this->selectedVolunteerId = $volunteer->id;
    // ...
}
```

Apply the same scoping in `enroll()`:

```php
$volunteer = Volunteer::where('project_id', $this->event->project_id)
    ->findOrFail($this->selectedVolunteerId);
```

---

### [MEDIUM] Missing Unique Index on `email_verification_tokens.token_hash`

**File:** `database/migrations/2026_03_02_140456_create_email_verification_tokens_table.php:19`
**Category:** Token Security
**Severity:** Medium

**Description:**
The `token_hash` column in `email_verification_tokens` has no database index at all — neither unique nor regular. This has two consequences:

1. **No uniqueness enforcement at the database level.** If two requests race to create a token for the same volunteer, both could be inserted. Laravel's `where('token_hash', $hash)->firstOrFail()` in `CompleteEmailVerification` would return one arbitrarily. This is a low-probability race condition but the database provides no safety net.

2. **Full table scan on every verification.** Every time a volunteer clicks their verification link, `CompleteEmailVerification::execute()` runs `EmailVerificationToken::where('token_hash', $hashed->hash)->firstOrFail()`. Without an index this is an O(n) table scan. As the table grows (tokens aren't cleaned up after use unless deletion succeeds), this slows down verification.

Note: The `magic_link_tokens` table has the same issue — but that table predates M10 and is out of scope.

**Proof of Concept:**
N/A for the index issue — it's a configuration gap rather than an exploitable vulnerability. However, token exhaustion / slowdown attacks become more feasible as the table grows unchecked.

**Remediation:**

```php
// In the migration (or a new migration):
$table->string('token_hash')->unique()->change();
```

Or add a new migration:

```php
Schema::table('email_verification_tokens', function (Blueprint $table) {
    $table->unique('token_hash');
});
```

---

### [LOW] Private Events Accessible via Public Signup if Status is PublishedOpen

**File:** `app/Livewire/Public/EventSignup.php:52-54`
**Category:** Auth/Authz
**Severity:** Low

**Description:**
The `mount()` method filters by `status = PublishedOpen` but does NOT filter by `visibility`. The `EventVisibility::Private` enum case exists and is applied to events intended for invite-only access. However, a private event with status `PublishedOpen` will be fully accessible at `/events/{publicToken}` to anyone who guesses or obtains the public token.

```php
// current — visibility not checked
$this->event = Event::where('public_token', $publicToken)
    ->where('status', EventStatus::PublishedOpen)
    ->firstOrFail();
```

The `public_token` is a UUID-style opaque token so guessing is infeasible. However, the token is included in all signup confirmation emails sent to volunteers, in the QR ticket page, and in the volunteer portal. Any volunteer who signs up for any event in the same project can inspect their email and attempt to navigate to a private event's URL if they can observe the token from another channel.

This is likely an incomplete M8 feature rather than a bug in M10 itself, but the signup wizard is the point of exposure.

**Proof of Concept:**
Organizer creates an event with `visibility = private`, sets status to `PublishedOpen`. The event is intended for a closed group. However `/events/{publicToken}` accepts signups from anyone with the token.

**Remediation:**
Add a `visibility` check in `mount()` or create a scoped query method on Event:

```php
$this->event = Event::where('public_token', $publicToken)
    ->where('status', EventStatus::PublishedOpen)
    ->where('visibility', EventVisibility::Public)   // add this
    ->firstOrFail();
```

Alternatively define a named scope `scopePubliclyOpenForSignup()` on the `Event` model that combines all three conditions, and use it here and in any other public-facing queries.

---

### [LOW] `customFieldResponses` Array Keys Not Validated as Integer IDs

**File:** `app/Livewire/Public/EventSignup.php:35-38` and `validateGearAndCustomFields():278-284`
**Category:** Input Validation
**Severity:** Low

**Description:**
The public property `customFieldResponses` is declared as `array<int, mixed>` but Livewire receives it as a plain PHP array from the client. The array keys are used directly as `CustomRegistrationField` IDs in `RecordCustomFieldResponses::execute()`:

```php
$rawValue = $responses[$field->id] ?? null;
```

The validation rules in `validateGearAndCustomFields()` only add rules for fields that the server *knows about* — they don't explicitly reject extra keys. An attacker could submit `customFieldResponses` with additional keys beyond those expected. While `RecordCustomFieldResponses` only processes known field IDs (it iterates `$fields` from the database, not `$responses`), the raw array is serialized and stored in `email_verification_tokens.custom_field_responses` (JSON) — including any attacker-supplied extra keys.

Similarly, `gearSelections` keys are gear item IDs but extra keys in the array are not stripped before storage in `email_verification_tokens.gear_selections`.

This means the database row can contain attacker-controlled extra key-value pairs, which could cause unexpected behavior if that JSON is later processed by other code without explicit key filtering.

**Proof of Concept:**
Attacker submits wizard with `customFieldResponses: {1: "real_value", 99999: "injected_value"}`. The `email_verification_tokens` row stores both. If future code iterates the stored JSON expecting only valid field IDs, it encounters the injected key.

**Remediation:**
Before storing or passing `customFieldResponses` to actions, strip any keys that are not in the set of valid field IDs for the event:

```php
// In EventSignup::submitSignup() before passing to ProcessVolunteerSignup:
$validFieldIds = $this->customRegistrationFields->pluck('id')->all();
$customFieldResponses = array_intersect_key(
    $this->customFieldResponses,
    array_flip($validFieldIds)
);
```

Apply equivalent filtering for `gearSelections` against `$this->gearItems->pluck('id')`.

---

### [LOW] `reservationExpiresAt` Public Property Exposed to Client Manipulation

**File:** `app/Livewire/Public/EventSignup.php:32`
**Category:** Livewire Exposure
**Severity:** Low

**Description:**
`reservationExpiresAt` is a public string property with no `#[Locked]` attribute. It is set by the server to an ISO timestamp and used by the Alpine.js countdown timer in the view. A user could modify the value of this property via Livewire's wire protocol (e.g., `$wire.set('reservationExpiresAt', '2099-12-31T00:00:00Z')`) to prevent their Alpine countdown from reaching zero.

However, this is a client-side cosmetic concern only. The actual reservation expiry check is always performed against the database (`ShiftReservation::forSession()->active()->exists()`) in all critical server-side methods (`advanceToPersonalInfo`, `advanceToConfirmation`, `submitSignup`). The Alpine timer fires `handleReservationExpired()` as a convenience UX indicator, not as a security gate.

**Proof of Concept:**
User opens browser console and sets `$wire.reservationExpiresAt = '2099-01-01T00:00:00.000Z'` to disable the countdown visual. Their UI does not transition to the Expired state. However, server-side checks still enforce the real expiry — the reservation will be rejected at `submitSignup()` if the DB rows are expired.

**Remediation:**
Apply `#[Locked]` to `reservationExpiresAt` to prevent client mutation, and derive the countdown on the frontend from a locked property. This eliminates any potential future code paths that might read the property as authoritative:

```php
#[Locked]
public string $reservationExpiresAt = '';
```

---

### [INFORMATIONAL] No Token-Level Lookup Index on `email_verification_tokens.token_hash`

*(Covered above in the Medium finding — this informational note documents the query performance aspect separately.)*

**File:** `database/migrations/2026_03_02_140456_create_email_verification_tokens_table.php`
**Category:** Other
**Severity:** Informational

**Description:**
The `token_hash` column lacks a database index. Every token verification triggers a full table scan. This is also true for `magic_link_tokens.token_hash` (pre-M10). For current scale this is acceptable, but should be addressed before high-volume events.

---

### [INFORMATIONAL] `ReleaseExpiredReservations` Deletes in Batches but Loop Has No Guard

**File:** `app/Actions/ReleaseExpiredReservations.php:17-23`
**Category:** Other
**Severity:** Informational

**Description:**
The `do...while` loop will continue deleting in 1000-row batches until all expired reservations are gone. If the scheduler somehow runs two concurrent instances (e.g., scheduler overlapping on a busy server), both instances could run the same loop simultaneously. Laravel's database queue driver does not protect against scheduler overlap by default.

This is not an exploitable security issue — deleting expired reservations is idempotent. However, simultaneous instances wasted DB resources. The standard mitigation is to mark the scheduled command as `withoutOverlapping()`.

**Remediation:**

```php
// In routes/console.php:
Schedule::command('app:release-expired-reservations')
    ->everyMinute()
    ->withoutOverlapping();
```

---

## Clean Areas

- **Mass assignment:** `ShiftReservation::$fillable` correctly restricts to `shift_id`, `session_id`, `expires_at`. No guarded bypass risk.
- **SQL injection:** All queries use Eloquent parameterized bindings. The `scopeSearch()` FULLTEXT `whereRaw` correctly passes the term as a bound parameter array. No raw user string interpolation found.
- **XSS in event-signup view:** All output uses `{{ }}` (HTML-escaped). No `{!! !!}` blocks present in the signup or manual-enrollment views.
- **CSRF:** Livewire 4 handles CSRF natively on all `wire:click` and `wire:model` interactions. No raw form `action=""` bypasses. Public routes use Livewire's built-in CSRF protection.
- **Shift ID cross-event injection:** Double-validated — once via `Rule::exists('shifts', 'id')->where(...)` in the Livewire component, and again inside `ReserveShifts::execute()` and `SignUpVolunteerForShifts::execute()` via `Shift::whereIn('volunteer_job_id', $eventJobIds)`. An attacker cannot submit shift IDs from a foreign event.
- **Session reservation hijacking:** Reservations are keyed to server-generated Laravel session IDs. The session ID is never exposed in the response body or in Livewire properties. Stealing another user's reservation requires obtaining their session cookie via standard session hijacking (out of scope).
- **Email verification token security:** `Str::random(64)` generates a cryptographically random 64-character token (uses `random_bytes` internally). SHA-256 hashed on storage. `hash_equals` used for timing-safe comparison. 24-hour expiry enforced. Token is deleted on use. Solid.
- **Scheduler command attack surface:** `app:release-expired-reservations` takes no user input, runs only on the server scheduler, uses parameterized Eloquent queries. No exploitable surface.
- **Data enumeration:** Volunteer counts and shift availability are shown as concrete numbers on the public page but no internal IDs are exposed. The event is identified by opaque `public_token`. Error messages for duplicate/full signups do not distinguish individual volunteer states in a way that leaks cross-volunteer information.
- **ManualEnrollment auth:** `mount()` and both mutating methods (`enroll()`, `createAndSelect()`) all call `Gate::authorize('manageJobs', $this->event)`. The event itself is loaded via `currentOrganization()->events()->findOrFail()` in `mount()`, scoped to the current org. The `#[Locked]` attribute on `$event` prevents client-side event ID substitution.
- **`WizardState` cannot be tampered to skip steps:** `WizardState` is a server-side enum property. While it is not `#[Locked]`, skipping to `Confirming` or `Complete` without going through the action methods (`reserveAndAdvance`, etc.) does not bypass validation — `submitSignup()` independently re-validates personal info, gear/fields, and the DB reservation check.

---

## Summary Table

| # | Severity | Category | Finding | Resolution |
|---|---|---|---|---|
| 1 | High | Rate Limiting | No rate limiting on public signup wizard | Fixed: `throttle:60,1` on route + `RateLimiter` in `reserveAndAdvance()` (15/min) and `submitSignup()` (5/5min) |
| 2 | Medium | Auth/Authz | Cross-project volunteer enrollment via unscoped `Volunteer::find()` in `ManualEnrollment` | Fixed: scoped both lookups with `->where('project_id', $this->event->project_id)` |
| 3 | Medium | Token Security | No unique index on `email_verification_tokens.token_hash` | Fixed: migration `add_unique_index_to_email_verification_tokens` |
| 4 | Low | Auth/Authz | Private events accessible via public signup wizard | By design: private = not listed, but accessible via direct URL (M8 intent confirmed by existing test) |
| 5 | Low | Input Validation | Extra keys in `gearSelections` / `customFieldResponses` not stripped | Fixed: `->only($validIds)` applied before passing to `ProcessVolunteerSignup` |
| 6 | Low | Livewire Exposure | `reservationExpiresAt` not `#[Locked]` | Fixed: added `#[Locked]` attribute |
| 7 | Informational | Other | `app:release-expired-reservations` missing `withoutOverlapping()` | Fixed: added `->withoutOverlapping()` in `routes/console.php` |

## Resolution Status

**Fixed:** 1 High, 2 Medium, 2 Low, 1 Informational
**By design:** 1 Low (private events via direct URL — M8 intended behavior)
**No unresolved findings.**
| 8 | Informational | Token Security | `magic_link_tokens.token_hash` also lacks a database index (pre-M10, out of scope but noted) | `database/migrations/2026_03_01_141017_...php` |
