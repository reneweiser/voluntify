<?php

namespace App\Livewire\Scanner;

use App\Actions\RecordArrival;
use App\Enums\ArrivalMethod;
use App\Enums\StaffRole;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\Volunteer;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Manual Lookup')]
#[Layout('layouts.scanner')]
class ManualLookup extends Component
{
    public ?int $selectedEventId = null;

    public string $search = '';

    public function mount(): void
    {
        $organization = currentOrganization();

        $hasAccess = $organization->users()
            ->where('user_id', auth()->id())
            ->wherePivotIn('role', [StaffRole::Organizer, StaffRole::EntranceStaff])
            ->exists();

        if (! $hasAccess) {
            abort(403);
        }
    }

    /** @return Collection<int, Event> */
    #[Computed]
    public function events(): Collection
    {
        return currentOrganization()->events()->get();
    }

    /** @return Collection<int, Volunteer> */
    #[Computed]
    public function volunteers(): Collection
    {
        if (! $this->selectedEventId || strlen($this->search) < 2) {
            return new Collection;
        }

        return Volunteer::query()
            ->forEvent($this->selectedEventId)
            ->where('name', 'like', '%'.$this->search.'%')
            ->with([
                'shiftSignups.shift.volunteerJob',
                'eventArrivals' => fn ($q) => $q->where('event_id', $this->selectedEventId),
                'tickets' => fn ($q) => $q->where('event_id', $this->selectedEventId),
            ])
            ->get();
    }

    public function confirmArrival(int $volunteerId): void
    {
        $ticket = Ticket::where('volunteer_id', $volunteerId)
            ->where('event_id', $this->selectedEventId)
            ->firstOrFail();

        $arrival = app(RecordArrival::class)->execute(
            ticket: $ticket,
            scannedBy: auth()->user(),
            method: ArrivalMethod::ManualLookup,
        );

        unset($this->volunteers);

        $this->dispatch('arrival-confirmed', volunteerId: $volunteerId, flagged: $arrival->flagged);
    }

    public function updatedSelectedEventId(): void
    {
        $this->search = '';
        unset($this->volunteers);
    }

    public function updatedSearch(): void
    {
        unset($this->volunteers);
    }
}
