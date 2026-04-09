<?php

namespace App\Livewire\Projects;

use App\Actions\CreateProjectScanner;
use App\Actions\DeleteProjectScanner;
use App\Actions\RegenerateAuthCode;
use App\Actions\SendScannerLinks;
use App\Actions\UpdateProjectScanner;
use App\Concerns\ConvertsTimezone;
use App\Enums\ScannerMode;
use App\Enums\ScannerType;
use App\Events\Activity\ScannerAssigneeAdded;
use App\Events\Activity\ScannerAssigneeRemoved;
use App\Exceptions\HasGuestListsException;
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
    use ConvertsTimezone;

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

    public function updatedFormType(string $value): void
    {
        if ($value === ScannerType::EntryStaff->value) {
            $this->form->modes = [ScannerMode::Checkin->value];
        }
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

        $tz = $this->project->timezone ?? 'UTC';
        $data = $this->form->toActionData();
        $data['starts_at'] = $this->toUtc($data['starts_at'], $tz);
        $data['ends_at'] = $this->toUtc($data['ends_at'], $tz);

        $action = new CreateProjectScanner;
        $scanner = $action->execute($this->project, $data);

        // Auto-send links to assignees if any exist
        $scanner->load('assignees');
        if ($scanner->assignees->isNotEmpty()) {
            (new SendScannerLinks)->execute($scanner);
        }

        $this->form->reset();
        $this->showCreateModal = false;
        unset($this->scanners);
    }

    public function editScanner(int $scannerId): void
    {
        $scanner = ProjectScanner::where('project_id', $this->projectId)->findOrFail($scannerId);

        $this->editingScannerId = $scannerId;
        $tz = $this->project->timezone ?? 'UTC';
        $this->form->fillFromScanner([
            'name' => $scanner->name,
            'type' => $scanner->type->value,
            'modes' => $scanner->modes ?? ['checkin'],
            'eventId' => $scanner->event_id,
            'gearItemIds' => $scanner->gear_item_ids ?? [],
            'hintText' => $scanner->hint_text ?? '',
            'startsAt' => $this->toLocal($scanner->starts_at, $tz),
            'endsAt' => $this->toLocal($scanner->ends_at, $tz),
        ]);
        $this->showCreateModal = true;
    }

    public function updateScanner(): void
    {
        $this->form->validate();

        $scanner = ProjectScanner::where('project_id', $this->projectId)
            ->findOrFail($this->editingScannerId);

        $tz = $this->project->timezone ?? 'UTC';
        $data = $this->form->toActionData();
        $data['starts_at'] = $this->toUtc($data['starts_at'], $tz);
        $data['ends_at'] = $this->toUtc($data['ends_at'], $tz);

        $action = new UpdateProjectScanner;
        $action->execute($scanner, $data);

        $this->form->reset();
        $this->showCreateModal = false;
        $this->editingScannerId = null;
        unset($this->scanners);
    }

    public function confirmDelete(int $scannerId): void
    {
        $this->resetErrorBag('scanner');
        $this->deletingScannerId = $scannerId;
        $this->showDeleteConfirm = true;
    }

    public function deleteScanner(): void
    {
        $scanner = ProjectScanner::where('project_id', $this->projectId)
            ->findOrFail($this->deletingScannerId);

        try {
            $action = new DeleteProjectScanner;
            $action->execute($scanner);
        } catch (HasGuestListsException $e) {
            $this->addError('scanner', $e->getMessage());

            return;
        }

        $this->showDeleteConfirm = false;
        $this->deletingScannerId = null;
        unset($this->scanners);
    }

    public function regenerateAuthCode(int $scannerId): void
    {
        $scanner = ProjectScanner::where('project_id', $this->projectId)
            ->findOrFail($scannerId);

        $action = new RegenerateAuthCode;
        $action->execute($scanner);

        $sendLinks = new SendScannerLinks;
        $sendLinks->execute($scanner);

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
