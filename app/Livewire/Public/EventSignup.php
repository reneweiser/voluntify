<?php

namespace App\Livewire\Public;

use App\Actions\ProcessVolunteerSignup;
use App\Actions\ReserveShifts;
use App\Actions\SendEmailVerification;
use App\Enums\EventStatus;
use App\Enums\GearItemType;
use App\Enums\HintLocation;
use App\Enums\WizardState;
use App\Exceptions\DomainException;
use App\Models\EmailVerificationToken;
use App\Models\Event;
use App\Models\ShiftReservation;
use App\Models\Volunteer;
use App\Services\HintTextResolver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.public')]
#[Title('Event Signup')]
class EventSignup extends Component
{
    #[Locked]
    public Event $event;

    public WizardState $state = WizardState::EmailEntry;

    /** @var array<int> */
    public array $selectedShiftIds = [];

    #[Locked]
    public string $reservationExpiresAt = '';

    /** @var array<int, string|null> */
    public array $gearSelections = [];

    /** @var array<int, mixed> */
    public array $customFieldResponses = [];

    public string $volunteerFirstName = '';

    public string $volunteerLastName = '';

    public string $volunteerEmail = '';

    public string $volunteerPhone = '';

    #[Locked]
    public string $warningMessage = '';

    #[Locked]
    public string $lookupMessage = '';

    #[Locked]
    public ?int $verificationTokenId = null;

    #[Locked]
    public ?int $existingVolunteerId = null;

    /** @var array<int> */
    #[Locked]
    public array $existingShiftIds = [];

    /** @var array<int, string|null> gear_item_id => size */
    #[Locked]
    public array $existingGearSelections = [];

    #[Locked]
    public bool $isReturningVolunteer = false;

    #[Locked]
    public ?string $verificationStartedAt = null;

    public function mount(string $publicToken): void
    {
        $this->event = Event::with('project')
            ->where('public_token', $publicToken)
            ->where('status', EventStatus::PublishedOpen)
            ->firstOrFail();

        // Cross-device verification: accept ?vt= query param with token hash (unguessable)
        $vt = request()->query('vt');
        if ($vt) {
            $token = EmailVerificationToken::where('token_hash', $vt)
                ->where('event_id', $this->event->id)
                ->whereNotNull('verified_at')
                ->where('verified_at', '>', now()->subMinutes(30))
                ->first();

            if ($token) {
                $this->volunteerEmail = $token->volunteer?->email ?? $token->email ?? '';
                $this->prefillFromVolunteer($token->volunteer);
                $this->state = WizardState::PersonalInfo;
            }
        }
    }

    /**
     * Visible gear items: SizeSelection only, filtered by selected jobs.
     */
    #[Computed]
    public function gearItems(): Collection
    {
        $items = $this->event->project?->gearItems()->get() ?? new Collection;

        // Only show SizeSelection items in the signup form (Quantity auto-assigned in backend)
        $items = $items->filter(fn ($item) => $item->type === GearItemType::SizeSelection);

        // Filter by selected jobs (only after shifts are selected)
        if (! empty($this->selectedShiftIds)) {
            $jobIds = $this->selectedJobIds;
            $items = $items->filter(fn ($item) => $item->job_ids === null || ! empty(array_intersect($item->job_ids, $jobIds)));
        }

        return $items->values();
    }

    #[Computed]
    public function customRegistrationFields(): Collection
    {
        $projectFields = $this->event->project?->customRegistrationFields()->get() ?? new Collection;
        $eventFields = $this->event->customRegistrationFields()->get();

        return $projectFields->merge($eventFields);
    }

    #[Computed]
    public function jobs(): Collection
    {
        return $this->event->volunteerJobs()
            ->active()
            ->whereHas('shifts', fn ($query) => $query->active())
            ->with(['shifts' => fn ($query) => $query->active()
                ->withCount(['activeSignups as signups_count', 'activeReservations as active_reservations_count'])
                ->orderBy('shift_date')
                ->orderBy('starts_at')])
            ->get();
    }

