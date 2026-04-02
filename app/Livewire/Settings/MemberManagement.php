<?php

namespace App\Livewire\Settings;

use App\Actions\InviteMember;
use App\Enums\StaffRole;
use App\Exceptions\MemberAlreadyExistsException;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Members')]
class MemberManagement extends Component
{
    public string $inviteName = '';

    public string $inviteEmail = '';

    public bool $showRemoveModal = false;

    public ?int $removeMemberId = null;

    public string $removeConfirmEmail = '';

    public function mount(): void
    {
        Gate::authorize('manageMembers', $this->organization());
    }

    #[Computed]
    public function organization(): Organization
    {
        return currentOrganization();
    }

    #[Computed]
    public function members(): Collection
    {
        return $this->organization()->users()->orderBy('name')->get();
    }

    public function confirmRemoveMember(int $userId): void
    {
        if ($userId === Auth::id()) {
            $this->addError('member', 'You cannot remove yourself.');

            return;
        }

        $this->removeMemberId = $userId;
        $this->removeConfirmEmail = '';
        $this->resetErrorBag('removeConfirmEmail');
        $this->showRemoveModal = true;
    }

    #[Computed]
    public function memberToRemove(): ?User
    {
        if (! $this->removeMemberId) {
            return null;
        }

        return $this->members()->firstWhere('id', $this->removeMemberId);
    }

    public function removeMember(): void
    {
        Gate::authorize('manageMembers', $this->organization());

        $member = $this->memberToRemove;

        if (! $member) {
            return;
        }

        $this->validate([
            'removeConfirmEmail' => ['required'],
        ]);

        if (Str::lower($this->removeConfirmEmail) !== Str::lower($member->email)) {
            $this->addError('removeConfirmEmail', 'The email address does not match.');

            return;
        }

        $this->organization()->users()->detach($member->id);

        $this->showRemoveModal = false;
        $this->reset('removeMemberId', 'removeConfirmEmail');
        unset($this->members, $this->memberToRemove);
    }

    public function inviteMember(): void
    {
        Gate::authorize('manageMembers', $this->organization());

        $this->validate([
            'inviteName' => ['required', 'string', 'max:255'],
            'inviteEmail' => ['required', 'email', 'max:255'],
        ]);

        try {
            app(InviteMember::class)->execute(
                $this->organization(),
                $this->inviteName,
                $this->inviteEmail,
                StaffRole::Organizer,
                auth()->user(),
            );
        } catch (MemberAlreadyExistsException) {
            $this->addError('inviteEmail', 'This user is already a member.');

            return;
        }

        $this->reset('inviteName', 'inviteEmail');

        unset($this->members);

        $this->dispatch('member-invited');
    }
}
