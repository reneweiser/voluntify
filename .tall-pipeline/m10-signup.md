# Milestone: m10-signup — Signup Flow Rewrite

**Features:** Multi-step signup wizard, shift reservations with TTL, manual volunteer enrollment, email verification within wizard
**Issues:** #69, #49, #88
**Dependencies:** m8-project-scoped (complete)

## Plan
- **Status:** complete
- **Gate summary:** 2 migrations, 1 new model, 1 enum, 3 new actions, 1 command, 3 VOs, wizard rewrite, manual enrollment update, Alpine timer. 14 plan-review concerns resolved (12 accepted, 1 rejected, 1 deferred).

## Implement
- **Status:** complete
- **Iteration:** 1
- **Gate summary:** 1042 tests green (984 + 58 new). migrate:fresh --seed clean. Pint passes. 47 files (27 modified + 20 new). All review resolutions (D11-D20) implemented. Wizard uses WizardState enum, ReserveShifts atomic inside transaction, Alpine timer with jitter.

## Test
- **Status:** complete
- 1042 tests pass (2267 assertions)

## Security Audit
- **Status:** complete
- **Gate summary:** 8 findings (1H, 2M, 3L, 2I). All fixed except 1L (private events via direct URL — by design). Report: `.tall-pipeline/m10-security-audit.md`.

---

## 1. Database Schema

### 1.1 Migration #16: create_shift_reservations_table

```php
Schema::create('shift_reservations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('shift_id')->constrained()->cascadeOnDelete();
    $table->string('session_id', 64)->index();
    $table->dateTime('expires_at');
    $table->timestamps();

    $table->index('expires_at'); // for cleanup query
});
```

**Columns:**

| Column | Type | Nullable | Default | Classification | Notes |
|---|---|---|---|---|---|
| id | bigint PK | no | auto | internal | |
| shift_id | FK → shifts | no | — | internal | Which shift is reserved |
| session_id | string(64) | no | — | internal | Ties reservation to a browser session (Laravel session ID) |
| expires_at | datetime | no | — | internal | When the reservation expires (created_at + 20 minutes) |
| created_at | timestamp | no | auto | internal | |
| updated_at | timestamp | no | auto | internal | |

**Indexes:** `shift_id` (FK), `session_id`, `expires_at`

**Data classification:** All `internal`. No PII — session_id is an opaque server-side identifier.

**Soft deletes:** No. Expired reservations are hard-deleted by the cleanup command. No audit trail needed for temporary hold records.

### 1.2 Migration #17: add_project_id_to_email_verification_tokens

```php
Schema::table('email_verification_tokens', function (Blueprint $table) {
    $table->foreignId('project_id')
        ->nullable()
        ->after('event_id')
        ->constrained()
        ->cascadeOnDelete();
});
```

**Why nullable:** Backward compatibility during migration. Existing tokens created before M10 will have `project_id = null`. New tokens always set `project_id` from `$event->project_id`. The `CompleteEmailVerification` action already loads `$token->event->project`, so null is safe — the project_id on the token is an optimization for direct queries, not a functional requirement.

### 1.3 Migration Order

Both migrations are independent and can run in any order. Convention: `create_` before `add_`.

```
16. create_shift_reservations_table
17. add_project_id_to_email_verification_tokens
```

---

## 2. Models

### 2.1 ShiftReservation (NEW)

**File:** `app/Models/ShiftReservation.php`

```php
class ShiftReservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'shift_id',
        'session_id',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('expires_at', '>', now());
    }

    public function scopeForSession(Builder $query, string $sessionId): void
    {
        $query->where('session_id', $sessionId);
    }

    public function scopeExpired(Builder $query): void
    {
        $query->where('expires_at', '<=', now());
    }
}
```

**Relationships to add on Shift model:**

```php
// In App\Models\Shift
public function reservations(): HasMany
{
    return $this->hasMany(ShiftReservation::class);
}

public function activeReservations(): HasMany
{
    return $this->hasMany(ShiftReservation::class)->active();
}
```

### 2.2 Shift::isFull() — Updated Logic

The existing `isFull()` method must account for active reservations in addition to active signups:

```php
// In App\Models\Shift
public function isFull(): bool
{
    $signupCount = $this->active_signups_count ?? $this->activeSignups()->count();
    $reservationCount = $this->active_reservations_count ?? $this->activeReservations()->count();

    return ($signupCount + $reservationCount) >= $this->capacity;
}

public function spotsRemaining(): int
{
    $signupCount = $this->active_signups_count ?? $this->activeSignups()->count();
    $reservationCount = $this->active_reservations_count ?? $this->activeReservations()->count();

    return max(0, $this->capacity - $signupCount - $reservationCount);
}
```

**Critical detail:** This means everywhere that calls `isFull()` or `spotsRemaining()` will automatically consider reservations. The `withCount('activeSignups')` calls in existing components will need to also include `withCount('activeReservations')` for accurate display, OR we add a computed `effectiveSignupCount` that combines both.