    /**
     * Job IDs derived from the selected shifts. Uses the cached jobs computed.
     *
     * @return array<int>
     */
    #[Computed]
    public function selectedJobIds(): array
    {
        if (empty($this->selectedShiftIds)) {
            return [];
        }

        $intIds = array_map('intval', $this->selectedShiftIds);

        return $this->jobs
            ->filter(fn ($job) => $job->shifts->contains(fn ($s) => in_array((int) $s->id, $intIds, true)))
            ->pluck('id')
            ->unique()
            ->values()
            ->all();
    }

    #[Computed]
    public function hintSignupEmail(): ?string
    {
        return $this->resolveHint(HintLocation::SignupEmail);
    }

    #[Computed]
    public function hintSignupPhone(): ?string
    {
        return $this->resolveHint(HintLocation::SignupPhone);
    }

    #[Computed]
    public function hintSignupSummary(): ?string
    {
        return $this->resolveHint(HintLocation::SignupSummary);
    }

    private function resolveHint(HintLocation $location): ?string
    {
        if (! $this->event->project) {
            return null;
        }

        return app(HintTextResolver::class)->resolve($location, $this->event->project);
    }

    /**
     * Whether the gear & fields step should be shown. Uses filtered gear list
     * (SizeSelection + job-matched) to prevent empty step for Quantity-only events.
     */
    #[Computed]
    public function hasGearOrFields(): bool
    {
        return $this->gearItems->isNotEmpty() || $this->customRegistrationFields->isNotEmpty();
    }

    /**
     * Returns the IDs of selected shifts that overlap with at least one other
     * selected shift.
     *
     * @return array<int>
     */
    #[Computed]
    public function overlappingShiftIds(): array
    {
        if (count($this->selectedShiftIds) < 2) {
            return [];
        }

        $intIds = array_map('intval', $this->selectedShiftIds);

        $newOnly = array_diff($intIds, $this->existingShiftIds);
        if (count($newOnly) < 1) {
            return [];
        }

        $allShifts = $this->jobs->flatMap(fn ($job) => $job->shifts);

        $newShifts = $allShifts
            ->filter(fn ($s) => in_array((int) $s->id, $newOnly, true)
                && $s->starts_at !== null && $s->ends_at !== null)
            ->values();

        $existingShifts = $allShifts
            ->filter(fn ($s) => in_array((int) $s->id, $this->existingShiftIds, true)
                && $s->starts_at !== null && $s->ends_at !== null)
            ->values();

        $conflicting = [];

        // New vs new
        for ($i = 0; $i < $newShifts->count(); $i++) {
            for ($j = $i + 1; $j < $newShifts->count(); $j++) {
                if ($newShifts[$i]->starts_at < $newShifts[$j]->ends_at
                    && $newShifts[$i]->ends_at > $newShifts[$j]->starts_at) {
                    $conflicting[] = $newShifts[$i]->id;
                    $conflicting[] = $newShifts[$j]->id;
                }
            }
        }

        // New vs existing — only flag the new shift (existing is locked in the UI)
        foreach ($newShifts as $new) {
            foreach ($existingShifts as $existing) {
                if ($new->starts_at < $existing->ends_at && $new->ends_at > $existing->starts_at) {
                    $conflicting[] = $new->id;
                }
            }
        }

        return array_values(array_unique($conflicting));
    }

    /**
     * Step 0 -> Step 1 or 2: Submit email, send verification or skip for verified volunteers.
     */
    public function submitEmail(): void
    {
        $this->validate([
            'volunteerEmail' => ['required', 'email', 'max:255'],
        ]);

        $ipKey = 'signup-lookup:'.request()->ip();
        if (RateLimiter::tooManyAttempts($ipKey, 10)) {
            $this->addError('volunteerEmail', __('Too many attempts. Please wait a moment before trying again.'));

            return;
        }
        RateLimiter::hit($ipKey, 60);

        $emailKey = 'signup-lookup-email:'.strtolower(trim($this->volunteerEmail));
        if (RateLimiter::tooManyAttempts($emailKey, 3)) {
            $this->addError('volunteerEmail', __('Too many attempts for this email. Please wait a few minutes.'));

            return;
        }
        RateLimiter::hit($emailKey, 300);

        $volunteer = Volunteer::where('email', $this->volunteerEmail)
            ->where('project_id', $this->event->project_id)
            ->first();

        // ALL emails go through verification — no skip, no prefill before proof of ownership
        $token = app(SendEmailVerification::class)->execute(
            $this->volunteerEmail,
            $this->event,
            $volunteer,
        );

        $this->verificationTokenId = $token->id;
        $this->verificationStartedAt = now()->toISOString();
        $this->state = WizardState::PendingVerification;
    }

