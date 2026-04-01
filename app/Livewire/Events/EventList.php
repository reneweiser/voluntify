<?php

namespace App\Livewire\Events;

use App\Actions\CreateEvent;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Events')]
class EventList extends Component
{
    use WithFileUploads;

    public string $statusFilter = '';

    public ?int $eventProjectId = null;

    public string $eventName = '';

    public string $eventDescription = '';

    public string $eventLocation = '';

    public string $eventStartsAt = '';

    public string $eventEndsAt = '';

    public $eventTitleImage;

    public bool $showCreateModal = false;

    #[Computed]
    public function organization(): Organization
    {
        return currentOrganization();
    }

    #[Computed]
    public function events(): Collection
    {
        $user = auth()->user();

        $query = $this->organization->events()
            ->with('project.organization')
            ->withVolunteerCount()
            ->latest('starts_at');

        if (! $user->isOrgOrganizerFor($this->organization)) {
            $projectIds = $user->projects()
                ->where('projects.organization_id', $this->organization->id)
                ->pluck('projects.id');

            $query->whereIn('project_id', $projectIds);
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        $events = $query->get();

        // Preload project roles to avoid N+1 in policy checks
        $projectIds = $events->pluck('project_id')->unique()->values()->all();
        $user->preloadProjectRoles($projectIds);

        return $events;
    }

    #[Computed]
    public function projects(): Collection
    {
        return $this->organization->projects()->active()->orderBy('name')->get();
    }

    #[Computed]
    public function canCreateEvents(): bool
    {
        return Gate::allows('create', [Event::class, $this->organization]);
    }

    public function setStatusFilter(?string $status): void
    {
        if ($status === null) {
            $this->statusFilter = '';
        } else {
            $this->statusFilter = $this->statusFilter === $status ? '' : $status;
        }

        unset($this->events);
    }

    public function createEvent(): void
    {
        Gate::authorize('create', [Event::class, $this->organization]);

        $this->validate([
            'eventProjectId' => ['required', Rule::exists('projects', 'id')->where('organization_id', $this->organization->id)],
            'eventName' => ['required', 'string', 'max:255'],
            'eventDescription' => ['nullable', 'string'],
            'eventLocation' => ['nullable', 'string', 'max:255'],
            'eventStartsAt' => ['required', 'date'],
            'eventEndsAt' => ['required', 'date', 'after:eventStartsAt'],
            'eventTitleImage' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $project = $this->organization->projects()->findOrFail($this->eventProjectId);

        $action = app(CreateEvent::class);

        $event = $action->execute(
            organization: $this->organization,
            project: $project,
            name: $this->eventName,
            description: $this->eventDescription ?: null,
            location: $this->eventLocation ?: null,
            startsAt: Carbon::parse($this->eventStartsAt),
            endsAt: Carbon::parse($this->eventEndsAt),
            titleImage: $this->eventTitleImage,
        );

        $this->reset('eventProjectId', 'eventName', 'eventDescription', 'eventLocation', 'eventStartsAt', 'eventEndsAt', 'eventTitleImage', 'showCreateModal');

        $this->redirectRoute('events.show', $event);
    }
}
