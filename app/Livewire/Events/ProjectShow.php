<?php

namespace App\Livewire\Events;

use App\Actions\DeleteProject;
use App\Actions\UpdateProject;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Project')]
class ProjectShow extends Component
{
    use WithFileUploads;

    public Project $project;

    public string $name = '';

    public string $description = '';

    public $titleImage;

    public string $senderName = '';

    public string $contactEmail = '';

    public bool $cancellationEnabled = false;

    public $cancellationCutoffHours = '';

    public bool $editing = false;

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

    public function deleteProject(): void
    {
        Gate::authorize('delete', $this->project);

        $action = app(DeleteProject::class);
        $action->execute($this->project);

        $this->redirectRoute('projects.index');
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
