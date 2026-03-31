<?php

namespace App\Livewire\Scanner;

use App\Enums\StaffRole;
use App\Models\Event;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Scanner')]
class ScannerEventSelect extends Component
{
    public function mount(): void
    {
        $organization = currentOrganization();

        $hasAccess = $organization->users()
            ->where('user_id', auth()->id())
            ->wherePivotIn('role', [StaffRole::Organizer, StaffRole::EntranceStaff, StaffRole::VolunteerAdmin])
            ->exists();

        if (! $hasAccess) {
            abort(403);
        }
    }

    /** @return Collection<int, Event> */
    #[Computed]
    public function events(): Collection
    {
        return currentOrganization()->events()
            ->published()
            ->orderBy('starts_at')
            ->withVolunteerCount()
            ->withCount('eventArrivals')
            ->get();
    }
}
