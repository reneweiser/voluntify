<?php

namespace App\Livewire\Events;

use App\Actions\CloneProject;
use App\Actions\CreateEvent;
use App\Actions\RequestProjectDeletion;
use App\Actions\RestoreProject;
use App\Actions\UpdateProject;
use App\Exceptions\DomainException;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Project')]
class ProjectShow extends Component
{
    use WithFileUploads;

    #[Locked]
    public Project $project;

    public string $name = '';

    public string $description = '';

    public $titleImage;

    public string $senderName = '';

    public string $contactEmail = '';

    public bool $cancellationEnabled = false;

    public $cancellationCutoffHours = '';

    public bool $editing = false;

    public string $deletePassword = '';

    public bool $showDeleteModal = false;

    public bool $showCloneModal = false;

    public $cloneDateOffset = '';

    public bool $showCreateEventModal = false;

    public string $newEventName = '';

    public string $newEventDescription = '';

    public string $newEventLocation = '';

    public string $newEventStartsAt = '';

    public string $newEventEndsAt = '';

    public $newEventTitleImage;

    public function mount(int $projectId): void
    {
        $this->project = currentOrganization()->projects()->findOrFail($projectId);

        Gate::authorize('view', $this->project);

        $this->fillForm();
    }

    #[Computed]
    public function organization(): Organization
    {
        return currentOrganization();
    }

    #[Computed]
    public function canManage(): bool
    {
        return Gate::allows('update', $this->project);
    }

    #[Computed]
    public function canCreateEvents(): bool
    {
        return Gate::allows('create', [Event::class, $this->organization]);
    }

    #[Computed]
    public function canManageMembers(): bool
    {
        return Gate::allows('manageMembers', $this->project);
    }

    #[Computed]
    public function memberEvents(): Collection
    {
        return $this->project->events()->orderBy('starts_at')->get();
    }

    #[Computed]
    public function publicUrl(): string
    {
        return route('projects.public', $this->project->public_token);
    }

    public function createEvent(): void
    {
        Gate::authorize('create', [Event::class, $this->organization]);

        $this->validate([
            'newEventName' => ['required', 'string', 'max:255'],
            'newEventDescription' => ['nullable', 'string'],
            'newEventLocation' => ['nullable', 'string', 'max:255'],
            'newEventStartsAt' => ['required', 'date'],
            'newEventEndsAt' => ['required', 'date', 'after:newEventStartsAt'],
            'newEventTitleImage' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $action = app(CreateEvent::class);

        $event = $action->execute(
            organization: $this->organization,
            project: $this->project,
            name: $this->newEventName,
            description: $this->newEventDescription ?: null,
            location: $this->newEventLocation ?: null,
            startsAt: Carbon::parse($this->newEventStartsAt),
            endsAt: Carbon::parse($this->newEventEndsAt),
            titleImage: $this->newEventTitleImage,
        );

        $this->reset('newEventName', 'newEventDescription', 'newEventLocation', 'newEventStartsAt', 'newEventEndsAt', 'newEventTitleImage', 'showCreateEventModal');

        $this->redirectRoute('events.show', $event);
    }

    public function startEditing(): void
    {
        Gate::authorize('update', $this->project);
        $this->editing = true;
    }

    public function cancelEditing(): void
    {
        $this->editing = false;
        $this->fillForm();
        $this->resetValidation();
    }

    public function saveProject(): void
    {
        Gate::authorize('update', $this->project);

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'titleImage' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'senderName' => ['nullable', 'string', 'max:255'],
            'contactEmail' => ['nullable', 'email', 'max:255'],
            'cancellationEnabled' => ['boolean'],
            'cancellationCutoffHours' => ['nullable', 'required_if:cancellationEnabled,true', 'integer', 'min:1', 'max:168'],
        ]);

        $action = app(UpdateProject::class);
        $this->project = $action->execute(
            project: $this->project,
            name: $this->name,
            description: $this->description ?: null,
            titleImage: $this->titleImage,
            senderName: $this->senderName ?: null,
            contactEmail: $this->contactEmail ?: null,
            cancellationEnabled: $this->cancellationEnabled,
            cancellationCutoffHours: $this->cancellationCutoffHours !== '' ? (int) $this->cancellationCutoffHours : null,
        );

        $this->titleImage = null;
        $this->editing = false;
        unset($this->memberEvents);
    }

    public function deleteImage(): void
    {
        Gate::authorize('update', $this->project);

        $action = app(UpdateProject::class);
        $this->project = $action->execute(
            project: $this->project,
            name: $this->project->name,
            description: $this->project->description,
            removeTitleImage: true,
        );
    }

    public function requestDeletion(): void
    {
        Gate::authorize('delete', $this->project);

        $this->validate([
            'deletePassword' => ['required', 'string'],
        ]);

        try {
            $action = app(RequestProjectDeletion::class);
            $this->project = $action->execute($this->project, $this->deletePassword);
            $this->showDeleteModal = false;
            $this->deletePassword = '';
        } catch (DomainException $e) {
            $this->addError('deletePassword', $e->getMessage());
        }
    }

    public function restoreProject(): void
    {
        Gate::authorize('delete', $this->project);

        $action = app(RestoreProject::class);
        $this->project = $action->execute($this->project);
    }

    public function confirmCloneProject(): void
    {
        Gate::authorize('update', $this->project);

        $this->validate([
            'cloneDateOffset' => ['nullable', 'integer', 'min:-3650', 'max:3650'],
        ]);

        $action = app(CloneProject::class);
        $offset = $this->cloneDateOffset !== '' ? (int) $this->cloneDateOffset : null;
        $clonedProject = $action->execute($this->project, $offset);

        $this->showCloneModal = false;
        $this->cloneDateOffset = '';

        $this->redirect(route('projects.show', $clonedProject), navigate: true);
    }

    private function fillForm(): void
    {
        $this->name = $this->project->name;
        $this->description = $this->project->description ?? '';
        $this->senderName = $this->project->sender_name ?? '';
        $this->contactEmail = $this->project->contact_email ?? '';
        $this->cancellationEnabled = $this->project->cancellation_enabled;
        $this->cancellationCutoffHours = $this->project->cancellation_cutoff_hours ?? '';
    }
}
