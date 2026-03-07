<?php

namespace App\Livewire\Events;

use App\Models\Event;
use App\Models\Volunteer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Volunteers')]
class VolunteerList extends Component
{
    use WithPagination;

    public Event $event;

    public string $search = '';

    public function mount(int $eventId): void
    {
        $this->event = currentOrganization()->events()->findOrFail($eventId);

        Gate::authorize('view', $this->event);
    }

    #[Computed]
    public function volunteers(): LengthAwarePaginator
    {
        return Volunteer::forEvent($this->event->id)
            ->when($this->search, fn ($q) => $q->search($this->search))
            ->with([
                'shiftSignups' => fn ($q) => $q->whereHas('shift.volunteerJob', fn ($sq) => $sq->where('event_id', $this->event->id)),
                'shiftSignups.shift.volunteerJob',
                'shiftSignups.attendanceRecord',
                'eventArrivals' => fn ($q) => $q->where('event_id', $this->event->id),
            ])
            ->orderBy('name')
            ->paginate(25);
    }

    public function updated(string $property): void
    {
        if ($property === 'search') {
            $this->resetPage();
            unset($this->volunteers);
        }
    }
}
