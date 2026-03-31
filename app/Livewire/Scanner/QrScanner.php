<?php

namespace App\Livewire\Scanner;

use App\Models\Event;
use Illuminate\Support\Facades\Gate;
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
        $this->event = $organization->events()->with('project.organization')->findOrFail($eventId);
        $this->eventId = $eventId;

        Gate::authorize('scan', $this->event);
    }
}
