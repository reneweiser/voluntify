<?php

namespace App\Livewire\Events;

use App\Actions\CloneProject;
use App\Actions\CreateEvent;
use App\Actions\RequestProjectDeletion;
use App\Actions\RestoreProject;
use App\Actions\UpdateProject;
use App\Exceptions\DomainException;
use App\Livewire\Forms\CreateEventInProjectForm;
use App\Livewire\Forms\ProjectDetailsForm;
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

    public ProjectDetailsForm $projectForm;

    public CreateEventInProjectForm $eventForm;

    public bool $editing = false;

    public string $deletePassword = '';

    public bool $showDeleteModal = false;

    public bool $showCloneModal = false;

    public $cloneDateOffset = '';

    public bool $showCreateEventModal = false;

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

        $this->eventForm->validate();

        $action = app(CreateEvent::class);

        $event = $action->execute(
            organization: $this->organization,
            project: $this->project,
            name: $this->eventForm->name,
            description: $this->eventForm->description ?: null,
            location: $this->eventForm->location ?: null,
            startsAt: Carbon::parse($this->eventForm->startsAt),
            endsAt: Carbon::parse($this->eventForm->endsAt),
            titleImage: $this->eventForm->titleImage,
        );

        $this->eventForm->reset();
        $this->showCreateEventModal = false;

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
        $this->projectForm->resetValidation();
    }

    public function saveProject(): void
    {
        Gate::authorize('update', $this->project);

        $this->projectForm->validate();

        $action = app(UpdateProject::class);
        $this->project = $action->execute(
            project: $this->project,
            name: $this->projectForm->name,
            description: $this->projectForm->description ?: null,
            titleImage: $this->projectForm->titleImage,
            senderName: $this->projectForm->senderName ?: null,
            contactEmail: $this->projectForm->contactEmail ?: null,
            cancellationEnabled: $this->projectForm->cancellationEnabled,
            cancellationCutoffHours: $this->projectForm->cancellationCutoffHours !== '' ? (int) $this->projectForm->cancellationCutoffHours : null,
        );

        $this->projectForm->titleImage = null;
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
        $this->projectForm->fillFromProject([
            'name' => $this->project->name,
            'description' => $this->project->description ?? '',
            'senderName' => $this->project->sender_name ?? '',
            'contactEmail' => $this->project->contact_email ?? '',
            'cancellationEnabled' => $this->project->cancellation_enabled,
            'cancellationCutoffHours' => $this->project->cancellation_cutoff_hours ?? '',
        ]);
    }
}
