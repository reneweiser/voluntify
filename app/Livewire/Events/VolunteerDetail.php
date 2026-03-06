<?php

namespace App\Livewire\Events;

use App\Models\Event;
use App\Models\EventArrival;
use App\Models\Organization;
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

    public function mount(int $eventId, int $volunteerId): void
    {
        $this->event = app(Organization::class)->events()->findOrFail($eventId);

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
}
