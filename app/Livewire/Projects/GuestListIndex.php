<?php

namespace App\Livewire\Projects;

use App\Actions\CreateGuestList;
use App\Actions\DeleteGuestList;
use App\Enums\ScannerType;
use App\Models\GuestList;
use App\Models\Project;
use App\Models\ProjectGearItem;
use App\Models\ProjectScanner;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Guest Lists')]
class GuestListIndex extends Component
{
    #[Locked]
    public int $projectId;

    public ?Project $project = null;

    public bool $showCreateModal = false;

    // Form fields
    public string $name = '';

    public ?int $scannerId = null;

    public array $gearItemIds = [];

    public function mount(int $projectId): void
    {
        $this->project = currentOrganization()->projects()->findOrFail($projectId);
        $this->projectId = $projectId;

        Gate::authorize('manageGuestLists', $this->project);
    }

    /** @return Collection<int, GuestList> */
    #[Computed]
    public function guestLists(): Collection
    {
        return GuestList::forProject($this->projectId)
            ->with('scanner', 'groups')
            ->withCount([
                'entries as total_entries',
                'entries as checked_in_entries' => fn ($q) => $q->whereNotNull('checked_in_at'),
            ])
            ->latest()
            ->get();
    }

    /** @return Collection<int, ProjectScanner> */
    #[Computed]
    public function entryStaffScanners(): Collection
    {
        return ProjectScanner::where('project_id', $this->projectId)
            ->where('type', ScannerType::EntryStaff)
            ->get();
    }

    /** @return Collection<int, ProjectGearItem> */
    #[Computed]
    public function projectGearItems(): Collection
    {
        return ProjectGearItem::where('project_id', $this->projectId)
            ->orderBy('sort_order')
            ->get();
    }

    public function createGuestList(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'scannerId' => ['required', Rule::exists('project_scanners', 'id')->where('project_id', $this->projectId)],
            'gearItemIds' => ['nullable', 'array'],
            'gearItemIds.*' => ['integer', Rule::exists('project_gear_items', 'id')->where('project_id', $this->projectId)],
        ]);

        $action = new CreateGuestList;
        $guestList = $action->execute($this->project, [
            'name' => $this->name,
            'scanner_id' => $this->scannerId,
            'gear_items' => ! empty($this->gearItemIds) ? $this->gearItemIds : null,
        ]);

        $this->resetForm();
        $this->showCreateModal = false;

        $this->redirect(route('guest-lists.show', [
            'projectId' => $this->projectId,
            'guestListId' => $guestList->id,
        ]), navigate: true);
    }

    public function deleteGuestList(int $guestListId): void
    {
        $guestList = GuestList::where('project_id', $this->projectId)->findOrFail($guestListId);

        Gate::authorize('manageGuestLists', $this->project);

        $action = new DeleteGuestList;
        $action->execute($guestList);

        session()->flash('message', 'Guest list deleted.');
        unset($this->guestLists);
    }

    private function resetForm(): void
    {
        $this->name = '';
        $this->scannerId = null;
        $this->gearItemIds = [];
    }
}
