<?php

namespace App\Livewire\Public;

use App\Actions\SignUpVolunteer;
use App\Enums\EventStatus;
use App\Exceptions\AlreadySignedUpException;
use App\Exceptions\ShiftFullException;
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

    public ?int $selectedShiftId = null;

    public bool $signupComplete = false;

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
            'selectedShiftId' => [
                'required',
                'integer',
                Rule::exists('shifts', 'id')->where(fn ($q) => $q->whereIn(
                    'volunteer_job_id',
                    $this->event->volunteerJobs()->select('id'),
                )),
            ],
        ]);

        $shift = \App\Models\Shift::whereHas(
            'volunteerJob',
            fn ($q) => $q->where('event_id', $this->event->id),
        )->findOrFail($this->selectedShiftId);

        $action = app(SignUpVolunteer::class);

        try {
            $action->execute(
                name: $this->volunteerName,
                email: $this->volunteerEmail,
                event: $this->event,
                shift: $shift,
            );

            $this->signupComplete = true;
        } catch (ShiftFullException) {
            $this->addError('selectedShiftId', __('This shift is now full. Please choose another.'));
            unset($this->jobs);
        } catch (AlreadySignedUpException) {
            $this->addError('volunteerEmail', __('You are already signed up for this shift.'));
        }
    }
}
