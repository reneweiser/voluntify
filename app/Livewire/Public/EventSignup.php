<?php

namespace App\Livewire\Public;

use App\Actions\SignUpVolunteerForShifts;
use App\Enums\EventStatus;
use App\Exceptions\DomainException;
use App\Models\Event;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.public')]
#[Title('Event Signup')]
class EventSignup extends Component
{
    public Event $event;

    public string $volunteerName = '';

    public string $volunteerEmail = '';

    public string $volunteerPhone = '';

    /** @var array<int> */
    public array $selectedShiftIds = [];

    public bool $signupComplete = false;

    public string $warningMessage = '';

    public function mount(string $publicToken): void
    {
        $this->event = Event::where('public_token', $publicToken)
            ->where('status', EventStatus::Published)
            ->firstOrFail();
    }

    #[Computed]
    public function jobs(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->event->volunteerJobs()
            ->with(['shifts' => fn ($q) => $q->withCount('signups')->orderBy('starts_at')])
            ->get();
    }

    public function signup(): void
    {
        $this->validate([
            'volunteerName' => ['required', 'string', 'max:255'],
            'volunteerEmail' => ['required', 'email', 'max:255'],
            'volunteerPhone' => ['nullable', 'string', 'max:20'],
            'selectedShiftIds' => ['required', 'array', 'min:1'],
            'selectedShiftIds.*' => [
                'integer',
                Rule::exists('shifts', 'id')->where(fn ($q) => $q->whereIn(
                    'volunteer_job_id',
                    $this->event->volunteerJobs()->select('id'),
                )),
            ],
        ]);

        $action = app(SignUpVolunteerForShifts::class);

        try {
            $result = $action->execute(
                name: $this->volunteerName,
                email: $this->volunteerEmail,
                event: $this->event,
                shiftIds: array_map('intval', $this->selectedShiftIds),
                phone: $this->volunteerPhone ?: null,
            );

            if ($result->hasNewSignups()) {
                $this->signupComplete = true;

                $skippedCount = count($result->skippedFull) + count($result->skippedDuplicate);
                if ($skippedCount > 0) {
                    $this->warningMessage = __('Some shifts were skipped because they were full or you were already signed up.');
                }
            } elseif (count($result->skippedDuplicate) === count($this->selectedShiftIds)) {
                $this->addError('selectedShiftIds', __('You are already signed up for all selected shifts.'));
            } elseif (count($result->skippedFull) === count($this->selectedShiftIds)) {
                $this->addError('selectedShiftIds', __('All selected shifts are full.'));
                unset($this->jobs);
            } else {
                $this->addError('selectedShiftIds', __('Selected shifts are either full or already registered.'));
                unset($this->jobs);
            }
        } catch (DomainException $e) {
            $this->addError('selectedShiftIds', $e->getMessage());
        }
    }
}
