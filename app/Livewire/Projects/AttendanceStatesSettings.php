<?php

namespace App\Livewire\Projects;

use App\Enums\AttendanceStatus;
use App\Models\Project;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Attendance States')]
class AttendanceStatesSettings extends Component
{
    #[Locked]
    public Project $project;

    /** @var array<int, array{key: string, label: string, active: bool, core: bool}> */
    public array $states = [];

    public function mount(int $projectId): void
    {
        $this->project = currentOrganization()->projects()->findOrFail($projectId);

        Gate::authorize('update', $this->project);

        $this->states = $this->project->all_attendance_states;
    }

    public function save(): void
    {
        $validKeys = array_column(AttendanceStatus::cases(), 'value');

        $rules = [];
        foreach ($this->states as $i => $state) {
            $rules["states.{$i}.label"] = ['required', 'string', 'max:50'];
            $rules["states.{$i}.key"] = ['required', 'string', 'in:'.implode(',', $validKeys)];
            $rules["states.{$i}.active"] = ['required', 'boolean'];

            if ($state['core'] ?? false) {
                $rules["states.{$i}.active"] = ['required', 'boolean', 'accepted'];
            }
        }

        $this->validate($rules);

        $this->project->update(['attendance_states' => $this->states]);
    }
}
