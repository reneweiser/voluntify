<?php

namespace App\Livewire\Events;

use App\Actions\AssignEventsToProject;
use App\Actions\DeleteEventImage;
use App\Actions\UpdateEvent;
use App\Enums\EventVisibility;
use App\Exceptions\DomainException;
use App\Livewire\Forms\EventSettingsForm;
use App\Models\Event;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Event Settings')]
class EventSettings extends Component
{
    use WithFileUploads;

    #[Locked]
    public Event $event;

    public EventSettingsForm $form;

    public string $selectedProjectId = '';

    public function mount(int $eventId): void
    {
        $this->event = currentOrganization()->events()->findOrFail($eventId);

        Gate::authorize('update', $this->event);

        $this->fillForm();
    }

    #[Computed]
    public function canManage(): bool
    {
        return Gate::allows('update', $this->event);
    }

    #[Computed]
    public function availableProjects(): Collection
    {
        return currentOrganization()->projects()->orderBy('name')->get();
    }

    public function updateProject(): void
    {
        Gate::authorize('update', $this->event);

        if ($this->selectedProjectId !== '') {
            $project = currentOrganization()->projects()->findOrFail((int) $this->selectedProjectId);
            $action = app(AssignEventsToProject::class);
            $action->execute($project, [$this->event->id], auth()->user());
        }

        $this->event->refresh();
    }

    public function saveEvent(): void
    {
        Gate::authorize('update', $this->event);

        $this->form->validate();

        try {
            $action = app(UpdateEvent::class);
            $this->event = $action->execute(
                event: $this->event,
                name: $this->form->name,
                description: $this->form->description ?: null,
                location: $this->form->location ?: null,
                startsAt: Carbon::parse($this->form->startsAt),
                endsAt: Carbon::parse($this->form->endsAt),
                titleImage: $this->form->titleImage,
                attendanceGraceMinutes: $this->form->attendanceGraceMinutes !== '' ? (int) $this->form->attendanceGraceMinutes : null,
                visibility: EventVisibility::from($this->form->visibility),
                notificationEmail: $this->form->notificationEmail ?: null,
                causer: auth()->user(),
            );

            $this->form->titleImage = null;

            $this->redirect(route('events.show', $this->event), navigate: true);
        } catch (DomainException $e) {
            $this->addError('form.name', $e->getMessage());
        }
    }

    public function deleteImage(): void
    {
        Gate::authorize('update', $this->event);

        $action = app(DeleteEventImage::class);
        $this->event = $action->execute($this->event, auth()->user());
    }

    private function fillForm(): void
    {
        $this->form->fillFromEvent([
            'name' => $this->event->name,
            'description' => $this->event->description ?? '',
            'location' => $this->event->location ?? '',
            'startsAt' => $this->event->starts_at->format('Y-m-d\TH:i'),
            'endsAt' => $this->event->ends_at->format('Y-m-d\TH:i'),
            'attendanceGraceMinutes' => $this->event->attendance_grace_minutes ?? '',
            'visibility' => $this->event->visibility?->value ?? 'public',
            'notificationEmail' => $this->event->notification_email ?? '',
        ]);
        $this->selectedProjectId = $this->event->project_id ? (string) $this->event->project_id : '';
    }
}