    /**
     * Polled every 3s from the PendingVerification screen.
     */
    public function checkVerification(): void
    {
        if ($this->verificationTokenId === null) {
            return;
        }

        // Max poll guard: stop after 10 minutes
        if ($this->verificationStartedAt && Carbon::parse($this->verificationStartedAt)->diffInMinutes(now()) >= 10) {
            return;
        }

        $token = EmailVerificationToken::find($this->verificationTokenId);

        if ($token?->isVerified()) {
            if ($token->volunteer_id) {
                $this->prefillFromVolunteer($token->volunteer);
            } else {
                $this->volunteerEmail = $token->email ?? $this->volunteerEmail;
            }
            $this->state = WizardState::PersonalInfo;

            return;
        }

        if ($token?->expires_at->isPast()) {
            $this->verificationTokenId = null;
        }
    }

    /**
     * Resend verification email (rate-limited).
     */
    public function resendVerification(): void
    {
        $emailKey = 'email-verification-resend:'.strtolower(trim($this->volunteerEmail));
        if (RateLimiter::tooManyAttempts($emailKey, 3)) {
            $this->addError('volunteerEmail', __('Too many resend attempts. Please wait before trying again.'));

            return;
        }
        RateLimiter::hit($emailKey, 3600);

        $volunteer = Volunteer::where('email', $this->volunteerEmail)
            ->where('project_id', $this->event->project_id)
            ->first();

        $token = app(SendEmailVerification::class)->execute(
            $this->volunteerEmail,
            $this->event,
            $volunteer,
        );

        $this->verificationTokenId = $token->id;
        $this->verificationStartedAt = now()->toISOString();
    }

    /**
     * Step 2 -> Step 3: Validate personal info and advance to shift selection.
     */
    public function advanceToShifts(): void
    {
        if ($this->state !== WizardState::PersonalInfo) {
            return;
        }

        $this->validatePersonalInfo();

        // Pre-select existing shifts for returning volunteers
        if (! empty($this->existingShiftIds)) {
            $this->selectedShiftIds = array_map('intval', array_unique(
                array_merge($this->selectedShiftIds, $this->existingShiftIds)
            ));
        }

        $this->state = WizardState::SelectingShifts;
    }

    /**
     * Step 3 -> Step 4 (or 5): Validate shift selection, reserve shifts, and advance.
     */
    public function reserveAndAdvance(): void
    {
        if (RateLimiter::tooManyAttempts('signup-reserve:'.request()->ip(), 15)) {
            $this->addError('selectedShiftIds', __('Too many attempts. Please wait a moment before trying again.'));

            return;
        }
        RateLimiter::hit('signup-reserve:'.request()->ip(), 60);

        $this->validate([
            'selectedShiftIds' => ['required', 'array', 'min:1'],
            'selectedShiftIds.*' => [
                'integer',
                Rule::exists('shifts', 'id')->where(fn ($q) => $q->whereIn(
                    'volunteer_job_id',
                    $this->event->volunteerJobs()->active()->select('id'),
                )->where('is_active', true)),
            ],
        ]);

        if (count($this->overlappingShiftIds) > 0) {
            return;
        }

        // Strip existing shifts from the selection for returning volunteers
        $newShiftIds = array_values(array_diff(
            array_map('intval', $this->selectedShiftIds),
            $this->existingShiftIds,
        ));

        if (empty($newShiftIds)) {
            $this->addError('selectedShiftIds', __('You are already signed up for all selected shifts.'));

            return;
        }

        $result = app(ReserveShifts::class)->execute(
            shiftIds: $newShiftIds,
            sessionId: session()->getId(),
            event: $this->event,
        );

        if (! $result->hasReservations()) {
            $this->addError('selectedShiftIds', __('All selected shifts are full.'));
            unset($this->jobs);

            return;
        }

        $this->selectedShiftIds = array_merge($this->existingShiftIds, $result->reservedShiftIds());
        unset($this->overlappingShiftIds, $this->selectedJobIds, $this->gearItems, $this->hasGearOrFields);
        $this->reservationExpiresAt = $result->expiresAt->toISOString();

        if (count($result->unavailable) > 0) {
            $this->warningMessage = __(':count shift(s) were full and removed from your selection.', [
                'count' => count($result->unavailable),
            ]);
        }

        if ($this->hasGearOrFields) {
            $this->state = WizardState::GearAndFields;
        } else {
            $this->state = WizardState::Confirming;
        }
    }

