<?php

namespace App\Livewire\Events;

use App\Actions\ArchiveEvent;
use App\Actions\CloneEvent;
use App\Actions\CloseRegistration;
use App\Actions\PublishEvent;
use App\Exceptions\DomainException;
use App\Models\Event;
use App\Models\Shift;
use App\Models\Volunteer;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Event Details')]
class EventShow extends Component
{
    public Event $event;

    public function mount(int $eventId): void
    {
        $this->event = currentOrganization()->events()->findOrFail($eventId);

        Gate::authorize('view', $this->event);
    }

    #[Computed]
    public function canManage(): bool
    {
        return Gate::allows('update', $this->event);
    }

    #[Computed]
    public function volunteerCount(): int
    {
        return Volunteer::forEvent($this->event->id)->count();
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
        if (! $this->event->status->isPublished()) {
            return null;
        }

        return route('events.public', $this->event->public_token);
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

    public function closeRegistration(): void
    {
        Gate::authorize('update', $this->event);

        try {
            $action = app(CloseRegistration::class);
            $this->event = $action->execute($this->event);
            $this->dispatch('event-registration-closed');
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
}
