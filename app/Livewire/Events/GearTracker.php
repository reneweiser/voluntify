<?php

namespace App\Livewire\Events;

use App\Actions\RecordGearPickup;
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
        return $this->event->project->gearItems()->get();
    }

    #[Computed]
    public function volunteers(): Collection
    {
        return Volunteer::forEvent($this->event->id)
            ->when($this->search, fn ($q) => $q->search($this->search))
            ->with([
                'volunteerGear' => fn ($q) => $q->whereIn(
                    'project_gear_item_id',
                    $this->event->project->gearItems()->select('id'),
                ),
                'volunteerGear.gearItem',
                'volunteerGear.pickups',
            ])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    public function togglePickup(int $gearId): void
    {
        Gate::authorize('trackGearPickup', $this->event);

        $gear = VolunteerGear::whereHas('gearItem', fn ($q) => $q->where('project_id', $this->event->project_id))
            ->findOrFail($gearId);

        if ($gear->isPickedUp()) {
            $gear->pickups()->delete();
        } else {
            app(RecordGearPickup::class)->execute($gear, auth()->user());
        }

        unset($this->volunteers);
    }

    public function assignAndPickup(int $itemId, int $volunteerId): void
    {
        Gate::authorize('trackGearPickup', $this->event);

        Volunteer::forEvent($this->event->id)->where('id', $volunteerId)->firstOrFail();

        $item = $this->event->project->gearItems()->findOrFail($itemId);

        $gear = VolunteerGear::firstOrCreate(
            [
                'project_gear_item_id' => $item->id,
                'volunteer_id' => $volunteerId,
            ],
        );

        app(RecordGearPickup::class)->execute($gear, auth()->user());

        unset($this->volunteers);
    }
}
