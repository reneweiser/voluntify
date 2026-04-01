<?php

namespace App\Livewire\Events;

use App\Actions\AssignEventsToProject;
use App\Actions\DeleteEventImage;
use App\Actions\UpdateEvent;
use App\Enums\EventVisibility;
use App\Exceptions\DomainException;
use App\Models\Event;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Event Settings')]
class EventSettings extends Component
{
    use WithFileUploads;

    public Event $event;

    public string $name = '';

    public string $description = '';

    public string $location = '';

    public string $startsAt = '';

    public string $endsAt = '';

    public $titleImage;

    public $attendanceGraceMinutes = '';

    public string $notificationEmail = '';

    public string $visibility = 'public';

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
            $action->execute($project, [$this->event->id]);
        }

        $this->event->refresh();
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
            'attendanceGraceMinutes' => ['nullable', 'integer', 'min:0', 'max:120'],
            'visibility' => ['required', Rule::in(array_column(EventVisibility::cases(), 'value'))],
            'notificationEmail' => ['nullable', 'email', 'max:255'],
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
                attendanceGraceMinutes: $this->attendanceGraceMinutes !== '' ? (int) $this->attendanceGraceMinutes : null,
                visibility: EventVisibility::from($this->visibility),
                notificationEmail: $this->notificationEmail ?: null,
            );

            $this->titleImage = null;

            $this->redirect(route('events.show', $this->event), navigate: true);
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

    private function fillForm(): void
    {
        $this->name = $this->event->name;
        $this->description = $this->event->description ?? '';
        $this->location = $this->event->location ?? '';
        $this->startsAt = $this->event->starts_at->format('Y-m-d\TH:i');
        $this->endsAt = $this->event->ends_at->format('Y-m-d\TH:i');
        $this->attendanceGraceMinutes = $this->event->attendance_grace_minutes ?? '';
        $this->selectedProjectId = $this->event->project_id ? (string) $this->event->project_id : '';
        $this->visibility = $this->event->visibility?->value ?? 'public';
        $this->notificationEmail = $this->event->notification_email ?? '';
    }
}