    /**
     * Step 4 -> Step 5: Validate gear/custom fields and advance to confirmation.
     */
    public function advanceToConfirmation(): void
    {
        if (! ShiftReservation::forSession(session()->getId())->active()->exists()) {
            $this->handleReservationExpired();

            return;
        }

        $this->validateGearAndCustomFields();
        $this->state = WizardState::Confirming;
    }

    /**
     * Navigate backward through the wizard.
     */
    public function goBack(): void
    {
        $this->state = match ($this->state) {
            WizardState::PersonalInfo => WizardState::EmailEntry,
            WizardState::SelectingShifts => WizardState::PersonalInfo,
            WizardState::GearAndFields => WizardState::SelectingShifts,
            WizardState::Confirming => $this->hasGearOrFields ? WizardState::GearAndFields : WizardState::SelectingShifts,
            default => $this->state,
        };
    }

    /**
     * Final submit: sign up directly (email already verified).
     */
    public function submitSignup(): void
    {
        if (RateLimiter::tooManyAttempts('signup-submit:'.request()->ip(), 5)) {
            $this->addError('volunteerEmail', __('Too many signup attempts. Please wait a few minutes before trying again.'));

            return;
        }
        RateLimiter::hit('signup-submit:'.request()->ip(), 300);

        $this->validatePersonalInfo();

        if ($this->hasGearOrFields) {
            $this->validateGearAndCustomFields();
        }

        // D13: Verify reservations still exist in DB, not just timestamp comparison.
        if (! ShiftReservation::forSession(session()->getId())->active()->exists()) {
            $this->handleReservationExpired();

            return;
        }

        $action = app(ProcessVolunteerSignup::class);

        try {
            // Strip to only valid gear item IDs and custom field IDs for this event.
            $gearSelections = $this->gearItems->isNotEmpty()
                ? collect($this->gearSelections)
                    ->filter()
                    ->only($this->gearItems->pluck('id')->all())
                    ->except(array_keys($this->existingGearSelections))
                    ->all()
                : null;

            $validFieldIds = $this->customRegistrationFields->pluck('id')->all();
            $customFieldResponses = $this->customRegistrationFields->isNotEmpty()
                ? collect($this->customFieldResponses)->only($validFieldIds)->all()
                : null;

            // Only submit newly selected shifts (exclude existing signups)
            $newShiftIds = array_values(array_diff(
                array_map('intval', $this->selectedShiftIds),
                $this->existingShiftIds,
            ));

            $result = $action->execute(
                firstName: $this->volunteerFirstName,
                lastName: $this->volunteerLastName,
                email: $this->volunteerEmail,
                event: $this->event,
                shiftIds: $newShiftIds,
                phone: $this->volunteerPhone ?: null,
                gearSelections: $gearSelections,
                customFieldResponses: $customFieldResponses,
                sessionId: session()->getId(),
            );

            if ($result->hasNewSignups()) {
                $this->state = WizardState::Complete;

                $skippedCount = count($result->skippedFull)
                    + count($result->skippedDuplicate)
                    + count($result->skippedOverlap);
                if ($skippedCount > 0) {
                    $this->warningMessage = __('Some shifts were skipped because they were full, you were already signed up, or they conflicted with another shift.');
                }
            } elseif (! $result->hasNewSignups() && count($result->skippedOverlap) > 0) {
                $this->addError('selectedShiftIds', __('All selected shifts conflict with existing signups.'));
                $this->restartSignup();
            } elseif (count($result->skippedDuplicate) === count($newShiftIds)) {
                $this->addError('selectedShiftIds', __('You are already signed up for all selected shifts.'));
                $this->restartSignup();
            } elseif (count($result->skippedFull) === count($newShiftIds)) {
                $this->addError('selectedShiftIds', __('All selected shifts are full.'));
                $this->restartSignup();
            } else {
                $this->addError('selectedShiftIds', __('Selected shifts are either full, already registered, or conflict with existing signups.'));
                $this->restartSignup();
            }
        } catch (DomainException $e) {
            $this->addError('selectedShiftIds', $e->getMessage());
        }
    }

