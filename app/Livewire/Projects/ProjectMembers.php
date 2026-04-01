<?php

namespace App\Livewire\Projects;

use App\Actions\AddProjectMember;
use App\Actions\RemoveProjectMember;
use App\Exceptions\DomainException;
use App\Exceptions\MemberAlreadyExistsException;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Project Members')]
class ProjectMembers extends Component
{
    #[Locked]
    public Project $project;

    public string $inviteEmail = '';

    public bool $showRemoveModal = false;

    public ?int $removeMemberId = null;

    public function mount(int $projectId): void
    {
        $this->project = currentOrganization()->projects()->findOrFail($projectId);

        Gate::authorize('manageMembers', $this->project);
    }

    /** @return Collection<int, User> */
    #[Computed]
    public function members(): Collection
    {
        return $this->project->users()->orderBy('name')->get();
    }

    /** @return Collection<int, User> */
    #[Computed]
    public function orgOrganizers(): Collection
    {
        return currentOrganization()->users()->orderBy('name')->get();
    }

    public function inviteMember(AddProjectMember $action): void
    {
        Gate::authorize('manageMembers', $this->project);

        $this->validate([
            'inviteEmail' => ['required', 'email', 'max:255'],
        ]);

        $user = User::where('email', $this->inviteEmail)->first();

        if (! $user) {
            $this->addError('inviteEmail', 'No user found with this email address.');

            return;
        }

        try {
            $action->execute($this->project, $user, auth()->user());
        } catch (MemberAlreadyExistsException) {
            $this->addError('inviteEmail', 'This user is already a project member.');

            return;
        } catch (DomainException $e) {
            $this->addError('inviteEmail', $e->getMessage());

            return;
        }

        $this->reset('inviteEmail');
        unset($this->members);

        $this->dispatch('member-added');
    }

    public function confirmRemoveMember(int $userId): void
    {
        $this->removeMemberId = $userId;
        $this->showRemoveModal = true;
    }

    public function removeMember(RemoveProjectMember $action): void
    {
        Gate::authorize('manageMembers', $this->project);

        if (! $this->removeMemberId) {
            return;
        }

        $user = User::findOrFail($this->removeMemberId);

        if (! $this->project->users()->where('user_id', $user->id)->exists()) {
            return;
        }

        $action->execute($this->project, $user, auth()->user());

        $this->showRemoveModal = false;
        $this->reset('removeMemberId');
        unset($this->members);
    }

    #[Computed]
    public function memberToRemove(): ?User
    {
        if (! $this->removeMemberId) {
            return null;
        }

        return $this->members->firstWhere('id', $this->removeMemberId);
    }
}
