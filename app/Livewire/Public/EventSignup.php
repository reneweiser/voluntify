<?php

namespace App\Livewire\Public;

use App\Actions\ProcessVolunteerSignup;
use App\Actions\ReserveShifts;
use App\Actions\SendEmailVerification;
use App\Actions\SubscribeToEventNotifications;
use App\Enums\EventStatus;
use App\Enums\GearItemType;
use App\Enums\HintLocation;
use App\Enums\WizardState;
use App\Exceptions\DomainException;
use App\Models\EmailVerificationToken;
use App\Models\Event;
use App\Models\Shift;
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

    #[Locked]
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

    public string $notificationSubscriptionEmail = '';

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

    #[Locked]
    public string $notificationSubscriptionMessage = '';

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
                ->where('expires_at', '>', now())
                ->first();

            if ($token) {
                $this->verificationTokenId = $token->id;
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
        return $this->event->publicSignupJobs($this->existingShiftIds);
    }

    /**
     * @return array{is_open: bool, is_closed: bool, threshold_percent: ?int, filled_spots: int, total_spots: int, progress_percent: int}
     */
    #[Computed]
    public function priorityGateStatus(): array
    {
        $event = $this->event->fresh();
        $filledSpots = $event->priorityFilledSpots();
        $totalSpots = $event->priorityCapacityTotal();

        return [
            'is_open' => $event->isPriorityGateOpen(),
            'is_closed' => ! $event->isPriorityGateOpen(),
            'threshold_percent' => $event->priority_unlock_threshold_percent,
            'filled_spots' => $filledSpots,
            'total_spots' => $totalSpots,
            'progress_percent' => $totalSpots === 0 ? 100 : min(100, (int) round(($filledSpots / $totalSpots) * 100)),
        ];
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

    /**
     * @return array<int>
     */
    #[Computed]
    public function renderableShiftIds(): array
    {
        return $this->jobs
            ->flatMap(fn ($job) => $job->shifts)
            ->pluck('id')
            ->map(fn ($shiftId) => (int) $shiftId)
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
     * Returns the selected new shifts keyed to their specific conflicts.
     *
     * @return array<int, array{shift_id: int, shift_label: string, conflicts: array<int, array{shift_id: int, shift_label: string, is_existing: bool}>}>
     */
    #[Computed]
    public function overlapConflictMap(): array
    {
        if (count($this->selectedShiftIds) < 2) {
            return [];
        }

        $selectedShiftIds = array_map('intval', $this->selectedShiftIds);
        $newShiftIds = array_values(array_diff($selectedShiftIds, $this->existingShiftIds));

        if ($newShiftIds === []) {
            return [];
        }

        $shiftContexts = $this->shiftContextMap();

        $newShifts = collect($newShiftIds)
            ->map(fn (int $shiftId) => $shiftContexts[$shiftId] ?? null)
            ->filter(fn (?array $context) => $context !== null
                && $context['shift']->starts_at !== null
                && $context['shift']->ends_at !== null)
            ->values();

        $existingShifts = collect($this->existingShiftIds)
            ->map(fn (int $shiftId) => $shiftContexts[$shiftId] ?? null)
            ->filter(fn (?array $context) => $context !== null
                && $context['shift']->starts_at !== null
                && $context['shift']->ends_at !== null)
            ->values();

        $conflicts = [];

        for ($i = 0; $i < $newShifts->count(); $i++) {
            for ($j = $i + 1; $j < $newShifts->count(); $j++) {
                if (! $this->shiftsOverlap($newShifts[$i]['shift'], $newShifts[$j]['shift'])) {
                    continue;
                }

                $this->addOverlapConflict($conflicts, $newShifts[$i], $newShifts[$j], false);
                $this->addOverlapConflict($conflicts, $newShifts[$j], $newShifts[$i], false);
            }
        }

        foreach ($newShifts as $newShift) {
            foreach ($existingShifts as $existingShift) {
                if (! $this->shiftsOverlap($newShift['shift'], $existingShift['shift'])) {
                    continue;
                }

                $this->addOverlapConflict($conflicts, $newShift, $existingShift, true);
            }
        }

        return $conflicts;
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
        return array_map('intval', array_keys($this->overlapConflictMap));
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

        $emailKey = 'signup-lookup-email:'.$this->event->id.':'.strtolower(trim($this->volunteerEmail));
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
        if (! $this->ensureStateIs(WizardState::PendingVerification)) {
            return;
        }

        $this->validate([
            'volunteerEmail' => ['required', 'email', 'max:255'],
        ]);

        $token = $this->currentPendingVerificationToken();

        if (! $token) {
            $this->addError('volunteerEmail', __('Your verification attempt is no longer active. Please start again.'));

            return;
        }

        $emailKey = 'email-verification-resend:'.$this->event->id.':'.strtolower(trim($this->volunteerEmail));
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

        if ($this->notificationSubscriptionEmail === '') {
            $this->notificationSubscriptionEmail = $this->volunteerEmail;
        }

        $this->state = WizardState::SelectingShifts;
    }

    public function subscribeToNotifications(): void
    {
        if ($this->state !== WizardState::SelectingShifts || $this->jobs->isNotEmpty()) {
            $this->addError('notificationSubscriptionEmail', __('Notifications are only available when no shifts can be selected.'));

            return;
        }

        $this->validate([
            'notificationSubscriptionEmail' => ['required', 'email', 'max:255'],
        ]);

        $ipKey = 'event-notification-subscribe:'.request()->ip();
        if (RateLimiter::tooManyAttempts($ipKey, 10)) {
            $this->addError('notificationSubscriptionEmail', __('Too many attempts. Please wait a moment before trying again.'));

            return;
        }
        RateLimiter::hit($ipKey, 60);

        $emailKey = 'event-notification-subscribe-email:'.$this->event->id.':'.strtolower(trim($this->notificationSubscriptionEmail));
        if (RateLimiter::tooManyAttempts($emailKey, 3)) {
            $this->addError('notificationSubscriptionEmail', __('Too many attempts for this email. Please wait a few minutes.'));

            return;
        }
        RateLimiter::hit($emailKey, 300);

        app(SubscribeToEventNotifications::class)->execute($this->event, $this->notificationSubscriptionEmail);

        $this->notificationSubscriptionMessage = __('Check your inbox to confirm your notification signup. Once confirmed, we will email you when new shifts become available.');
    }

    /**
     * Step 3 -> Step 4 (or 5): Validate shift selection, reserve shifts, and advance.
     */
    public function reserveAndAdvance(): void
    {
        if (! $this->ensureStateIs(WizardState::SelectingShifts)) {
            return;
        }

        if (RateLimiter::tooManyAttempts('signup-reserve:'.request()->ip(), 15)) {
            $this->addError('selectedShiftIds', __('Too many attempts. Please wait a moment before trying again.'));

            return;
        }
        RateLimiter::hit('signup-reserve:'.request()->ip(), 60);

        $this->validate([
            'selectedShiftIds' => ['required', 'array', 'min:1'],
            'selectedShiftIds.*' => [
                'integer',
                Rule::in($this->renderableShiftIds),
                Rule::exists('shifts', 'id')->where(fn ($q) => $q->whereIn(
                    'volunteer_job_id',
                    $this->event->volunteerJobs()->active()->select('id'),
                )->where('is_active', true)),
            ],
        ]);

        if (count($this->overlappingShiftIds) > 0) {
            $this->addError('selectedShiftIds', __('Some selected shifts overlap in time. Review the conflict details below before continuing.'));

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

        try {
            $result = app(ReserveShifts::class)->execute(
                shiftIds: $newShiftIds,
                sessionId: session()->getId(),
                event: $this->event,
            );
        } catch (DomainException $e) {
            $this->addError('selectedShiftIds', $e->getMessage());

            return;
        }

        if (! $result->hasReservations()) {
            $this->addError('selectedShiftIds', __('All selected shifts are full.'));
            unset($this->jobs);

            return;
        }

        $this->selectedShiftIds = array_merge($this->existingShiftIds, $result->reservedShiftIds());
        unset($this->overlapConflictMap, $this->overlappingShiftIds, $this->selectedJobIds, $this->gearItems, $this->hasGearOrFields);
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
        if (! $this->ensureStateIs(WizardState::GearAndFields)) {
            return;
        }

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
        if (! $this->ensureStateIs(WizardState::Confirming)) {
            return;
        }

        if (RateLimiter::tooManyAttempts('signup-submit:'.request()->ip(), 5)) {
            $this->addError('volunteerEmail', __('Too many signup attempts. Please wait a few minutes before trying again.'));

            return;
        }
        RateLimiter::hit('signup-submit:'.request()->ip(), 300);

        $this->validatePersonalInfo();

        if ($this->hasGearOrFields) {
            $this->validateGearAndCustomFields();
        }

        $verificationToken = $this->verificationTokenId !== null
            ? EmailVerificationToken::find($this->verificationTokenId)
            : null;

        $verifiedEmail = $verificationToken?->volunteer?->email ?? $verificationToken?->email;

        if (! $verificationToken?->isVerified() || $verificationToken->expires_at->isPast() || $verificationToken->project_id !== $this->event->project_id || $verifiedEmail === null || strcasecmp($this->volunteerEmail, $verifiedEmail) !== 0) {
            $this->addError('volunteerEmail', __('Your verified email no longer matches the submitted email. Please verify your email again before completing your signup.'));
            $this->restartSignup();

            return;
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

            $reservedShiftIds = ShiftReservation::forSession(session()->getId())
                ->active()
                ->pluck('shift_id')
                ->map(fn ($shiftId) => (int) $shiftId)
                ->sort()
                ->values()
                ->all();

            $submittedShiftIds = collect($newShiftIds)
                ->sort()
                ->values()
                ->all();

            if ($submittedShiftIds !== $reservedShiftIds) {
                $this->addError('selectedShiftIds', __('Your shift reservation no longer matches your current selection. Please review your shifts and continue again.'));
                $this->restartSignup();

                return;
            }

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
        $this->volunteerFirstName = '';
        $this->volunteerLastName = '';
        $this->volunteerEmail = '';
        $this->volunteerPhone = '';
        $this->warningMessage = '';
        $this->lookupMessage = '';
        $this->notificationSubscriptionEmail = '';
        $this->notificationSubscriptionMessage = '';
        $this->verificationTokenId = null;
        $this->existingVolunteerId = null;
        $this->existingShiftIds = [];
        $this->existingGearSelections = [];
        $this->gearSelections = [];
        $this->customFieldResponses = [];
        $this->isReturningVolunteer = false;
        $this->verificationStartedAt = null;
        unset($this->jobs, $this->overlapConflictMap, $this->overlappingShiftIds, $this->selectedJobIds, $this->gearItems, $this->hasGearOrFields);
    }

    /**
     * @return array<int, array{shift: Shift, label: string}>
     */
    private function shiftContextMap(): array
    {
        $timezone = $this->event->project->timezone ?? 'UTC';
        $contexts = [];

        foreach ($this->jobs as $job) {
            foreach ($job->shifts as $shift) {
                $contexts[(int) $shift->id] = [
                    'shift' => $shift,
                    'label' => $job->name.' — '.$shift->shift_date->setTimezone($timezone)->format('M d').' '.$shift->displayTimeRange($timezone),
                ];
            }
        }

        return $contexts;
    }

    private function shiftsOverlap(Shift $firstShift, Shift $secondShift): bool
    {
        return $firstShift->starts_at < $secondShift->ends_at
            && $firstShift->ends_at > $secondShift->starts_at;
    }

    /**
     * @param  array<int, array{shift_id: int, shift_label: string, conflicts: array<int, array{shift_id: int, shift_label: string, is_existing: bool}>}>  $conflicts
     * @param  array{shift: Shift, label: string}  $selectedShift
     * @param  array{shift: Shift, label: string}  $conflictingShift
     */
    private function addOverlapConflict(array &$conflicts, array $selectedShift, array $conflictingShift, bool $isExisting): void
    {
        $selectedShiftId = (int) $selectedShift['shift']->id;
        $conflictingShiftId = (int) $conflictingShift['shift']->id;

        $conflicts[$selectedShiftId] ??= [
            'shift_id' => $selectedShiftId,
            'shift_label' => $selectedShift['label'],
            'conflicts' => [],
        ];

        $alreadyRecorded = collect($conflicts[$selectedShiftId]['conflicts'])
            ->contains(fn (array $conflict) => $conflict['shift_id'] === $conflictingShiftId);

        if ($alreadyRecorded) {
            return;
        }

        $conflicts[$selectedShiftId]['conflicts'][] = [
            'shift_id' => $conflictingShiftId,
            'shift_label' => $conflictingShift['label'],
            'is_existing' => $isExisting,
        ];
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

    /**
     * @param  array{shift_id: int, shift_label: string, conflicts: array<int, array{shift_id: int, shift_label: string, is_existing: bool}>}  $conflict
     */
    public function overlapConflictMessage(array $conflict): string
    {
        $existingLabels = collect($conflict['conflicts'])
            ->filter(fn (array $item) => $item['is_existing'])
            ->pluck('shift_label')
            ->values()
            ->all();

        $selectedLabels = collect($conflict['conflicts'])
            ->reject(fn (array $item) => $item['is_existing'])
            ->pluck('shift_label')
            ->values()
            ->all();

        $segments = [];

        if ($existingLabels !== []) {
            $segments[] = count($existingLabels) === 1
                ? __('your existing shift :shifts', ['shifts' => $this->implodeLabels($existingLabels)])
                : __('your existing shifts :shifts', ['shifts' => $this->implodeLabels($existingLabels)]);
        }

        if ($selectedLabels !== []) {
            $segments[] = count($selectedLabels) === 1
                ? __('the selected shift :shifts', ['shifts' => $this->implodeLabels($selectedLabels)])
                : __('the selected shifts :shifts', ['shifts' => $this->implodeLabels($selectedLabels)]);
        }

        return __('Deselect :shift because it overlaps :conflicts.', [
            'shift' => $conflict['shift_label'],
            'conflicts' => $this->implodeLabels($segments),
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

    private function ensureStateIs(WizardState $expectedState): bool
    {
        if ($this->state === $expectedState) {
            return true;
        }

        $this->addError('state', __('Please continue from the current signup step.'));

        return false;
    }

    private function currentPendingVerificationToken(): ?EmailVerificationToken
    {
        if ($this->verificationTokenId === null) {
            return null;
        }

        $token = EmailVerificationToken::find($this->verificationTokenId);

        if (! $token) {
            return null;
        }

        if ($token->event_id !== $this->event->id || $token->project_id !== $this->event->project_id) {
            return null;
        }

        if ($token->isVerified() || $token->expires_at->isPast()) {
            return null;
        }

        if (strcasecmp($token->email ?? '', $this->volunteerEmail) !== 0) {
            return null;
        }

        return $token;
    }

    /**
     * @param  array<int, string>  $labels
     */
    private function implodeLabels(array $labels): string
    {
        if (count($labels) <= 1) {
            return $labels[0] ?? '';
        }

        $lastLabel = array_pop($labels);

        return implode(', ', $labels).' '.__('and').' '.$lastLabel;
    }
}
