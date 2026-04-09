<?php

namespace App\Livewire\Public;

use App\Actions\ProcessVolunteerSignup;
use App\Actions\ReserveShifts;
use App\Enums\EventStatus;
use App\Enums\GearItemType;
use App\Enums\HintLocation;
use App\Enums\WizardState;
use App\Exceptions\DomainException;
use App\Models\Event;
use App\Models\ShiftReservation;
use App\Models\Volunteer;
use App\Services\HintTextResolver;
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

    public WizardState $state = WizardState::PersonalInfo;

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

    public string $warningMessage = '';

    public string $lookupMessage = '';

    public function mount(string $publicToken): void
    {
        $this->event = Event::with('project')
            ->where('public_token', $publicToken)
            ->where('status', EventStatus::PublishedOpen)
            ->firstOrFail();
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
            ->with(['shifts' => fn ($q) => $q->withCount(['activeSignups as signups_count', 'activeReservations as active_reservations_count'])->orderBy('shift_date')->orderBy('starts_at')])
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

        $selected = $this->jobs
            ->flatMap(fn ($job) => $job->shifts)
            ->filter(fn ($shift) => in_array((int) $shift->id, $intIds, true)
                && $shift->starts_at !== null
                && $shift->ends_at !== null)
            ->values();

        $conflicting = [];

        for ($i = 0; $i < $selected->count(); $i++) {
            for ($j = $i + 1; $j < $selected->count(); $j++) {
                $a = $selected[$i];
                $b = $selected[$j];

                if ($a->starts_at < $b->ends_at && $a->ends_at > $b->starts_at) {
                    $conflicting[] = $a->id;
                    $conflicting[] = $b->id;
                }
            }
        }

        return array_values(array_unique($conflicting));
    }

    /**
     * Look up existing volunteer by email to pre-fill returning volunteer data.
     */
    public function lookupVolunteer(): void
    {
        if (! $this->volunteerEmail || ! $this->event->project_id) {
            $this->lookupMessage = '';

            return;
        }

        $key = 'signup-lookup:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return;
        }
        RateLimiter::hit($key, 60);

        $volunteer = Volunteer::where('email', $this->volunteerEmail)
            ->where('project_id', $this->event->project_id)
            ->first();

        if ($volunteer) {
            $this->volunteerFirstName = $volunteer->first_name;
            $this->volunteerLastName = $volunteer->last_name;
            if ($volunteer->phone) {
                $this->volunteerPhone = $volunteer->phone;
            }
            $this->lookupMessage = __('Details pre-filled from your previous signup.');
        } else {
            $this->lookupMessage = '';
        }
    }

    /**
     * Step 1 -> Step 2: Validate personal info and advance to shift selection.
     */
    public function advanceToShifts(): void
    {
        $this->validatePersonalInfo();
        $this->state = WizardState::SelectingShifts;
    }

    /**
     * Step 2 -> Step 3 (or 4): Validate shift selection, reserve shifts, and advance.
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
                    $this->event->volunteerJobs()->select('id'),
                )),
            ],
        ]);

        if (count($this->overlappingShiftIds) > 0) {
            return;
        }

        $result = app(ReserveShifts::class)->execute(
            shiftIds: array_map('intval', $this->selectedShiftIds),
            sessionId: session()->getId(),
            event: $this->event,
        );

        if (! $result->hasReservations()) {
            $this->addError('selectedShiftIds', __('All selected shifts are full.'));
            unset($this->jobs);

            return;
        }

        $this->selectedShiftIds = $result->reservedShiftIds();
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
     * Step 3 -> Step 4: Validate gear/custom fields and advance to confirmation.
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
            WizardState::SelectingShifts => WizardState::PersonalInfo,
            WizardState::GearAndFields => WizardState::SelectingShifts,
            WizardState::Confirming => WizardState::PersonalInfo,
            default => $this->state,
        };
    }

    /**
     * Step 4: Final submit.
     */
    public function submitSignup(): void
    {
        if (RateLimiter::tooManyAttempts('signup-submit:'.request()->ip(), 5)) {
            $this->addError('volunteerEmail', __('Too many signup attempts. Please wait a few minutes before trying again.'));

            return;
        }
        RateLimiter::hit('signup-submit:'.request()->ip(), 300);

        // Per-email rate limit: prevent email-bombing a specific address (3 per hour)
        $emailKey = 'email-verification-resend:'.strtolower(trim($this->volunteerEmail));
        if ($this->volunteerEmail && RateLimiter::tooManyAttempts($emailKey, 3)) {
            $this->addError('volunteerEmail', 'Zu viele Versuche für diese E-Mail-Adresse. Bitte warte eine Stunde.');

            return;
        }
        RateLimiter::hit($emailKey, 3600);

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
                    ->all()
                : null;

            $validFieldIds = $this->customRegistrationFields->pluck('id')->all();
            $customFieldResponses = $this->customRegistrationFields->isNotEmpty()
                ? collect($this->customFieldResponses)->only($validFieldIds)->all()
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
                $this->state = WizardState::PendingVerification;

                return;
            }

            $result = $outcome->batchResult;

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
            } elseif (count($result->skippedDuplicate) === count($this->selectedShiftIds)) {
                $this->addError('selectedShiftIds', __('You are already signed up for all selected shifts.'));
                $this->restartSignup();
            } elseif (count($result->skippedFull) === count($this->selectedShiftIds)) {
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
        $this->state = WizardState::PersonalInfo;
        $this->selectedShiftIds = [];
        $this->reservationExpiresAt = '';
        $this->warningMessage = '';
        $this->lookupMessage = '';
        unset($this->jobs, $this->overlappingShiftIds, $this->selectedJobIds, $this->gearItems, $this->hasGearOrFields);
    }

    private function validateGearAndCustomFields(): void
    {
        $gearRules = [];
        foreach ($this->gearItems as $item) {
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

        $this->validate(array_merge($gearRules, $customFieldRules));
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
}