    /**
     * Called by the Alpine reservation timer when the countdown reaches zero.
     */
    public function handleReservationExpired(): void
    {
        $this->state = WizardState::Expired;
        $this->reservationExpiresAt = '';
    }

    /**
     * Reset the wizard to step 1 for a fresh start.
     */
    public function restartSignup(): void
    {
        // Release any active reservations for this session
        ShiftReservation::forSession(session()->getId())->delete();

        $this->state = WizardState::EmailEntry;
        $this->selectedShiftIds = [];
        $this->reservationExpiresAt = '';
        $this->warningMessage = '';
        $this->lookupMessage = '';
        $this->verificationTokenId = null;
        $this->existingVolunteerId = null;
        $this->existingShiftIds = [];
        $this->existingGearSelections = [];
        $this->gearSelections = [];
        $this->customFieldResponses = [];
        $this->isReturningVolunteer = false;
        $this->verificationStartedAt = null;
        unset($this->jobs, $this->overlappingShiftIds, $this->selectedJobIds, $this->gearItems, $this->hasGearOrFields);
    }

    private function validateGearAndCustomFields(): void
    {
        $gearRules = [];
        foreach ($this->gearItems as $item) {
            if (array_key_exists($item->id, $this->existingGearSelections)) {
                continue;
            }
            if ($item->requires_size) {
                $gearRules['gearSelections.'.$item->id] = ['required', 'string', Rule::in($item->available_sizes ?? [])];
            }
        }

        $customFieldRules = [];
        foreach ($this->customRegistrationFields as $field) {
            $customFieldRules['customFieldResponses.'.$field->id] = $field->type->validationRules(
                $field->options ?? [],
                $field->required,
                $field->allow_multiple,
            );

            $itemRules = $field->type->validationItemRules($field->options ?? [], $field->allow_multiple);
            if ($itemRules !== null) {
                $customFieldRules['customFieldResponses.'.$field->id.'.*'] = $itemRules;
            }
        }

        $rules = array_merge($gearRules, $customFieldRules);

        if ($rules !== []) {
            $this->validate($rules);
        }
    }

    private function validatePersonalInfo(): void
    {
        $this->validate([
            'volunteerFirstName' => ['required', 'string', 'max:255'],
            'volunteerLastName' => ['required', 'string', 'max:255'],
            'volunteerEmail' => ['required', 'email', 'max:255'],
            'volunteerPhone' => [$this->event->phone_required ? 'required' : 'nullable', 'string', 'max:20'],
        ]);
    }

    private function prefillFromVolunteer(?Volunteer $volunteer): void
    {
        if (! $volunteer) {
            return;
        }

        $this->isReturningVolunteer = true;
        $this->existingVolunteerId = $volunteer->id;
        $this->volunteerFirstName = $volunteer->first_name;
        $this->volunteerLastName = $volunteer->last_name;
        $this->volunteerEmail = $volunteer->email;

        if ($volunteer->phone) {
            $this->volunteerPhone = $volunteer->phone;
        }

        // Load existing shift signups for this event
        $this->existingShiftIds = $volunteer->shiftSignups()
            ->active()
            ->whereHas('shift.volunteerJob', fn ($q) => $q->where('event_id', $this->event->id))
            ->pluck('shift_id')
            ->all();

        // Load existing gear selections (SizeSelection type only, project-scoped)
        $this->existingGearSelections = $volunteer->volunteerGear()
            ->whereHas('gearItem', fn ($q) => $q->where('project_id', $this->event->project_id)
                ->where('type', GearItemType::SizeSelection))
            ->get()
            ->pluck('size', 'project_gear_item_id')
            ->all();

        if (! empty($this->existingShiftIds) || ! empty($this->existingGearSelections)) {
            $this->lookupMessage = __('Details pre-filled from your previous signup.');
        }
    }
}
