<?php

namespace App\Livewire\Scanner;

use App\Enums\StaffRole;
use App\Models\Event;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Scanner')]
#[Layout('layouts.scanner')]
class QrScanner extends Component
{
    public int $eventId;

    public ?Event $event = null;

    public function mount(int $eventId): void
    {
        $organization = currentOrganization();

        $hasAccess = $organization->users()
            ->where('user_id', auth()->id())
            ->wherePivotIn('role', [StaffRole::Organizer, StaffRole::EntranceStaff])
            ->exists();

        if (! $hasAccess) {
            abort(403);
        }

        $this->event = $organization->events()->findOrFail($eventId);
        $this->eventId = $eventId;
    }
}
