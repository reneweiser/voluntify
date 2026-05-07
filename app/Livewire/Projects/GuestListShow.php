<?php

namespace App\Livewire\Projects;

use App\Actions\AddGuestEntry;
use App\Actions\AddGuestGroup;
use App\Actions\ConfirmGuestList;
use App\Actions\QueueGuestInvitationSiblingSet;
use App\Actions\RemoveGuestEntry;
use App\Actions\RemoveGuestGroup;
use App\Actions\UpdateGuestEntry;
use App\Actions\UpdateGuestList;
use App\Enums\ScannerType;
use App\Exceptions\DomainException;
use App\Models\GuestEntry;
use App\Models\GuestGroup;
use App\Models\GuestList;
use App\Models\Project;
use App\Models\ProjectGearItem;
use App\Models\ProjectScanner;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
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
        $this->authorizeGuestListManagement();

        $guestList = $this->guestList;

        $this->editName = $guestList->name;
        $this->editScannerId = $guestList->scanner_id;
        $this->editGearItemIds = $guestList->gear_items ?? [];
        $this->showEditModal = true;
    }

    public function updateGuestList(): void
    {
        $this->authorizeGuestListManagement();

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
        $this->authorizeGuestListManagement();

        $guestList = GuestList::where('project_id', $this->projectId)->findOrFail($this->guestListId);

        try {
            $action = new ConfirmGuestList;
            $action->execute($guestList);
        } catch (DomainException $e) {
            session()->flash('error', $e->getMessage());
            $this->dispatch('guest-list-feedback');

            return;
        }

        session()->flash('message', __('Guest list sending is now active. QR codes are being generated.'));
        $this->dispatch('guest-list-feedback');
        unset($this->guestList);
    }

    #[Computed]
    public function pendingInvitationCount(): int
    {
        return $this->guestList->entries()
            ->pendingInvitation()
            ->distinct()
            ->count('email');
    }

    public function sendPendingInvitations(): void
    {
        $this->authorizeGuestListManagement();

        $guestList = GuestList::where('project_id', $this->projectId)->findOrFail($this->guestListId);

        if (! $guestList->isConfirmed()) {
            return;
        }

        $emails = $guestList->entries()
            ->pendingInvitation()
            ->distinct()
            ->pluck('email');

        $queueGuestInvitationSiblingSet = new QueueGuestInvitationSiblingSet;
        $queuedCount = 0;

        foreach ($emails as $email) {
            if ($queueGuestInvitationSiblingSet->claimPending($guestList, $email)) {
                $queuedCount++;
            }
        }

        session()->flash('message', __('Invitations queued for :count recipients.', ['count' => $queuedCount]));
        $this->dispatch('guest-list-feedback');
        unset($this->guestList, $this->pendingInvitationCount);
    }

    public function resendFailedInvitation(int $entryId): void
    {
        $this->authorizeGuestListManagement();

        $entry = GuestEntry::whereHas('group', fn ($query) => $query->where('guest_list_id', $this->guestListId))
            ->findOrFail($entryId);

        if (! $entry->isInvitationFailed() || $entry->email === null) {
            return;
        }

        $guestList = GuestList::where('project_id', $this->projectId)->findOrFail($this->guestListId);

        $wasClaimed = (new QueueGuestInvitationSiblingSet)->claimFailed($guestList, $entry->email);

        if (! $wasClaimed) {
            session()->flash('error', __('This invitation is no longer available for resend.'));
            $this->dispatch('guest-list-feedback');

            return;
        }

        session()->flash('message', __('Invitation resend queued for :email.', ['email' => $entry->email]));
        $this->dispatch('guest-list-feedback');
        unset($this->guestList, $this->pendingInvitationCount);
    }

    public function addGroup(): void
    {
        $this->authorizeGuestListManagement();

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
        $this->authorizeGuestListManagement();

        $group = GuestGroup::where('guest_list_id', $this->guestListId)->findOrFail($groupId);

        $action = new RemoveGuestGroup;
        $action->execute($group);

        unset($this->guestList);
    }

    public function addEntry(int $groupId): void
    {
        $this->authorizeGuestListManagement();

        $group = GuestGroup::where('guest_list_id', $this->guestListId)->findOrFail($groupId);

        $action = new AddGuestEntry;
        $action->execute($group);

        unset($this->guestList);
    }

    public function removeEntry(int $entryId): void
    {
        $this->authorizeGuestListManagement();

        $entry = GuestEntry::whereHas('group', fn ($q) => $q->where('guest_list_id', $this->guestListId))
            ->findOrFail($entryId);

        $action = new RemoveGuestEntry;
        $action->execute($entry);

        unset($this->guestList);
    }

    public function startEditEntry(int $entryId): void
    {
        $this->authorizeGuestListManagement();

        $entry = GuestEntry::whereHas('group', fn ($q) => $q->where('guest_list_id', $this->guestListId))
            ->findOrFail($entryId);

        $this->editingEntryId = $entryId;
        $this->entryName = $entry->name ?? '';
        $this->entryEmail = $entry->email ?? '';
        $this->entryGear = [];

        $this->dispatch('guest-entry-edit-opened', inputId: $entry->isInvitationFailed()
            ? 'entry-email-'.$entry->id
            : 'entry-name-'.$entry->id);
    }

    public function saveEntry(): void
    {
        $this->authorizeGuestListManagement();

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
        session()->flash('message', __('Guest entry updated.'));
        $this->dispatch('guest-list-feedback');
    }

    public function cancelEditEntry(): void
    {
        $this->authorizeGuestListManagement();

        $this->editingEntryId = null;
        $this->entryName = '';
        $this->entryEmail = '';
        $this->entryGear = [];
    }

    private function authorizeGuestListManagement(): void
    {
        $authenticatedUser = Auth::user();

        if ($authenticatedUser instanceof User) {
            Auth::setUser($authenticatedUser->fresh());
        }

        Gate::authorize('manageGuestLists', $this->project);
    }
}
