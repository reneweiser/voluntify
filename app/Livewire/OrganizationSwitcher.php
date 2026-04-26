<?php

namespace App\Livewire;

use App\Actions\CreateOrganization;
use App\Actions\LeaveOrganization;
use App\Actions\SetCurrentOrganization;
use App\Exceptions\DomainException;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

class OrganizationSwitcher extends Component
{
    public bool $showCreateModal = false;

    public string $newOrgName = '';

    public string $newOrgSlug = '';

    public bool $showLeaveModal = false;

    public ?int $leaveOrganizationId = null;

    #[Computed]
    public function organizations(): Collection
    {
        /** @var User $user */
        $user = Auth::user();
        $orgIds = $user->accessibleOrganizationIds();

        return Organization::whereIn('id', $orgIds)->orderBy('name')->get();
    }

    #[Computed]
    public function activeOrganization(): Organization
    {
        return currentOrganization();
    }

    #[Computed]
    public function organizationToLeave(): ?Organization
    {
        if (! $this->leaveOrganizationId) {
            return null;
        }

        return $this->organizations->firstWhere('id', $this->leaveOrganizationId);
    }

    public function switchOrganization(int $organizationId, SetCurrentOrganization $action): void
    {
        $organization = Organization::findOrFail($organizationId);
        /** @var User $user */
        $user = Auth::user();

        Gate::authorize('view', $organization);

        $action->execute($user, $organization);

        $this->redirect(route('dashboard'), navigate: true);
    }

    public function updatedNewOrgName(string $value): void
    {
        $this->newOrgSlug = Str::slug($value);
    }

    public function createOrganization(CreateOrganization $action, SetCurrentOrganization $setCurrentOrganization): void
    {
        /** @var User $user */
        $user = Auth::user();

        Gate::authorize('create', Organization::class);

        $this->validate([
            'newOrgName' => ['required', 'max:255'],
            'newOrgSlug' => ['required', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
        ]);

        $organization = $action->execute(
            user: $user,
            name: $this->newOrgName,
            slug: $this->newOrgSlug,
        );

        $setCurrentOrganization->execute($user, $organization);

        $this->reset('showCreateModal', 'newOrgName', 'newOrgSlug');

        $this->redirect(route('dashboard'), navigate: true);
    }

    public function confirmLeaveOrganization(int $id): void
    {
        $this->leaveOrganizationId = $id;
        $this->resetErrorBag('leave');
        $this->showLeaveModal = true;
    }

    public function leaveOrganization(LeaveOrganization $action): void
    {
        $organization = $this->organizationToLeave;
        /** @var User $user */
        $user = Auth::user();

        if (! $organization) {
            return;
        }

        try {
            $action->execute($user, $organization);
        } catch (DomainException $e) {
            $this->addError('leave', $e->getMessage());

            return;
        }

        $this->showLeaveModal = false;
        $this->reset('leaveOrganizationId');

        $this->redirect(route('dashboard'), navigate: true);
    }
}
