<?php

namespace App\Livewire\Projects;

use App\Actions\CreateProjectScanner;
use App\Actions\DeleteProjectScanner;
use App\Actions\SendScannerLinks;
use App\Actions\UpdateProjectScanner;
use App\Events\Activity\ScannerAssigneeAdded;
use App\Events\Activity\ScannerAssigneeRemoved;
use App\Livewire\Forms\ScannerForm;
use App\Models\Event;
use App\Models\Project;
use App\Models\ProjectGearItem;
use App\Models\ProjectScanner;
use App\Models\ProjectScannerAssignee;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
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

    public ScannerForm $form;

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
        $this->form->validate();

        $action = new CreateProjectScanner;
        $scanner = $action->execute($this->project, $this->form->toActionData());

        session()->flash('rawAuthCode', [
            'id' => $scanner->id,
            'code' => $scanner->raw_auth_code,
        ]);

        $this->form->reset();
        $this->showCreateModal = false;
        unset($this->scanners);
    }

    public function editScanner(int $scannerId): void
    {
        $scanner = ProjectScanner::where('project_id', $this->projectId)->findOrFail($scannerId);

        $this->editingScannerId = $scannerId;
        $this->form->fillFromScanner([
            'name' => $scanner->name,
            'type' => $scanner->type->value,
            'modes' => $scanner->modes ?? ['checkin'],
            'eventId' => $scanner->event_id,
            'gearItemIds' => $scanner->gear_item_ids ?? [],
            'hintText' => $scanner->hint_text ?? '',
            'startsAt' => $scanner->starts_at->format('Y-m-d\TH:i'),
            'endsAt' => $scanner->ends_at->format('Y-m-d\TH:i'),
        ]);
        $this->showCreateModal = true;
    }

    public function updateScanner(): void
    {
        $this->form->validate();

        $scanner = ProjectScanner::where('project_id', $this->projectId)
            ->findOrFail($this->editingScannerId);

        $action = new UpdateProjectScanner;
        $action->execute($scanner, $this->form->toActionData());

        $this->form->reset();
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

        $assignee = $scanner->assignees()->firstOrCreate(
            ['email' => $email],
        );

        if ($assignee->wasRecentlyCreated) {
            ScannerAssigneeAdded::dispatch($scanner, $email, auth()->user());
        }

        unset($this->scanners);
    }

    public function removeAssignee(int $assigneeId): void
    {
        $assignee = ProjectScannerAssignee::whereHas('projectScanner', function ($q) {
            $q->where('project_id', $this->projectId);
        })->findOrFail($assigneeId);

        $scanner = $assignee->projectScanner;
        $email = $assignee->email;

        $assignee->delete();

        ScannerAssigneeRemoved::dispatch($scanner, $email, auth()->user());

        unset($this->scanners);
    }
}
