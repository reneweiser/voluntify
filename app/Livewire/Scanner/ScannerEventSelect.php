<?php

namespace App\Livewire\Scanner;

use App\Models\Event;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Scanner')]
class ScannerEventSelect extends Component
{
    public function mount(): void
    {
        if (! auth()->user()->hasAccessToOrganization(currentOrganization())) {
            abort(403);
        }
    }

    /** @return Collection<int, Event> */
    #[Computed]
    public function events(): Collection
    {
        $user = auth()->user();
        $org = currentOrganization();

        $query = $org->events()
            ->published()
            ->with('project.organization')
            ->orderBy('starts_at')
            ->withVolunteerCount()
            ->withCount('eventArrivals');

        if (! $user->isOrgOrganizerFor($org)) {
            $projectIds = $user->projects()
                ->where('projects.organization_id', $org->id)
                ->pluck('projects.id');

            $query->whereIn('project_id', $projectIds);
        }

        $events = $query->get();

        // Preload project roles to avoid N+1 in policy checks
        $user = auth()->user();
        $projectIds = $events->pluck('project_id')->unique()->values()->all();
        $user->preloadProjectRoles($projectIds);

        return $events;
    }
}
