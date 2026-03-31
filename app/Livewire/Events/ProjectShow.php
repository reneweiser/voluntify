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
        ]);

        $action = app(UpdateProject::class);
        $this->project = $action->execute(
            project: $this->project,
            name: $this->name,
            description: $this->description ?: null,
            titleImage: $this->titleImage,
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
    }
}
