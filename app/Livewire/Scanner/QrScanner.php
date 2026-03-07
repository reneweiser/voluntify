<?php

namespace App\Livewire\Scanner;

use App\Enums\StaffRole;
use App\Models\Event;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Scanner')]
#[Layout('layouts.scanner')]
class QrScanner extends Component
{
    public ?int $selectedEventId = null;

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

    /** @return \Illuminate\Database\Eloquent\Collection<int, Event> */
    #[Computed]
    public function events(): \Illuminate\Database\Eloquent\Collection
    {
        return currentOrganization()->events()->get();
    }
}
