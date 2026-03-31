<?php

namespace App\Livewire\Events;

use App\Actions\CreateProject;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Projects')]
class ProjectList extends Component
{
    use WithFileUploads;

    public string $projectName = '';

    public string $projectDescription = '';

    public $projectTitleImage;

    public bool $showCreateModal = false;

    #[Computed]
    public function organization(): Organization
    {
        return currentOrganization();
    }

    #[Computed]
    public function projects(): Collection
    {
        return $this->organization->projects()
            ->withCount('events')
            ->latest()
            ->get();
    }

    #[Computed]
    public function canCreateProjects(): bool
    {
        return Gate::allows('create', [Project::class, $this->organization]);
    }

    public function mount(): void
    {
        Gate::authorize('viewAny', [Project::class, $this->organization]);
    }

    public function createProject(): void
    {
        Gate::authorize('create', [Project::class, $this->organization]);

        $this->validate([
            'projectName' => ['required', 'string', 'max:255'],
            'projectDescription' => ['nullable', 'string'],
            'projectTitleImage' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $action = app(CreateProject::class);

        $project = $action->execute(
            organization: $this->organization,
            name: $this->projectName,
            description: $this->projectDescription ?: null,
            titleImage: $this->projectTitleImage,
        );

        $this->reset('projectName', 'projectDescription', 'projectTitleImage', 'showCreateModal');

        $this->redirectRoute('projects.show', $project);
    }
}
