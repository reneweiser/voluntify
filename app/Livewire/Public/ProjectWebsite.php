<?php

namespace App\Livewire\Public;

use App\Models\Project;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.public')]
#[Title('Project')]
class ProjectWebsite extends Component
{
    public Project $project;

    public function mount(string $publicToken): void
    {
        $this->project = Project::where('public_token', $publicToken)
            ->firstOrFail();
    }

    public function render(): mixed
    {
        return view('livewire.public.project-website', [
            'events' => $this->project->publishedEvents()->publiclyVisible()->withVolunteerCount()->get(),
        ]);
    }
}
