<?php

namespace App\Livewire\Events;

use App\Actions\PromoteVolunteer;
use App\Enums\StaffRole;
use App\Exceptions\DomainException;
use App\Models\Event;
use App\Models\EventArrival;
use App\Models\Volunteer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Volunteer Detail')]
class VolunteerDetail extends Component
{
    public Event $event;

    public Volunteer $volunteer;

    public bool $showPromoteModal = false;

    public string $promoteRole = 'volunteer_admin';

    public function mount(int $eventId, int $volunteerId): void
    {
        $this->event = currentOrganization()->events()->findOrFail($eventId);

        Gate::authorize('view', $this->event);

        $this->volunteer = Volunteer::forEvent($eventId)->findOrFail($volunteerId);
    }

    #[Computed]
    public function shiftSignups(): Collection
    {
        return $this->volunteer->shiftSignups()
            ->whereHas('shift.volunteerJob', fn ($q) => $q->where('event_id', $this->event->id))
            ->with(['shift.volunteerJob', 'attendanceRecord'])
            ->get();
    }

    #[Computed]
    public function arrival(): ?EventArrival
    {
        return $this->volunteer->eventArrivals()
            ->where('event_id', $this->event->id)
            ->first();
    }

    #[Computed]
    public function canPromote(): bool
    {
        return Gate::allows('update', $this->event) && ! $this->volunteer->user_id;
    }

    #[Computed]
    public function isAlreadyPromoted(): bool
    {
        return (bool) $this->volunteer->user_id;
    }

    public function promoteVolunteer(): void
    {
        Gate::authorize('update', $this->event);

        $this->validate([
            'promoteRole' => ['required', 'string', 'in:organizer,volunteer_admin,entrance_staff'],
        ]);

        try {
            $action = app(PromoteVolunteer::class);
            $action->execute(
                volunteer: $this->volunteer,
                organization: $this->event->organization,
                role: StaffRole::from($this->promoteRole),
                promotedBy: auth()->user(),
            );

            $this->volunteer->refresh();
            $this->showPromoteModal = false;
            unset($this->canPromote, $this->isAlreadyPromoted);
            $this->dispatch('volunteer-promoted');
        } catch (DomainException $e) {
            $this->addError('promoteRole', $e->getMessage());
        }
    }
}
