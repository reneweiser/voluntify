<?php

namespace App\Livewire\Projects;

use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Projekt-Website')]
class ProjectWebsiteEditor extends Component
{
    #[Locked]
    public Project $project;

    public string $websiteDescription = '';

    public string $websiteContactInfo = '';

    public bool $websitePublished = false;

    public function mount(int $projectId): void
    {
        $this->project = currentOrganization()->projects()->findOrFail($projectId);

        Gate::authorize('update', $this->project);

        $this->fillForm();
    }

    #[Computed]
    public function publicUrl(): string
    {
        return route('projects.public', $this->project->public_token);
    }

    #[Computed]
    public function previewEvents(): Collection
    {
        return $this->project->events()
            ->published()
            ->publiclyVisible()
            ->orderBy('starts_at')
            ->get();
    }

    public function saveWebsite(): void
    {
        Gate::authorize('update', $this->project);

        $this->validate([
            'websiteDescription' => ['nullable', 'string', 'max:10000'],
            'websiteContactInfo' => ['nullable', 'string', 'max:500'],
            'websitePublished' => ['boolean'],
        ]);

        $this->project->update([
            'website_description' => $this->websiteDescription ?: null,
            'website_contact_info' => $this->websiteContactInfo ?: null,
            'website_published' => $this->websitePublished,
        ]);

        $this->project->refresh();

        $this->dispatch('website-saved');
    }

    public function togglePublished(): void
    {
        Gate::authorize('update', $this->project);

        $this->websitePublished = ! $this->websitePublished;
        $this->saveWebsite();
    }

    private function fillForm(): void
    {
        $this->websiteDescription = $this->project->website_description ?? '';
        $this->websiteContactInfo = $this->project->website_contact_info ?? '';
        $this->websitePublished = (bool) $this->project->website_published;
    }
}
