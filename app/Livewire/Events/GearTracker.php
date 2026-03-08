<?php

namespace App\Livewire\Events;

use App\Actions\ToggleGearPickup;
use App\Models\Event;
use App\Models\Volunteer;
use App\Models\VolunteerGear;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Gear Pickup')]
class GearTracker extends Component
{
    public Event $event;

    public string $search = '';

    public function mount(int $eventId): void
    {
        $this->event = currentOrganization()->events()->findOrFail($eventId);

        Gate::authorize('trackGearPickup', $this->event);
    }

    #[Computed]
    public function gearItems(): Collection
    {
        return $this->event->gearItems()->get();
    }

    #[Computed]
    public function volunteers(): Collection
    {
        return Volunteer::forEvent($this->event->id)
            ->when($this->search, fn ($q) => $q->search($this->search))
            ->with([
                'volunteerGear' => fn ($q) => $q->whereIn(
                    'event_gear_item_id',
                    $this->event->gearItems()->select('id'),
                ),
                'volunteerGear.gearItem',
            ])
            ->orderBy('name')
            ->get();
    }

    public function togglePickup(int $gearId): void
    {
        Gate::authorize('trackGearPickup', $this->event);

        $gear = VolunteerGear::whereHas('gearItem', fn ($q) => $q->where('event_id', $this->event->id))
            ->findOrFail($gearId);

        app(ToggleGearPickup::class)->execute($gear, auth()->user());

        unset($this->volunteers);
    }

    public function assignAndPickup(int $itemId, int $volunteerId): void
    {
        Gate::authorize('trackGearPickup', $this->event);

        $item = $this->event->gearItems()->findOrFail($itemId);

        $gear = VolunteerGear::firstOrCreate(
            [
                'event_gear_item_id' => $item->id,
                'volunteer_id' => $volunteerId,
            ],
        );

        app(ToggleGearPickup::class)->execute($gear, auth()->user());

        unset($this->volunteers);
    }
}
