<?php

namespace App\Livewire\Projects;

use App\Actions\AddGuestEntry;
use App\Actions\AddGuestGroup;
use App\Actions\ConfirmGuestList;
use App\Actions\RemoveGuestEntry;
use App\Actions\RemoveGuestGroup;
use App\Actions\UpdateGuestEntry;
use App\Actions\UpdateGuestList;
use App\Enums\ScannerType;
use App\Exceptions\DomainException;
use App\Jobs\SendGuestInvitationsJob;
use App\Models\GuestEntry;
use App\Models\GuestGroup;
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

#[Title('Guest List')]
class GuestListShow extends Component
{
    #[Locked]
    public int $projectId;

    #[Locked]
    public int $guestListId;

    public ?Project $project = null;

    public bool $showEditModal = false;

    // Edit form fields
    public string $editName = '';

    public ?int $editScannerId = null;

    public array $editGearItemIds = [];

    // Add group form fields
    public string $newGroupLabel = '';

    public int $newGroupCount = 1;

    // Entry editing fields
    #[Locked]
    public ?int $editingEntryId = null;

    public string $entryName = '';

    public string $entryEmail = '';

    public array $entryGear = [];

    public function mount(int $projectId, int $guestListId): void
    {
        $this->project = currentOrganization()->projects()->findOrFail($projectId);
        $this->projectId = $projectId;
        $this->guestListId = $guestListId;

        Gate::authorize('manageGuestLists', $this->project);

        // Verify guest list belongs to project
        GuestList::where('project_id', $this->projectId)->findOrFail($guestListId);
    }

    #[Computed]
    public function guestList(): GuestList
    {
        return GuestList::where('project_id', $this->projectId)
            ->with('scanner', 'groups.entries.gear')
            ->withCount([
                'entries as total_entries',
                'entries as checked_in_entries' => fn ($q) => $q->whereNotNull('checked_in_at'),
            ])
            ->findOrFail($this->guestListId);
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

    public function openEditModal(): void
    {
        $guestList = $this->guestList;

        $this->editName = $guestList->name;
        $this->editScannerId = $guestList->scanner_id;
        $this->editGearItemIds = $guestList->gear_items ?? [];
        $this->showEditModal = true;
    }

    public function updateGuestList(): void
    {
        $this->validate([
            'editName' => ['required', 'string', 'max:255'],
            'editScannerId' => ['required', Rule::exists('project_scanners', 'id')->where('project_id', $this->projectId)],
            'editGearItemIds' => ['nullable', 'array'],
            'editGearItemIds.*' => ['integer', Rule::exists('project_gear_items', 'id')->where('project_id', $this->projectId)],
        ]);

        $guestList = GuestList::where('project_id', $this->projectId)->findOrFail($this->guestListId);

        $action = new UpdateGuestList;
        $action->execute($guestList, [
            'name' => $this->editName,
            'scanner_id' => $this->editScannerId,
            'gear_items' => ! empty($this->editGearItemIds) ? $this->editGearItemIds : null,
        ]);

        $this->showEditModal = false;
        unset($this->guestList);
    }

    public function confirmGuestList(): void
    {
        $guestList = GuestList::where('project_id', $this->projectId)->findOrFail($this->guestListId);

        try {
            $action = new ConfirmGuestList;
            $action->execute($guestList);
        } catch (DomainException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        session()->flash('message', 'Guest list confirmed. QR codes are being generated.');
        unset($this->guestList);
    }

    #[Computed]
    public function pendingInvitationCount(): int
    {
        return $this->guestList->entries()
            ->whereNotNull('email')
            ->whereNotNull('qr_token')
            ->whereNull('invitation_sent_at')
            ->count();
    }

    public function sendPendingInvitations(): void
    {
        $guestList = GuestList::where('project_id', $this->projectId)->findOrFail($this->guestListId);

        if (! $guestList->isConfirmed()) {
            return;
        }

        $pendingEntries = $guestList->entries()
            ->whereNotNull('email')
            ->whereNotNull('qr_token')
            ->whereNull('invitation_sent_at')
            ->get();

        $emails = $pendingEntries->pluck('email')->unique();

        foreach ($emails as $email) {
            SendGuestInvitationsJob::dispatch($guestList, $email);
        }

        $pendingEntries->each(function (GuestEntry $entry) {
            $entry->update(['invitation_sent_at' => now()]);
        });

        session()->flash('message', __('Invitations queued for :count guests.', ['count' => $emails->count()]));
        unset($this->guestList, $this->pendingInvitationCount);
    }

    public function addGroup(): void
    {
        $this->validate([
            'newGroupLabel' => ['required', 'string', 'max:255'],
            'newGroupCount' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $guestList = GuestList::where('project_id', $this->projectId)->findOrFail($this->guestListId);

        $action = new AddGuestGroup;
        $action->execute($guestList, $this->newGroupLabel, $this->newGroupCount);

        $this->newGroupLabel = '';
        $this->newGroupCount = 1;
        unset($this->guestList);
    }

    public function removeGroup(int $groupId): void
    {
        $group = GuestGroup::where('guest_list_id', $this->guestListId)->findOrFail($groupId);

        $action = new RemoveGuestGroup;
        $action->execute($group);

        unset($this->guestList);
    }

    public function addEntry(int $groupId): void
    {
        $group = GuestGroup::where('guest_list_id', $this->guestListId)->findOrFail($groupId);

        $action = new AddGuestEntry;
        $action->execute($group);

        unset($this->guestList);
    }

    public function removeEntry(int $entryId): void
    {
        $entry = GuestEntry::whereHas('group', fn ($q) => $q->where('guest_list_id', $this->guestListId))
            ->findOrFail($entryId);

        $action = new RemoveGuestEntry;
        $action->execute($entry);

        unset($this->guestList);
    }

    public function startEditEntry(int $entryId): void
    {
        $entry = GuestEntry::whereHas('group', fn ($q) => $q->where('guest_list_id', $this->guestListId))
            ->findOrFail($entryId);

        $this->editingEntryId = $entryId;
        $this->entryName = $entry->name ?? '';
        $this->entryEmail = $entry->email ?? '';
        $this->entryGear = [];
    }

    public function saveEntry(): void
    {
        $this->validate([
            'entryName' => ['nullable', 'string', 'max:255'],
            'entryEmail' => ['nullable', 'email', 'max:255'],
            'entryGear' => ['nullable', 'array'],
            'entryGear.*.project_gear_item_id' => ['required', 'integer', Rule::exists('project_gear_items', 'id')->where('project_id', $this->projectId)],
            'entryGear.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'entryGear.*.selection' => ['nullable', 'string', 'max:255'],
        ]);

        $entry = GuestEntry::whereHas('group', fn ($q) => $q->where('guest_list_id', $this->guestListId))
            ->findOrFail($this->editingEntryId);

        $action = new UpdateGuestEntry;
        $action->execute($entry, [
            'name' => $this->entryName ?: null,
            'email' => $this->entryEmail ?: null,
            'gear' => $this->entryGear,
        ]);

        $this->cancelEditEntry();
        unset($this->guestList);
    }

    public function cancelEditEntry(): void
    {
        $this->editingEntryId = null;
        $this->entryName = '';
        $this->entryEmail = '';
        $this->entryGear = [];
    }
}
