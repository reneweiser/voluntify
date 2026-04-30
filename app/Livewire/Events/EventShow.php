<?php

namespace App\Livewire\Events;

use App\Actions\ArchiveEvent;
use App\Actions\CloneEvent;
use App\Actions\CloseRegistration;
use App\Actions\PublishEvent;
use App\Actions\RequestEventDeletion;
use App\Actions\RestoreEvent;
use App\Actions\RevertEventToDraft;
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

    public string $republishNote = '';

    public bool $showRepublishModal = false;

    public string $deletePassword = '';

    public bool $showDeleteModal = false;

    public bool $showCloneModal = false;

    public $cloneDateOffset = '';

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

    /**
     * @return array{is_visible: bool, is_open: bool, threshold_percent: ?int, filled_spots: int, total_spots: int, progress_percent: int, unlocked_at: ?string}
     */
    #[Computed]
    public function priorityGateSummary(): array
    {
        $event = $this->event->fresh();
        $filledSpots = $event->priorityFilledSpots();
        $totalSpots = $event->priorityCapacityTotal();

        return [
            'is_visible' => $event->priority_unlock_threshold_percent !== null || $event->priority_gate_unlocked_at !== null || $totalSpots > 0,
            'is_open' => $event->isPriorityGateOpen(),
            'threshold_percent' => $event->priority_unlock_threshold_percent,
            'filled_spots' => $filledSpots,
            'total_spots' => $totalSpots,
            'progress_percent' => $totalSpots === 0 ? 100 : min(100, (int) round(($filledSpots / $totalSpots) * 100)),
            'unlocked_at' => $event->priority_gate_unlocked_at?->format('d.m.Y H:i'),
        ];
    }

    public function publishEvent(): void
    {
        Gate::authorize('publish', $this->event);

        if ($this->event->was_previously_published) {
            $this->showRepublishModal = true;

            return;
        }

        try {
            $action = app(PublishEvent::class);
            $this->event = $action->execute($this->event, causer: auth()->user());
            $this->dispatch('event-published');
        } catch (DomainException $e) {
            $this->addError('status', $e->getMessage());
        }
    }

    public function confirmRepublish(): void
    {
        Gate::authorize('publish', $this->event);

        try {
            $action = app(PublishEvent::class);
            $this->event = $action->execute(
                $this->event,
                $this->republishNote ?: null,
                causer: auth()->user(),
            );
            $this->showRepublishModal = false;
            $this->republishNote = '';
            $this->dispatch('event-published');
        } catch (DomainException $e) {
            $this->addError('status', $e->getMessage());
        }
    }

    public function revertToDraft(): void
    {
        Gate::authorize('update', $this->event);

        try {
            $action = app(RevertEventToDraft::class);
            $this->event = $action->execute($this->event, auth()->user());
            $this->dispatch('event-reverted-to-draft');
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
            $this->event = $action->execute($this->event, auth()->user());
            $this->dispatch('event-archived');
        } catch (DomainException $e) {
            $this->addError('status', $e->getMessage());
        }
    }

    public function openCloneModal(): void
    {
        $this->showCloneModal = true;
    }

    public function confirmClone(): void
    {
        Gate::authorize('create', [Event::class, $this->event->organization]);

        $this->validate([
            'cloneDateOffset' => ['nullable', 'integer', 'min:-3650', 'max:3650'],
        ]);

        $action = app(CloneEvent::class);
        $offset = $this->cloneDateOffset !== '' ? (int) $this->cloneDateOffset : null;
        $clonedEvent = $action->execute($this->event, causer: auth()->user(), dateOffsetDays: $offset);

        $this->showCloneModal = false;
        $this->cloneDateOffset = '';

        $this->redirect(route('events.show', $clonedEvent), navigate: true);
    }

    public function requestDeletion(): void
    {
        Gate::authorize('delete', $this->event);

        $this->validate([
            'deletePassword' => ['required', 'string'],
        ]);

        try {
            $action = app(RequestEventDeletion::class);
            $this->event = $action->execute($this->event, $this->deletePassword, auth()->user());
            $this->showDeleteModal = false;
            $this->deletePassword = '';
        } catch (DomainException $e) {
            $this->addError('deletePassword', $e->getMessage());
        }
    }

    public function restoreEvent(): void
    {
        Gate::authorize('delete', $this->event);

        $action = app(RestoreEvent::class);
        $this->event = $action->execute($this->event);
    }
}
