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
use Livewire\WithFileUploads;

#[Title('Events')]
class EventList extends Component
{
    use WithFileUploads;

    public string $statusFilter = '';

    public string $eventName = '';

    public string $eventDescription = '';

    public string $eventLocation = '';

    public string $eventStartsAt = '';

    public string $eventEndsAt = '';

    public $eventTitleImage;

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
            'eventTitleImage' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $action = app(CreateEvent::class);

        $event = $action->execute(
            organization: app(Organization::class),
            name: $this->eventName,
            description: $this->eventDescription ?: null,
            location: $this->eventLocation ?: null,
            startsAt: Carbon::parse($this->eventStartsAt),
            endsAt: Carbon::parse($this->eventEndsAt),
            titleImage: $this->eventTitleImage,
        );

        $this->reset('eventName', 'eventDescription', 'eventLocation', 'eventStartsAt', 'eventEndsAt', 'eventTitleImage', 'showCreateModal');

        $this->redirectRoute('events.show', $event);
    }
}
