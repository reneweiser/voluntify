<?php

namespace App\Livewire\Projects;

use App\Actions\CreateProjectScanner;
use App\Actions\DeleteProjectScanner;
use App\Actions\SendScannerLinks;
use App\Actions\UpdateProjectScanner;
use App\Models\Event;
use App\Models\Project;
use App\Models\ProjectGearItem;
use App\Models\ProjectScanner;
use App\Models\ProjectScannerAssignee;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Scanner Management')]
class ScannerManagement extends Component
{
    #[Locked]
    public int $projectId;

    public ?Project $project = null;

    public bool $showCreateModal = false;

    public bool $showDeleteConfirm = false;

    public ?int $deletingScannerId = null;

    // Form fields
    public string $name = '';

    public string $type = 'entry_staff';

    public array $modes = ['checkin'];

    public ?int $eventId = null;

    public array $gearItemIds = [];

    public string $hintText = '';

    public string $startsAt = '';

    public string $endsAt = '';

    public ?int $editingScannerId = null;

    public function mount(int $projectId): void
    {
        $this->project = currentOrganization()->projects()->findOrFail($projectId);
        $this->projectId = $projectId;

        Gate::authorize('manageScanners', $this->project);
    }

    /** @return Collection<int, ProjectScanner> */
    #[Computed]
    public function scanners(): Collection
    {
        return ProjectScanner::where('project_id', $this->projectId)
            ->with('assignees')
            ->orderBy('starts_at')
            ->get();
    }

    /** @return Collection<int, Event> */
    #[Computed]
    public function events(): Collection
    {
        return Event::where('project_id', $this->projectId)->orderBy('name')->get();
    }

    /** @return Collection<int, ProjectGearItem> */
    #[Computed]
    public function gearItems(): Collection
    {
        return ProjectGearItem::where('project_id', $this->projectId)->orderBy('sort_order')->get();
    }

    public function createScanner(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:entry_staff,volunteer_admin'],
            'modes' => ['required', 'array', 'min:1'],
            'modes.*' => ['string', 'in:checkin,gear_pickup'],
            'eventId' => ['nullable', Rule::exists('events', 'id')->where('project_id', $this->projectId)],
            'startsAt' => ['required', 'date'],
            'endsAt' => ['required', 'date', 'after:startsAt'],
        ]);

        $action = new CreateProjectScanner;
        $scanner = $action->execute($this->project, [
            'name' => $this->name,
            'type' => $this->type,
            'modes' => $this->modes,
            'event_id' => $this->eventId,
            'gear_item_ids' => ! empty($this->gearItemIds) ? $this->gearItemIds : null,
            'hint_text' => $this->hintText ?: null,
            'starts_at' => $this->startsAt,
            'ends_at' => $this->endsAt,
        ]);

        session()->flash('rawAuthCode', [
            'id' => $scanner->id,
            'code' => $scanner->raw_auth_code,
        ]);

        $this->resetForm();
        $this->showCreateModal = false;
        unset($this->scanners);
    }

    public function editScanner(int $scannerId): void
    {
        $scanner = ProjectScanner::where('project_id', $this->projectId)->findOrFail($scannerId);

        $this->editingScannerId = $scannerId;
        $this->name = $scanner->name;
        $this->type = $scanner->type->value;
        $this->modes = $scanner->modes ?? ['checkin'];
        $this->eventId = $scanner->event_id;
        $this->gearItemIds = $scanner->gear_item_ids ?? [];
        $this->hintText = $scanner->hint_text ?? '';
        $this->startsAt = $scanner->starts_at->format('Y-m-d\TH:i');
        $this->endsAt = $scanner->ends_at->format('Y-m-d\TH:i');
        $this->showCreateModal = true;
    }

    public function updateScanner(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:entry_staff,volunteer_admin'],
            'modes' => ['required', 'array', 'min:1'],
            'modes.*' => ['string', 'in:checkin,gear_pickup'],
            'eventId' => ['nullable', Rule::exists('events', 'id')->where('project_id', $this->projectId)],
            'startsAt' => ['required', 'date'],
            'endsAt' => ['required', 'date', 'after:startsAt'],
        ]);

        $scanner = ProjectScanner::where('project_id', $this->projectId)
            ->findOrFail($this->editingScannerId);

        $action = new UpdateProjectScanner;
        $action->execute($scanner, [
            'name' => $this->name,
            'type' => $this->type,
            'modes' => $this->modes,
            'event_id' => $this->eventId,
            'gear_item_ids' => ! empty($this->gearItemIds) ? $this->gearItemIds : null,
            'hint_text' => $this->hintText ?: null,
            'starts_at' => $this->startsAt,
            'ends_at' => $this->endsAt,
        ]);

        $this->resetForm();
        $this->showCreateModal = false;
        $this->editingScannerId = null;
        unset($this->scanners);
    }

    public function confirmDelete(int $scannerId): void
    {
        $this->deletingScannerId = $scannerId;
        $this->showDeleteConfirm = true;
    }

    public function deleteScanner(): void
    {
        $scanner = ProjectScanner::where('project_id', $this->projectId)
            ->findOrFail($this->deletingScannerId);

        $action = new DeleteProjectScanner;
        $action->execute($scanner);

        $this->showDeleteConfirm = false;
        $this->deletingScannerId = null;
        unset($this->scanners);
    }

    public function sendLinks(int $scannerId): void
    {
        $scanner = ProjectScanner::where('project_id', $this->projectId)
            ->findOrFail($scannerId);

        $action = new SendScannerLinks;
        $action->execute($scanner);

        session()->flash('message', 'Scanner links sent.');
        unset($this->scanners);
    }

    public function addAssignee(int $scannerId, string $email): void
    {
        $validator = Validator::make(
            ['email' => $email],
            ['email' => ['required', 'email']],
        );

        if ($validator->fails()) {
            return;
        }

        $scanner = ProjectScanner::where('project_id', $this->projectId)->findOrFail($scannerId);

        $scanner->assignees()->firstOrCreate(
            ['email' => $email],
        );

        unset($this->scanners);
    }

    public function removeAssignee(int $assigneeId): void
    {
        $assignee = ProjectScannerAssignee::whereHas('projectScanner', function ($q) {
            $q->where('project_id', $this->projectId);
        })->findOrFail($assigneeId);

        $assignee->delete();

        unset($this->scanners);
    }

    private function resetForm(): void
    {
        $this->name = '';
        $this->type = 'entry_staff';
        $this->modes = ['checkin'];
        $this->eventId = null;
        $this->gearItemIds = [];
        $this->hintText = '';
        $this->startsAt = '';
        $this->endsAt = '';
    }
}
