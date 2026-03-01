<?php

namespace App\Livewire\Events;

use App\Actions\CreateEvent;
use App\Models\Event;
use App\Models\Organization;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Events')]
class EventList extends Component
{
    public string $statusFilter = '';

    public string $eventName = '';

    public string $eventDescription = '';

    public string $eventLocation = '';

    public string $eventStartsAt = '';

    public string $eventEndsAt = '';

    public bool $showCreateModal = false;

    public function mount(): void
    {
        if (! app()->bound(Organization::class)) {
            $this->redirect(route('dashboard'));
        }
    }

    #[Computed]
    public function events(): \Illuminate\Database\Eloquent\Collection
    {
        $query = app(Organization::class)->events()
            ->withCount('volunteers')
            ->latest('starts_at');

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        return $query->get();
    }

    #[Computed]
    public function canCreateEvents(): bool
    {
        return Gate::allows('create', [Event::class, app(Organization::class)]);
    }

    public function setStatusFilter(string $status): void
    {
        $this->statusFilter = $this->statusFilter === $status ? '' : $status;

        unset($this->events);
    }

    public function createEvent(): void
    {
        Gate::authorize('create', [Event::class, app(Organization::class)]);

        $this->validate([
            'eventName' => ['required', 'string', 'max:255'],
            'eventDescription' => ['nullable', 'string'],
            'eventLocation' => ['nullable', 'string', 'max:255'],
            'eventStartsAt' => ['required', 'date'],
            'eventEndsAt' => ['required', 'date', 'after:eventStartsAt'],
        ]);

        $action = new CreateEvent(app(Organization::class));

        $event = $action->execute(
            name: $this->eventName,
            description: $this->eventDescription ?: null,
            location: $this->eventLocation ?: null,
            startsAt: Carbon::parse($this->eventStartsAt),
            endsAt: Carbon::parse($this->eventEndsAt),
        );

        $this->reset('eventName', 'eventDescription', 'eventLocation', 'eventStartsAt', 'eventEndsAt', 'showCreateModal');

        $this->redirectRoute('events.show', $event);
    }
}
