<?php

namespace App\Livewire\Events;

use App\Actions\ArchiveEvent;
use App\Actions\AssignEventsToGroup;
use App\Actions\CloneEvent;
use App\Actions\DeleteEventImage;
use App\Actions\PublishEvent;
use App\Actions\RemoveEventFromGroup;
use App\Actions\UpdateEvent;
use App\Enums\EventStatus;
use App\Exceptions\DomainException;
use App\Models\Event;
use App\Models\EventGroup;
use App\Models\Shift;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Event Details')]
class EventShow extends Component
{
    use WithFileUploads;

    public Event $event;

    public string $name = '';

    public string $description = '';

    public string $location = '';

    public string $startsAt = '';

    public string $endsAt = '';

    public $titleImage;

    public $cancellationCutoffHours = '';

    public bool $editing = false;

    public string $selectedGroupId = '';

    public function mount(int $eventId): void
    {
        $this->event = currentOrganization()->events()->findOrFail($eventId);

        Gate::authorize('view', $this->event);

        $this->fillForm();
    }

    #[Computed]
    public function canManage(): bool
    {
        return Gate::allows('update', $this->event);
    }

    #[Computed]
    public function volunteerCount(): int
    {
        return $this->event->volunteers()->count();
    }

    #[Computed]
    public function jobCount(): int
    {
        return $this->event->volunteerJobs()->count();
    }

    #[Computed]
    public function shiftCount(): int
    {
        return Shift::whereIn(
            'volunteer_job_id',
            $this->event->volunteerJobs()->select('id'),
        )->count();
    }

    #[Computed]
    public function publicUrl(): ?string
    {
        if ($this->event->status !== EventStatus::Published) {
            return null;
        }

        return route('events.public', $this->event->public_token);
    }

    #[Computed]
    public function availableGroups(): \Illuminate\Database\Eloquent\Collection
    {
        return currentOrganization()->eventGroups()->orderBy('name')->get();
    }

    public function updateGroup(): void
    {
        Gate::authorize('update', $this->event);

        if ($this->selectedGroupId === '') {
            $action = app(RemoveEventFromGroup::class);
            $action->execute($this->event);
        } else {
            $group = EventGroup::findOrFail((int) $this->selectedGroupId);
            $action = app(AssignEventsToGroup::class);
            $action->execute($group, [$this->event->id]);
        }

        $this->event->refresh();
    }

    public function startEditing(): void
    {
        Gate::authorize('update', $this->event);

        $this->editing = true;
    }

    public function cancelEditing(): void
    {
        $this->editing = false;
        $this->fillForm();
        $this->resetValidation();
    }

    public function saveEvent(): void
    {
        Gate::authorize('update', $this->event);

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'startsAt' => ['required', 'date'],
            'endsAt' => ['required', 'date', 'after:startsAt'],
            'titleImage' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'cancellationCutoffHours' => ['nullable', 'integer', 'min:1', 'max:168'],
        ]);

        try {
            $action = app(UpdateEvent::class);
            $this->event = $action->execute(
                event: $this->event,
                name: $this->name,
                description: $this->description ?: null,
                location: $this->location ?: null,
                startsAt: Carbon::parse($this->startsAt),
                endsAt: Carbon::parse($this->endsAt),
                titleImage: $this->titleImage,
                cancellationCutoffHours: $this->cancellationCutoffHours !== '' ? (int) $this->cancellationCutoffHours : null,
            );

            $this->titleImage = null;
            $this->editing = false;
            $this->dispatch('event-updated');
        } catch (DomainException $e) {
            $this->addError('name', $e->getMessage());
        }
    }

    public function deleteImage(): void
    {
        Gate::authorize('update', $this->event);

        $action = app(DeleteEventImage::class);
        $this->event = $action->execute($this->event);
    }

    public function publishEvent(): void
    {
        Gate::authorize('publish', $this->event);

        try {
            $action = app(PublishEvent::class);
            $this->event = $action->execute($this->event);
            $this->dispatch('event-published');
        } catch (DomainException $e) {
            $this->addError('status', $e->getMessage());
        }
    }

    public function archiveEvent(): void
    {
        Gate::authorize('archive', $this->event);

        try {
            $action = app(ArchiveEvent::class);
            $this->event = $action->execute($this->event);
            $this->dispatch('event-archived');
        } catch (DomainException $e) {
            $this->addError('status', $e->getMessage());
        }
    }

    public function cloneEvent(): void
    {
        Gate::authorize('create', [Event::class, $this->event->organization]);

        $action = app(CloneEvent::class);
        $clonedEvent = $action->execute($this->event);

        $this->redirect(route('events.show', $clonedEvent), navigate: true);
    }

    private function fillForm(): void
    {
        $this->name = $this->event->name;
        $this->description = $this->event->description ?? '';
        $this->location = $this->event->location ?? '';
        $this->startsAt = $this->event->starts_at->format('Y-m-d\TH:i');
        $this->endsAt = $this->event->ends_at->format('Y-m-d\TH:i');
        $this->cancellationCutoffHours = $this->event->cancellation_cutoff_hours ?? '';
        $this->selectedGroupId = $this->event->event_group_id ? (string) $this->event->event_group_id : '';
    }
}