**Approach:** Add `withCount('activeReservations as active_reservations_count')` wherever we already do `withCount('activeSignups as ...')`. The `isFull()`/`spotsRemaining()` methods use the counts if loaded, otherwise query. This ensures backward compatibility — if reservations are not counted (e.g., in ManualEnrollment where reservations don't apply), the fallback query handles it.

### 2.3 EmailVerificationToken — Updated

Add `project_id` to `$fillable`:

```php
protected $fillable = [
    'volunteer_id',
    'event_id',
    'project_id', // NEW
    'shift_ids',
    'gear_selections',
    'custom_field_responses',
    'token_hash',
    'expires_at',
];
```

Add `project()` relationship:

```php
public function project(): BelongsTo
{
    return $this->belongsTo(Project::class);
}
```

### 2.4 Factory: ShiftReservationFactory (NEW)

```php
class ShiftReservationFactory extends Factory
{
    protected $model = ShiftReservation::class;

    public function definition(): array
    {
        return [
            'shift_id' => Shift::factory(),
            'session_id' => Str::random(40),
            'expires_at' => now()->addMinutes(20),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'expires_at' => now()->subMinute(),
        ]);
    }
}
```

### 2.5 Factory: EmailVerificationTokenFactory — Updated

Add `project_id` to definition (optional, defaults via event relationship):

```php
public function definition(): array
{
    return [
        'volunteer_id' => Volunteer::factory(),
        'event_id' => Event::factory(),
        'project_id' => null, // Set from event in production code
        'shift_ids' => [1],
        'token_hash' => HashedToken::fromPlaintext(fake()->sha256())->hash,
        'expires_at' => now()->addHours(24),
    ];
}
```

---

## 3. Actions

### 3.1 ReserveShifts (NEW)

**File:** `app/Actions/ReserveShifts.php`
**Signature:** `execute(array $shiftIds, string $sessionId, Event $event): ReservationResult`

```php
class ReserveShifts
{
    public const TTL_MINUTES = 20;

    /**
     * Reserve shifts for a signup session. Atomic — uses DB locks to prevent double-booking.
     *
     * @param array<int> $shiftIds
     * @return ReservationResult
     */
    public function execute(array $shiftIds, string $sessionId, Event $event): ReservationResult
    {
        // 1. Validate all shiftIds belong to this event
        $eventJobIds = $event->volunteerJobs()->pluck('id');
        $validShiftIds = Shift::whereIn('volunteer_job_id', $eventJobIds)
            ->whereIn('id', $shiftIds)
            ->pluck('id')
            ->all();

        if (count($validShiftIds) !== count($shiftIds)) {
            throw new DomainException('One or more shifts do not belong to this event.');
        }

        // 2. Release any existing reservations for this session (user changed selection)
        ShiftReservation::forSession($sessionId)->delete();

        // 3. Lock and reserve
        return DB::transaction(function () use ($validShiftIds, $sessionId) {
            $reserved = [];
            $unavailable = [];
            $expiresAt = now()->addMinutes(self::TTL_MINUTES);

            foreach ($validShiftIds as $shiftId) {
                $shift = Shift::lockForUpdate()->findOrFail($shiftId);

                if ($shift->isFull()) {
                    $unavailable[] = $shift;
                    continue;
                }

                $reserved[] = ShiftReservation::create([
                    'shift_id' => $shiftId,
                    'session_id' => $sessionId,
                    'expires_at' => $expiresAt,
                ]);
            }

            return new ReservationResult(
                reserved: $reserved,
                unavailable: $unavailable,
                expiresAt: count($reserved) > 0 ? $expiresAt : null,
            );
        });
    }
}
```

**Key design decisions:**

1. **Session-scoped, not volunteer-scoped.** At reservation time, we don't know who the volunteer is yet (step 1 = shift selection, step 3 = personal info). The Laravel session ID ties reservations to a browser tab.

2. **Replacing on re-selection.** If a user goes back to step 1 and changes their shift selection, the old reservations are released before new ones are created. This prevents stale reservations from blocking capacity.

3. **Partial success allowed.** If 3 shifts are selected but 1 is full, 2 get reserved. The result communicates which shifts were unavailable so the UI can inform the user.

4. **Lock ordering.** Shifts are locked in the order of `$validShiftIds`. Since we sort in `SignUpVolunteerForShifts`, we should sort here too to prevent deadlocks. Add `sort($validShiftIds)` before the loop.

### 3.2 ReservationResult (NEW Value Object)

**File:** `app/ValueObjects/ReservationResult.php`

```php
readonly class ReservationResult
{
    /**
     * @param array<ShiftReservation> $reserved
     * @param array<Shift> $unavailable
     */
    public function __construct(
        public array $reserved = [],
        public array $unavailable = [],
        public ?CarbonInterface $expiresAt = null,
    ) {}

    public function hasReservations(): bool
    {
        return count($this->reserved) > 0;
    }

    /** @return array<int> */
    public function reservedShiftIds(): array
    {
        return array_map(fn (ShiftReservation $r) => $r->shift_id, $this->reserved);
    }
}
```

### 3.3 ReleaseExpiredReservations (NEW)

**File:** `app/Actions/ReleaseExpiredReservations.php`

```php
class ReleaseExpiredReservations
{
    public function execute(): int
    {
        return ShiftReservation::expired()->delete();
    }
}
```

Simple and idempotent. Returns the count of deleted rows for logging.

### 3.4 CreateVolunteerManually (NEW)

**File:** `app/Actions/CreateVolunteerManually.php`
**Signature:** `execute(Project $project, array $data): Volunteer`

```php
class CreateVolunteerManually
{
    /**
     * Create a new volunteer manually (admin panel).
     * The volunteer is auto-verified (no email verification needed).
     *
     * @param array{first_name: string, last_name: string, email: string, phone?: string|null} $data
     */
    public function execute(Project $project, array $data): Volunteer
    {
        $volunteer = Volunteer::firstOrCreate(
            ['email' => $data['email'], 'project_id' => $project->id],
            [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'phone' => $data['phone'] ?? null,
                'email_verified_at' => now(), // Auto-verified for manual creation
            ],
        );

        // Update name/phone if volunteer already existed
        if (!$volunteer->wasRecentlyCreated) {
            $volunteer->update([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'phone' => $data['phone'] ?? $volunteer->phone,
            ]);

            // Auto-verify if not yet verified
            if (!$volunteer->isEmailVerified()) {
                $volunteer->markEmailAsVerified();
            }
        }

        return $volunteer;
    }
}
```

**Key decision:** Manual creation auto-verifies the volunteer. The organizer is the trust anchor — if they're creating a volunteer record, no email verification is needed.

### 3.5 ProcessVolunteerSignup — Updated

Changes needed:
1. Accept optional `sessionId` parameter for reservation release after successful signup.
2. After signup completes (for verified volunteer), release reservations for that session.

```php
public function execute(
    string $firstName,
    string $lastName,
    string $email,
    Event $event,
    array $shiftIds,
    ?string $phone = null,
    ?array $gearSelections = null,
    ?array $customFieldResponses = null,
    ?string $sessionId = null, // NEW
): SignupOutcome {
    // ... existing firstOrCreate logic ...

    if ($volunteer->isEmailVerified()) {
        $result = $this->signUpAction->execute(
            volunteer: $volunteer,
            event: $event,
            shiftIds: $shiftIds,
        );

        // ... existing gear, custom fields, event dispatch ...

        // Release reservations after successful signup
        if ($sessionId !== null) {
            ShiftReservation::forSession($sessionId)->delete();
        }

        return SignupOutcome::completed($result);
    }

    $this->sendVerification->execute(
        $volunteer, $event, $shiftIds, $gearSelections, $customFieldResponses
    );

    // Note: reservations are NOT released here — they hold the spot
    // until the verification completes or the TTL expires.

    return SignupOutcome::pendingVerification($email);
}
```

### 3.6 CompleteEmailVerification — Updated

After successful verification and signup, release any session reservations. However, we don't have the session_id at verification time (the verification link is clicked from email, possibly a different browser). So:

**Approach:** The `SignUpVolunteerForShifts` action already locks the shift and checks capacity. When the verified volunteer's shifts are actually created, the reservations are no longer needed. The TTL handles cleanup — reservations expire in 20 minutes anyway. No change needed to `CompleteEmailVerification`.

**However:** There's a subtle race condition. If the reservation has expired but the shifts filled up in the meantime, the verification will report `skippedFull`. This is the existing behavior and is acceptable — the pending verification message already warns "Your shift selections are not reserved until you verify."

**Wait — with M10, we ARE reserving.** So the message changes. Let's reconsider:

When an unverified volunteer signs up:
1. Reservations are held (20 min TTL).
2. Verification email sent (24h expiry).
3. If verification happens within 20 min → reservations still active → signup succeeds.
4. If verification happens after 20 min → reservations expired → shifts may be full.

**Decision:** The 20-minute TTL is intentionally short. After it expires, the spot is released. The verification email message should say "Your shifts are held for 20 minutes. After that, spots may fill." This is the correct UX — the TTL protects against abandoned signups.

**Change to CompleteEmailVerification:** No structural change needed. The `SignUpVolunteerForShifts` action handles the capacity check with locks. The only addition: if we stored the `session_id` on the verification token, we could clean up reservations on verification. But since TTL handles this and storing session_id adds complexity, we skip it.

### 3.7 SignUpVolunteerForShifts — Updated

The `isFull()` check in the transaction already accounts for reservations (via the updated `Shift::isFull()`). When creating a signup, we should release the reservation for that specific shift if one exists for the session.

**Change:** After creating a signup within the transaction, delete any reservation for that shift + session:

```php
// Inside the foreach loop, after creating the signup:
if ($sessionId !== null) {
    ShiftReservation::where('shift_id', $shift->id)
        ->where('session_id', $sessionId)
        ->delete();
}
```

**Problem:** `SignUpVolunteerForShifts` currently doesn't know the session_id. We need to add an optional parameter:

```php
public function execute(
    Volunteer $volunteer,
    Event $event,
    array $shiftIds,
    bool $sendNotification = true,
    ?string $sessionId = null, // NEW
): SignupBatchResult
```

**But wait:** The `isFull()` check would count this session's own reservations as "taken" capacity. When the user reserved shift A and then tries to sign up for shift A, the shift appears full because the reservation counts against capacity.

**Solution:** When checking `isFull()` during signup from the wizard, we need to EXCLUDE the current session's reservations from the count. Two approaches:

**Approach A (Simple):** Before the lock-and-check loop, delete all reservations for this session. Then `isFull()` won't count them. The spot is "released and immediately claimed" atomically within the transaction.

**Approach B (Precise):** Pass session_id into the capacity check and exclude it.

**Go with Approach A.** It's simpler and works within the existing transaction:

```php
// In SignUpVolunteerForShifts::execute(), inside the DB::transaction:
// Release this session's reservations BEFORE checking capacity
if ($sessionId !== null) {
    ShiftReservation::forSession($sessionId)->delete();
}

// Then proceed with lock-and-check loop as before
```

This is safe because:
- We're inside a transaction with row-level locks.
- Releasing the reservation frees the capacity.
- The signup immediately claims it.
- No other transaction can grab the spot between release and claim because the shift row is locked.

### 3.8 SendEmailVerification — Updated

Add `project_id` to the token creation:

```php
public function execute(Volunteer $volunteer, Event $event, array $shiftIds, ?array $gearSelections = null, ?array $customFieldResponses = null): void
{
    // ... existing token generation ...

    EmailVerificationToken::create([
        'volunteer_id' => $volunteer->id,
        'event_id' => $event->id,
        'project_id' => $event->project_id, // NEW
        'shift_ids' => $shiftIds,
        'gear_selections' => $gearSelections,
        'custom_field_responses' => $customFieldResponses,
        'token_hash' => $hashed->hash,
        'expires_at' => now()->addHours(24),
    ]);

    // ... rest unchanged ...
}
```

---

## 4. Wizard Component Design

### 4.1 Architecture: One Livewire Component, Four Steps

Following the architecture-patterns.md Multi-Step Form pattern: **one Livewire component** (`SignupWizard`) with Alpine.js `x-data` managing step transitions. Steps are `x-show` regions, not separate components.

```
EventSignup (full-page, #[Layout('layouts.public')])
  └── SignupWizard logic + Alpine x-data="signupWizard(...)"
        ├── Step 1: Select Shifts (x-show="step === 1")
        │     ├── ShiftPicker (Blade partial — checkboxes, capacity display)
        │     └── [Next] button → wire:click="reserveAndAdvance"
        ├── Step 2: Gear & Custom Fields (x-show="step === 2")
        │     ├── GearSection (Blade partial — size selectors)
        │     ├── CustomFieldsSection (Blade partial — dynamic fields)
        │     └── [Next] / [Back] buttons
        ├── Step 3: Personal Info (x-show="step === 3")
        │     ├── Name, email, phone fields
        │     └── [Next] / [Back] buttons
        ├── Step 4: Confirm & Submit (x-show="step === 4")
        │     ├── Summary of selections
        │     ├── [Submit] button → wire:click="signup"
        │     └── [Back] button
        ├── ReservationTimer (Alpine — countdown, visible on steps 2-4)
        ├── VerificationNotice (x-show — shown after submit for unverified)
        └── SuccessNotice (x-show — shown after successful signup)
```

### 4.2 Component: EventSignup (Rewritten)

**File:** `app/Livewire/Public/EventSignup.php`

The rewritten component replaces the existing single-form `EventSignup`. The route stays the same: `GET /events/{publicToken}`.

**Livewire Properties:**

```php
// Step management (server-side for validation gating)
public int $currentStep = 1;

// Step 1: Shift selection
/** @var array<int> */
public array $selectedShiftIds = [];

// Step 2: Gear & custom fields
/** @var array<int, string|null> */
public array $gearSelections = [];
/** @var array<int, mixed> */
public array $customFieldResponses = [];

// Step 3: Personal info
public string $volunteerFirstName = '';
public string $volunteerLastName = '';
public string $volunteerEmail = '';
public string $volunteerPhone = '';

// Reservation state
public ?string $reservationExpiresAt = null; // ISO 8601 string for Alpine

// Outcome state
public bool $signupComplete = false;
public bool $pendingVerification = false;
public string $warningMessage = '';
```

**Key Properties:**
- `$currentStep` is server-managed (not `#[Locked]`, because we set it server-side in action methods). Alpine reads it via `$wire.currentStep` for `x-show` but the server is the source of truth.
- `$reservationExpiresAt` is a string (ISO 8601) so Alpine can parse it for the countdown.
- `$selectedShiftIds` uses deferred `wire:model` (step 1 checkboxes).

**Livewire Methods:**

```php
public function mount(string $publicToken): void
{
    $this->event = Event::where('public_token', $publicToken)
        ->where('status', EventStatus::PublishedOpen)
        ->firstOrFail();
}

// Step 1 → Step 2: Reserve shifts and advance
public function reserveAndAdvance(): void
{
    $this->validate([
        'selectedShiftIds' => ['required', 'array', 'min:1'],
        'selectedShiftIds.*' => ['integer', Rule::exists('shifts', 'id')->where(...)],
    ]);

    $result = app(ReserveShifts::class)->execute(
        shiftIds: array_map('intval', $this->selectedShiftIds),
        sessionId: session()->getId(),
        event: $this->event,
    );

    if (!$result->hasReservations()) {
        $this->addError('selectedShiftIds', __('All selected shifts are full.'));
        unset($this->jobs); // refresh computed
        return;
    }

    // Update selection to only reserved shifts
    $this->selectedShiftIds = $result->reservedShiftIds();
    $this->reservationExpiresAt = $result->expiresAt->toISOString();

    if (count($result->unavailable) > 0) {
        $this->warningMessage = __(':count shift(s) were full and removed from your selection.', [
            'count' => count($result->unavailable),
        ]);
    }

    // Skip step 2 if no gear items and no custom fields
    if ($this->gearItems->isEmpty() && $this->customRegistrationFields->isEmpty()) {
        $this->currentStep = 3;
    } else {
        $this->currentStep = 2;
    }
}

// Step 2 → Step 3: Validate gear/fields and advance
public function advanceToPersonalInfo(): void
{
    $this->validateGearAndCustomFields();
    $this->currentStep = 3;
}

// Step 3 → Step 4: Validate personal info and show summary
public function advanceToConfirmation(): void
{
    $this->validatePersonalInfo();
    $this->currentStep = 4;
}

// Step 4: Final submit
public function signup(): void
{
    // Re-validate everything
    $this->validatePersonalInfo();
    $this->validateGearAndCustomFields();

    // Check reservation hasn't expired
    if ($this->reservationExpiresAt && Carbon::parse($this->reservationExpiresAt)->isPast()) {
        $this->addError('selectedShiftIds', __('Your reservation has expired. Please start over.'));
        $this->resetWizard();
        return;
    }

    $action = app(ProcessVolunteerSignup::class);

    try {
        $gearSelections = $this->gearItems->isNotEmpty()
            ? collect($this->gearSelections)->filter()->all()
            : null;

        $customFieldResponses = $this->customRegistrationFields->isNotEmpty()
            ? $this->customFieldResponses
            : null;

        $outcome = $action->execute(
            firstName: $this->volunteerFirstName,
            lastName: $this->volunteerLastName,
            email: $this->volunteerEmail,
            event: $this->event,
            shiftIds: array_map('intval', $this->selectedShiftIds),
            phone: $this->volunteerPhone ?: null,
            gearSelections: $gearSelections,
            customFieldResponses: $customFieldResponses,
            sessionId: session()->getId(),
        );

        if ($outcome->isPendingVerification()) {
            $this->pendingVerification = true;
            return;
        }

        $result = $outcome->batchResult;

        if ($result->hasNewSignups()) {
            $this->signupComplete = true;
            $skippedCount = count($result->skippedFull) + count($result->skippedDuplicate);
            if ($skippedCount > 0) {
                $this->warningMessage = __('Some shifts were skipped because they were full or you were already signed up.');
            }
        } else {
            // All shifts skipped — show appropriate error
            if (count($result->skippedDuplicate) === count($this->selectedShiftIds)) {
                $this->addError('selectedShiftIds', __('You are already signed up for all selected shifts.'));
            } else {
                $this->addError('selectedShiftIds', __('Selected shifts are either full or already registered.'));
            }
            $this->resetWizard();
        }
    } catch (DomainException $e) {
        $this->addError('selectedShiftIds', $e->getMessage());
    }
}

// Navigation helpers
public function goToStep(int $step): void
{
    if ($step < 1 || $step >= $this->currentStep) {
        return; // Can only go backward
    }
    $this->currentStep = $step;
}

public function handleReservationExpired(): void
{
    $this->addError('selectedShiftIds', __('Your reservation has expired. Please select your shifts again.'));
    $this->resetWizard();
}

private function resetWizard(): void
{
    $this->currentStep = 1;
    $this->selectedShiftIds = [];
    $this->reservationExpiresAt = null;
    $this->warningMessage = '';
    unset($this->jobs); // refresh computed
}
```

**Computed Properties:** Same as current — `jobs()`, `gearItems()`, `customRegistrationFields()`. The `jobs()` query adds `withCount('activeReservations')`.

### 4.3 Step Layout — What Each Step Contains

**Step 1: Select Shifts**
- Event header (title image, name, description, date, location) — always visible above wizard.
- Job list with shift checkboxes. Capacity display includes reservations.
- "Continue" button → `wire:click="reserveAndAdvance"`.
- Warning callout if shown.

**Step 2: Gear & Custom Fields** (skipped if none exist)
- Gear selection (size dropdowns for size-required items).
- Custom registration fields (text, select, checkbox).
- ReservationTimer visible.
- "Back" and "Continue" buttons.

**Step 3: Personal Info**
- First name, last name, email, phone fields.
- ReservationTimer visible.
- "Back" and "Continue" buttons.

**Step 4: Confirm**
- Summary: selected shifts (with times), gear selections, custom field responses, personal info.
- ReservationTimer visible.
- "Back" and "Sign Up" buttons.

**After submit:**
- Pending verification notice (with email sent message).
- OR success notice.

### 4.4 Step Progress Indicator

A visual step indicator (Blade partial) shows 4 steps with active/complete/upcoming states. Each completed step is clickable to go back.

```html
<div class="flex items-center justify-center gap-2 mb-8">
    @foreach ([1 => 'Shifts', 2 => 'Details', 3 => 'Info', 4 => 'Confirm'] as $num => $label)
        @if ($showStep2 || $num !== 2)
            <button wire:click="goToStep({{ $num }})"
                class="..."
                @disabled($num > $currentStep)>
                {{ $num }}. {{ __($label) }}
            </button>
        @endif
    @endforeach
</div>
```

Where `$showStep2` is true when gear items or custom fields exist.

---

## 5. Alpine.js ReservationTimer

### 5.1 Design

An Alpine component that displays a countdown from the reservation expiry time. Dispatches a Livewire method when the timer reaches zero.

```html
<div x-data="reservationTimer($wire)" x-show="$wire.reservationExpiresAt && $wire.currentStep > 1"
     x-cloak class="...">
    <template x-if="remaining > 0">
        <div class="flex items-center gap-2 text-sm">
            <flux:icon name="clock" variant="mini" class="size-4" />
            <span>
                {{ __('Reservation expires in') }}
                <span x-text="formattedTime" class="font-mono font-medium"></span>
            </span>
        </div>
    </template>
</div>
```

### 5.2 Alpine Component

```js
// resources/js/reservation-timer.js
document.addEventListener('alpine:init', () => {
    Alpine.data('reservationTimer', ($wire) => ({
        remaining: 0,
        interval: null,

        init() {
            this.$watch(() => $wire.reservationExpiresAt, (val) => {
                if (val) {
                    this.startCountdown(val);
                } else {
                    this.stopCountdown();
                }
            });

            if ($wire.reservationExpiresAt) {
                this.startCountdown($wire.reservationExpiresAt);
            }
        },

        startCountdown(expiresAt) {
            this.stopCountdown();
            const expiry = new Date(expiresAt).getTime();

            this.updateRemaining(expiry);
            this.interval = setInterval(() => {
                this.updateRemaining(expiry);
                if (this.remaining <= 0) {
                    this.stopCountdown();
                    $wire.handleReservationExpired();
                }
            }, 1000);
        },

        updateRemaining(expiry) {
            this.remaining = Math.max(0, Math.floor((expiry - Date.now()) / 1000));
        },

        stopCountdown() {
            if (this.interval) {
                clearInterval(this.interval);
                this.interval = null;
            }
        },

        get formattedTime() {
            const m = Math.floor(this.remaining / 60);
            const s = this.remaining % 60;
            return `${m}:${s.toString().padStart(2, '0')}`;
        },

        destroy() {
            this.stopCountdown();
        },
    }));
});
```

**Key behavior:**
- Timer starts when `$wire.reservationExpiresAt` is set (after step 1).
- Visible on steps 2-4.
- When it reaches zero, calls `$wire.handleReservationExpired()` which resets the wizard to step 1.
- Uses `$watch` to react to Livewire property changes (e.g., if user re-reserves).

### 5.3 Visual Treatment

- **> 5 minutes:** Green background, normal text.
- **1-5 minutes:** Amber/yellow background, slightly urgent.
- **< 1 minute:** Red background, pulsing animation.

```html
<div :class="{
    'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300': remaining > 300,
    'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300': remaining <= 300 && remaining > 60,
    'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300 animate-pulse': remaining <= 60,
}" class="rounded-lg px-4 py-2 transition-colors">
```

---

## 6. Email Verification Integration

### 6.1 Within-Wizard Flow

When a first-time volunteer submits the wizard (step 4) and their email is unverified:

1. `signup()` calls `ProcessVolunteerSignup`.
2. `ProcessVolunteerSignup` detects unverified volunteer, calls `SendEmailVerification`.
3. `SendEmailVerification` stores shift_ids, gear_selections, custom_field_responses on the token.
4. `ProcessVolunteerSignup` returns `SignupOutcome::pendingVerification()`.
5. The wizard shows the verification notice (same as current, but inline in the wizard).

The verification notice replaces the wizard steps with:
- Envelope icon.
- "Check your email" heading.
- The email address.
- "Your shifts are held for 20 minutes" message.
- A note that after 20 minutes, spots may be released.

### 6.2 Email Verification Page — Kept As-Is

The `EmailVerificationPage` component at `/verify-email/{token}` remains unchanged. When the volunteer clicks the verification link, `CompleteEmailVerification` runs, creates signups, and shows the success page.

**Rationale for not integrating verification INTO the wizard:** The verification link in the email opens a new browser tab/window. Making it redirect back into the wizard adds complexity (session sharing, state recovery) with no UX benefit. The verification page already shows the outcome clearly.

### 6.3 Change to Pending Verification Message

Current message: "Your shift selections are not reserved until you verify."
New message: "Your shifts are held for 20 minutes. Verify your email promptly to secure your spots."

This accurately reflects the reservation TTL behavior.

---

## 7. Scheduled Command: ReleaseExpiredReservationsCommand

**File:** `app/Console/Commands/ReleaseExpiredReservationsCommand.php`

```php
class ReleaseExpiredReservationsCommand extends Command
{
    protected $signature = 'app:release-expired-reservations';

    protected $description = 'Release expired shift reservations to free capacity';

    public function handle(ReleaseExpiredReservations $action): void
    {
        $count = $action->execute();

        if ($count > 0) {
            $this->info("Released {$count} expired reservation(s).");
        }
    }
}
```

**Schedule registration in `routes/console.php`:**

```php
Schedule::command('app:release-expired-reservations')->everyMinute();
```

**Why every minute:** Reservations have a 20-minute TTL. Running every minute ensures expired reservations are cleaned up within 1 minute of expiry, keeping capacity counts accurate. The query is lightweight (single DELETE with a datetime comparison).

---

## 8. Manual Enrollment — Changes

### 8.1 ManualEnrollment Component — Add "Create New Volunteer" Mode

Currently, ManualEnrollment only searches existing volunteers. With #88, organizers need to create new volunteers directly.

**New mode:** A toggle between "Search Existing" and "Create New" volunteer.

**New properties:**

```php
public bool $createNewMode = false;
public string $newFirstName = '';
public string $newLastName = '';
public string $newEmail = '';
public string $newPhone = '';
```

**New method:**

```php
public function createAndSelect(CreateVolunteerManually $action): void
{
    Gate::authorize('manageJobs', $this->event);

    $this->validate([
        'newFirstName' => ['required', 'string', 'max:255'],
        'newLastName' => ['required', 'string', 'max:255'],
        'newEmail' => ['required', 'email', 'max:255'],
        'newPhone' => ['nullable', 'string', 'max:20'],
    ]);

    $volunteer = $action->execute(
        project: $this->event->project,
        data: [
            'first_name' => $this->newFirstName,
            'last_name' => $this->newLastName,
            'email' => $this->newEmail,
            'phone' => $this->newPhone ?: null,
        ],
    );

    $this->selectVolunteer($volunteer->id);
    $this->createNewMode = false;
    $this->resetNewVolunteerForm();
}

private function resetNewVolunteerForm(): void
{
    $this->newFirstName = '';
    $this->newLastName = '';
    $this->newEmail = '';
    $this->newPhone = '';
}

public function toggleCreateMode(): void
{
    $this->createNewMode = !$this->createNewMode;
    if ($this->createNewMode) {
        $this->selectedVolunteerId = null;
        $this->search = '';
    }
}
```

### 8.2 Manual Enrollment — No Reservations

Manual enrollment (admin panel) does NOT use reservations. Organizers operate in a trusted context with immediate enrollment. The `SignUpVolunteerForShifts` action is called directly (as now), without `sessionId`.

### 8.3 Template Changes

Add a "Create New" tab/toggle button in the "Select Volunteer" card. When active, shows a form with first name, last name, email, phone fields and a "Create & Select" button.

---

## 9. Component Communication Patterns

| From | To | Mechanism | Name/Detail |
|---|---|---|---|
| Alpine ReservationTimer | Livewire EventSignup | `$wire.handleReservationExpired()` | Timer expired, reset wizard |
| Step navigation buttons | Livewire EventSignup | `wire:click="goToStep(N)"` | Backward navigation |
| Step advance buttons | Livewire EventSignup | `wire:click="reserveAndAdvance"` etc. | Forward with validation |
| Livewire EventSignup | Alpine ReservationTimer | `$wire.reservationExpiresAt` property | Timer start signal |

No cross-component Livewire events needed. Everything is within a single full-page component.

---

## 10. Changes to Existing Files Summary

### Models
| File | Change |
|---|---|
| `app/Models/Shift.php` | Add `reservations()`, `activeReservations()` relationships. Update `isFull()` and `spotsRemaining()` to count active reservations. |
| `app/Models/EmailVerificationToken.php` | Add `project_id` to `$fillable`. Add `project()` relationship. |

### Actions
| File | Change |
|---|---|
| `app/Actions/ProcessVolunteerSignup.php` | Add optional `$sessionId` param. Release reservations after successful signup. |
| `app/Actions/SignUpVolunteerForShifts.php` | Add optional `$sessionId` param. Release session reservations before lock loop (Approach A). |
| `app/Actions/SendEmailVerification.php` | Add `project_id` to token creation. |

### Components
| File | Change |
|---|---|
| `app/Livewire/Public/EventSignup.php` | Full rewrite as multi-step wizard. |
| `resources/views/livewire/public/event-signup.blade.php` | Full rewrite with 4-step layout, timer, progress indicator. |
| `app/Livewire/Events/ManualEnrollment.php` | Add create-new-volunteer mode. |
| `resources/views/livewire/events/manual-enrollment.blade.php` | Add create-new UI toggle and form. |

### New Files
| File | Purpose |
|---|---|
| `app/Models/ShiftReservation.php` | Reservation model |
| `app/Actions/ReserveShifts.php` | Reserve shifts with TTL |
| `app/Actions/ReleaseExpiredReservations.php` | Cleanup action |
| `app/Actions/CreateVolunteerManually.php` | Manual volunteer creation |
| `app/ValueObjects/ReservationResult.php` | Reservation outcome VO |
| `app/Console/Commands/ReleaseExpiredReservationsCommand.php` | Scheduled cleanup |
| `database/factories/ShiftReservationFactory.php` | Test factory |
| `database/migrations/..._create_shift_reservations_table.php` | Schema |
| `database/migrations/..._add_project_id_to_email_verification_tokens.php` | Schema |
| `resources/js/reservation-timer.js` | Alpine countdown component |

### Routes
No route changes. The public signup route stays: `GET /events/{publicToken}` → `EventSignup::class`.

### Schedule
Add to `routes/console.php`: `Schedule::command('app:release-expired-reservations')->everyMinute();`

---

## 11. Test Strategy

### 11.1 Unit Tests — Actions

**ReserveShiftsTest** (`tests/Feature/Actions/ReserveShiftsTest.php`):
- [x] Reserves shifts and returns ReservationResult with correct shift IDs and expiry
- [x] Marks unavailable shifts when shift is full (signups at capacity)
- [x] Marks unavailable shifts when shift is full due to existing reservations
- [x] Throws DomainException for shifts not belonging to event
- [x] Replaces existing reservations for same session (re-selection)
- [x] Creates reservations atomically — concurrent requests don't double-book
- [x] Sets expiry 20 minutes from now
- [x] Handles mixed available/unavailable shifts (partial reservation)

**ReleaseExpiredReservationsTest** (`tests/Feature/Actions/ReleaseExpiredReservationsTest.php`):
- [x] Deletes expired reservations and returns count
- [x] Does not delete active (non-expired) reservations
- [x] Returns 0 when no expired reservations exist
- [x] Handles mix of expired and active reservations

**CreateVolunteerManuallyTest** (`tests/Feature/Actions/CreateVolunteerManuallyTest.php`):
- [x] Creates new volunteer with auto-verified email
- [x] Returns existing volunteer if email+project match already exists
- [x] Updates name and phone on existing volunteer
- [x] Auto-verifies existing unverified volunteer

### 11.2 Unit Tests — Models

**ShiftReservationTest** (`tests/Feature/Models/ShiftReservationTest.php`):
- [x] isExpired() returns true for past expiry
- [x] isExpired() returns false for future expiry
- [x] scopeActive filters correctly
- [x] scopeExpired filters correctly
- [x] scopeForSession filters correctly

**ShiftTest updates** (`tests/Feature/Models/ShiftTest.php` — existing):
- [x] isFull() counts active reservations in addition to active signups
- [x] spotsRemaining() subtracts active reservations
- [x] isFull() does not count expired reservations

### 11.3 Feature Tests — Wizard Component

**EventSignupTest updates** (`tests/Feature/Public/EventSignupTest.php` and `tests/Feature/Livewire/EventSignupTest.php`):

Many existing tests will need updates because the flow changes from single-form to multi-step. Key new/updated tests:

- [x] Step 1: renders shift selection, shows capacity info
- [x] Step 1: validates at least one shift selected before advancing
- [x] Step 1 → Step 2: creates reservations and advances to gear/fields step
- [x] Step 1 → Step 3: skips step 2 when no gear or custom fields
- [x] Step 1: shows error when all selected shifts are full
- [x] Step 1: shows warning and advances with partial availability
- [x] Step 2: renders gear selectors and custom fields
- [x] Step 2: validates required gear sizes
- [x] Step 3: renders personal info fields
- [x] Step 3: validates required fields
- [x] Step 4: shows summary of all selections
- [x] Step 4 → success: completes signup for verified volunteer
- [x] Step 4 → verification: shows pending verification for unverified volunteer
- [x] Navigation: can go back to previous steps
- [x] Navigation: cannot skip forward
- [x] Reservation expiry: handleReservationExpired resets to step 1
- [x] Shows reservation timer state (expiresAt property set)

**ManualEnrollmentTest updates** (`tests/Feature/Livewire/ManualEnrollmentTest.php`):
- [x] Create new volunteer mode: renders form
- [x] Creates volunteer and selects them
- [x] Validates required fields for new volunteer
- [x] Auto-verifies created volunteer
- [x] Handles duplicate email (uses existing volunteer)

### 11.4 Feature Tests — Flow

**VolunteerSignupFlowTest updates** (`tests/Feature/Flows/VolunteerSignupFlowTest.php`):
- [x] Full wizard flow: select shifts → reserve → gear → info → confirm → verify → ticket
- [x] Verified volunteer wizard flow: select → reserve → info → confirm → done
- [x] Reservation expiry flow: reserve → expire → reset to step 1

### 11.5 Feature Tests — Scheduled Command

**ReleaseExpiredReservationsCommandTest** (`tests/Feature/Commands/ReleaseExpiredReservationsCommandTest.php`):
- [x] Command runs and releases expired reservations
- [x] Outputs count of released reservations
- [x] Outputs nothing when no expired reservations

### 11.6 Updated Tests — Actions

**ProcessVolunteerSignupTest** — add test for sessionId param:
- [x] Releases reservations after successful signup when sessionId provided

**SignUpVolunteerForShiftsTest** — add test for sessionId param:
- [x] Releases session reservations before capacity check

### 11.7 Browser Tests (Playwright)

Not in M10 scope per the plan. The Livewire feature tests cover the wizard flow adequately. Playwright E2E can be added in M13 polish.

---

## 12. Implementation Order

### Phase 1: Schema (no dependencies)
1. Create migration: `create_shift_reservations_table`
2. Create migration: `add_project_id_to_email_verification_tokens`
3. Create model: `ShiftReservation` with factory
4. Update model: `EmailVerificationToken` — add `project_id` to fillable, add relationship
5. Update factory: `EmailVerificationTokenFactory` — add `project_id`
6. Run `migrate:fresh --seed` to verify clean
7. **Gate:** Migrations pass, models instantiate, factory creates records

### Phase 2: Actions (depends on Phase 1)
1. Create value object: `ReservationResult`
2. Create action: `ReserveShifts`
3. Create action: `ReleaseExpiredReservations`
4. Create action: `CreateVolunteerManually`
5. Update model: `Shift` — add `reservations()`, `activeReservations()`, update `isFull()`, `spotsRemaining()`
6. Update action: `SignUpVolunteerForShifts` — add optional `$sessionId`, release reservations in transaction
7. Update action: `ProcessVolunteerSignup` — add optional `$sessionId`, pass through to SignUpVolunteerForShifts
8. Update action: `SendEmailVerification` — add `project_id` to token
9. Write unit tests for all new actions
10. Write unit tests for updated Shift model capacity logic
11. **Gate:** All action tests green. Existing action tests still pass (no breaking signature changes — new params are optional)

### Phase 3: Wizard Component (depends on Phase 2)
1. Create `resources/js/reservation-timer.js` Alpine component
2. Register it in Vite entry point
3. Rewrite `app/Livewire/Public/EventSignup.php` as multi-step wizard
4. Rewrite `resources/views/livewire/public/event-signup.blade.php` with 4-step layout
5. Update existing EventSignup tests to match new multi-step flow
6. Write new wizard-specific tests
7. Run Pint
8. **Gate:** All EventSignup tests green. Manual browser verification of wizard flow

### Phase 4: Manual Enrollment (depends on Phase 2)
1. Update `app/Livewire/Events/ManualEnrollment.php` — add create-new mode
2. Update `resources/views/livewire/events/manual-enrollment.blade.php` — add toggle and form
3. Update `tests/Feature/Livewire/ManualEnrollmentTest.php`
4. Write new tests for create-new volunteer flow
5. Run Pint
6. **Gate:** ManualEnrollment tests green

### Phase 5: Scheduled Command (depends on Phase 2)
1. Create `app/Console/Commands/ReleaseExpiredReservationsCommand.php`
2. Register in `routes/console.php`
3. Write command test
4. **Gate:** Command test green

### Phase 6: Integration & Polish (depends on Phases 3-5)
1. Update flow tests in `VolunteerSignupFlowTest.php`
2. Run full test suite: `vendor/bin/sail artisan test --compact`
3. Run Pint: `vendor/bin/sail bin pint --dirty --format agent`
4. Manual smoke test of wizard flow in browser
5. **Gate:** Full suite green. Pint clean. Browser smoke test passes.

---

## Decisions

| # | Stage | Decision | Rationale | Affects |
|---|---|---|---|---|
| D1 | plan | Session-scoped reservations (not volunteer-scoped) | Volunteer identity unknown at step 1 (shift selection). Session ID available immediately. | ReserveShifts, ShiftReservation |
| D2 | plan | Approach A: delete session reservations before signup lock loop | Simpler than excluding session from capacity count. Atomic within transaction. | SignUpVolunteerForShifts |
| D3 | plan | Keep EmailVerificationPage as separate page | Verification link opens new tab. No benefit to redirect into wizard. Adds complexity. | CompleteEmailVerification, EmailVerificationPage |
| D4 | plan | 20-minute TTL, every-minute cleanup | Short enough to prevent abandoned signup gridlock. Minute-level cleanup keeps capacity accurate. | ReserveShifts, ReleaseExpiredReservations, schedule |
| D5 | plan | Manual enrollment skips reservations | Organizers are trusted. Immediate enrollment via existing lock-based SignUpVolunteerForShifts. | ManualEnrollment |
| D6 | plan | Auto-verify manually created volunteers | Organizer is trust anchor. No email verification needed for admin-created records. | CreateVolunteerManually |
| D7 | plan | project_id nullable on email_verification_tokens | Backward compat with existing tokens. Production not live — could be NOT NULL, but nullable is safer. | Migration #17 |
| D8 | plan | One Livewire component for wizard (not 4) | Per architecture-patterns.md: multi-step forms use one component + Alpine x-show. | EventSignup |
| D9 | plan | Server-side step tracking ($currentStep) | Server validates before advancing. Alpine reads via $wire for x-show. Server is source of truth. | EventSignup |
| D10 | plan | No Playwright in M10 | Livewire feature tests cover wizard flow. Playwright E2E deferred to M13 polish milestone. | Test scope |
| D11 | plan-review | Move session reservation delete inside DB::transaction | Race condition: between delete and lockForUpdate, another user could claim freed capacity | ReserveShifts |
| D12 | plan-review | Audit and enumerate ALL isFull()/spotsRemaining() callers | Add withCount('activeReservations') to ManualEnrollment, PublicEventController, ShiftResource, and any other caller | All shift-listing code |
| D13 | plan-review | Add DB reservation existence check before wizard submit | Timestamp-only check misses scheduler deletion; better error message when reservations expired | EventSignup wizard |
| D14 | plan-review | Add 0-15s random jitter to Alpine timer | Prevents thundering herd when many users expire simultaneously | reservation-timer.js |
| D15 | plan-review | Batch deletion in ReleaseExpiredReservations | limit(1000) loop prevents single massive DELETE after scheduler stall | ReleaseExpiredReservations |
| D16 | plan-review | Replace 3 booleans with WizardState enum | Makes invalid wizard states unrepresentable; cleaner than currentStep + pendingVerification + signupComplete | EventSignup, WizardState enum |
| D17 | plan-review | Rename SignupBatchResult → ShiftSignupResult | Clearer naming in pipeline; add PHPDoc to each VO explaining position | VOs |
| D18 | plan-review | Sort shift IDs in ReserveShifts before lock loop | Prevents deadlocks with concurrent SignUpVolunteerForShifts | ReserveShifts |
| D19 | plan-review | Add PHPDoc explaining session-scoped reservations | Knowledge must live in code, not just plan doc | ShiftReservation, ReserveShifts |
| D20 | plan-review | Document multi-tab limitation in code | Same session → tab B deletes tab A reservations. Known edge case for target audience. | ReserveShifts PHPDoc |

## Reviews

### implement — 2026-03-31

| # | Perspective | Concern | Severity | Resolution | Rationale |
|---|---|---|---|---|---|
| 1 | Security Paranoid | Missing `#[Locked]` on `EventSignup.$event` | high | accepted | Added `#[Locked]` — prevents event ID spoofing via snapshot mutation |
| 2 | Accessibility Champion | No step announcement on wizard transitions | high | accepted | Added `aria-live="polite"` region with Alpine watcher; updates on state change |
| 3 | Accessibility Champion | No focus management between steps | high | accepted | Alpine watcher calls `getElementById('step-heading-' + state).focus()` via `$nextTick` |
| 4 | Simplicity Zealot | Dual deadline tracking — timer fires up to 15s after server expiry | high | partially accepted | Kept timer as informational UX; added server-side reservation check in `advanceToPersonalInfo()` and `advanceToConfirmation()` so expiry is caught at every step advance, not just submit |
| 5 | Security Paranoid | Email XSS in translation interpolation | medium | accepted | Added explicit `e()` around `$volunteerEmail` before passing to `__()` |
| 6 | Security Paranoid | ManualEnrollment: no `#[Locked]` on `$event` or `$selectedVolunteerId` | medium | accepted | Added `#[Locked]` to both properties |
| 7 | Accessibility Champion | Progress indicator has no semantic role | medium | accepted | Wrapped in `<nav aria-label="Signup steps">`, added `aria-current="step"` and `aria-disabled="true"` |
| 8 | Accessibility Champion | Disabled (full) shift checkboxes don't explain why | medium | accepted | Added `aria-label` with "Full, no spots available" on disabled inputs |
| 9 | Accessibility Champion | Timer has no accessible context for SR users | medium | accepted | Added `role="timer"`, `aria-label`, `aria-hidden` on visual span, milestone-based `srAnnouncement` in JS (5m/2m/1m/30s) |
| 10 | Simplicity Zealot | Session reservations silently break multi-tab | medium | deferred | Known limitation documented in code (D20). Edge case for volunteer signups. |
| 11 | Simplicity Zealot | Action orchestration leaks into component | medium | deferred | Works correctly; refactor is future-milestone work |
| 12 | Security Paranoid | Custom field options rendered without sanitization | medium | deferred | Admin-created content (trusted). Audit when custom field admin UI is built. |
| 13 | Simplicity Zealot | WizardState conflates navigable and terminal states | low | deferred | Low risk; no invalid transition evidence. Add guard if states grow. |
| 14 | Simplicity Zealot | Gear/custom field validation duplicated | low | deferred | Two call sites is acceptable now; extract if rules diverge. |
| 15 | Security Paranoid | `$customFieldResponses` in snapshot (sensitive data) | low | deferred | No sensitive custom fields in current use; revisit if health/legal data fields added. |

### plan — 2026-03-31

| # | Perspective | Concern | Severity | Resolution | Rationale |
|---|---|---|---|---|---|
| 1 | Devil's Advocate | Race condition: session delete outside transaction | high | accepted | Move delete inside transaction (D11) |
| 2 | Devil's Advocate | isFull() blast radius under-specified — admin views show phantom full | high | accepted | Enumerate all callers (D12) |
| 3 | Scalability | N+1 in ManualEnrollment/PublicEventController/ShiftResource | high | accepted | Same fix as #2 (D12) |
| 4 | Scalability | Lock contention on popular shifts with 50+ concurrent users | high | rejected | Target audience is small/mid orgs. Acceptable at scale. |
| 5 | Devil's Advocate | Multi-tab shares session → destructive reservation replacement | medium | deferred | Known limitation, document in code (D20). Edge case for volunteer signups. |
| 6 | Devil's Advocate | Server-side expiry check is timestamp-only, no DB verification | medium | accepted | Add DB existence check (D13) |
| 7 | Scalability | Thundering herd on expiry — 50 users fire at same second | medium | accepted | Add random jitter (D14) |
| 8 | Scalability | Cleanup at scale after scheduler stall — large DELETE | medium | accepted | Batch deletion (D15) |
| 9 | Junior Dev | Wizard state spread across 3 variables — can desync | medium | accepted | WizardState enum (D16) |
| 10 | Junior Dev | Three VOs with no naming pattern | medium | accepted | Rename + PHPDoc (D17) |
| 11 | Devil's Advocate | ReserveShifts code doesn't sort shift IDs | low | accepted | Add sort (D18) |
| 12 | Junior Dev | Session-scoped reservations lack inline explanation | low | accepted | PHPDoc (D19) |
| 13 | Junior Dev | Alpine timer / $wire bridge under-documented | low | accepted | Comment block in JS |
| 14 | Junior Dev | goToStep() silently ignores invalid input | low | accepted | Inline comment |
| 15 | Scalability | Multi-tab shared session (dup of #5) | low | — | Duplicate |

## Feedback Loops

| # | Date | Direction | Trigger | Fix | Resolution |
|---|---|---|---|---|---|

## Cross-Milestone Interface

| Category | Items |
|---|---|
| M8 dependency | ProcessVolunteerSignup: firstOrCreate scoped by project_id (already done) |
| M8 dependency | Volunteers, gear, custom fields, tickets are project-scoped (already done) |
| M9 dependency | EventPolicy::manageJobs gates ManualEnrollment access (already done) |
| M11 impact | Scanner does not depend on M10 signup changes — separate codepath |
| M13 impact | Playwright E2E for wizard flow deferred to M13 |

## Tasks

- [x] Phase 1.1: Migration — create_shift_reservations_table
- [x] Phase 1.2: Migration — add_project_id_to_email_verification_tokens
- [x] Phase 1.3: Model — ShiftReservation + factory
- [x] Phase 1.4: Model — EmailVerificationToken update (project_id fillable + relationship)
- [x] Phase 1.5: Factory — EmailVerificationTokenFactory update
- [x] Phase 1.6: Verify migrate:fresh --seed
- [x] Phase 1.7: WizardState enum (D16)
- [x] Phase 1.8: Rename SignupBatchResult -> ShiftSignupResult (D17) + PHPDoc on all VOs
- [x] Phase 2.1: Value object — ReservationResult
- [x] Phase 2.2: Action — ReserveShifts (with D11, D18, D19, D20)
- [x] Phase 2.3: Action — ReleaseExpiredReservations (with D15 batch deletion)
- [x] Phase 2.4: Action — CreateVolunteerManually
- [x] Phase 2.5: Model — Shift: reservations(), activeReservations(), isFull(), spotsRemaining()
- [x] Phase 2.6: Action — SignUpVolunteerForShifts: add sessionId, release reservations (D11)
- [x] Phase 2.7: Action — ProcessVolunteerSignup: add sessionId, pass through
- [x] Phase 2.8: Action — SendEmailVerification: add project_id to token
- [x] Phase 2.9: Tests — ReserveShiftsTest (8 tests)
- [x] Phase 2.10: Tests — ReleaseExpiredReservationsTest (4 tests)
- [x] Phase 2.11: Tests — CreateVolunteerManuallyTest (5 tests)
- [x] Phase 2.12: Tests — ShiftReservationTest (model) (5 tests)
- [x] Phase 2.13: Tests — Shift model updated capacity tests (5 tests)
- [x] Phase 2.14: Tests — ProcessVolunteerSignupTest updates (1 new test)
- [x] Phase 2.15: Tests — SignUpVolunteerForShiftsTest updates (1 new test)
- [x] Phase 2.16: Tests — SendEmailVerificationTest update (1 new test for project_id)
- [x] Phase 2.17: D12 — withCount('activeReservations') added to all isFull()/spotsRemaining() callers
- [x] Phase 5.1: Command — ReleaseExpiredReservationsCommand
- [x] Phase 5.2: Schedule — register in console.php
- [x] Phase 5.3: Tests — ReleaseExpiredReservationsCommandTest (3 tests)
- [x] Phase 3.1: Alpine — reservation-timer.js (with D14 jitter)
- [x] Phase 3.2: Vite — register timer in app.js entry point
- [x] Phase 3.3: Component — EventSignup rewrite (PHP) with WizardState enum (D16), DB reservation check (D13)
- [x] Phase 3.4: Template — event-signup.blade.php rewrite (4-step wizard, timer, step indicator, confirmation summary)
- [x] Phase 3.5: Tests — EventSignupTest updates (both files rewritten for multi-step flow)
- [x] Phase 3.6: Tests — New wizard-specific tests (navigation, expiry, D13 DB check, step 2 gear/fields)
- [x] Phase 3.7: Pint — clean
- [x] Phase 4.1: Component — ManualEnrollment create-new mode (createAndSelect, toggleCreateMode)
- [x] Phase 4.2: Template — manual-enrollment.blade.php update (Create New toggle + form)
- [x] Phase 4.3: Tests — ManualEnrollmentTest updates + 7 new tests
- [x] Phase 4.4: Pint — clean
- [x] Phase 6.1: Tests — VolunteerSignupFlowTest updates (4 tests for wizard flow, including reservation release)
- [x] Phase 6.2: Full test suite green (1042 tests, 2267 assertions)
- [x] Phase 6.3: Pint clean
- [ ] Phase 6.4: Browser smoke test (manual — requires running app)
