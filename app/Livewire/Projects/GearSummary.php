<?php

namespace App\Livewire\Projects;

use App\Actions\ExportGearSummaryCsv;
use App\Actions\GenerateGearSummary;
use App\Models\Project;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Title('Gear-Übersicht')]
class GearSummary extends Component
{
    public Project $project;

    public function mount(int $projectId): void
    {
        $this->project = currentOrganization()->projects()->findOrFail($projectId);

        Gate::authorize('view', $this->project);
    }

    /**
     * @return array<int, array{id: int, name: string, type: string, requires_size: bool, total_assigned: int, picked_up: int, pending: int}>
     */
    #[Computed]
    public function summary(): array
    {
        return app(GenerateGearSummary::class)->execute($this->project);
    }

    #[Computed]
    public function totalAssigned(): int
    {
        return collect($this->summary)->sum('total_assigned');
    }

    #[Computed]
    public function totalPickedUp(): int
    {
        return collect($this->summary)->sum('picked_up');
    }

    #[Computed]
    public function totalPending(): int
    {
        return collect($this->summary)->sum('pending');
    }

    public function exportCsv(): StreamedResponse
    {
        Gate::authorize('update', $this->project);

        $rows = app(ExportGearSummaryCsv::class)->execute($this->project);

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($handle, $row, ';');
            }
            fclose($handle);
        }, "gear-summary-{$this->project->id}.csv", [
            'Content-Type' => 'text/csv',
        ]);
    }
}
