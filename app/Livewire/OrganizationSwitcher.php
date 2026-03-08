<?php

namespace App\Livewire;

use App\Actions\CreateOrganization;
use App\Actions\LeaveOrganization;
use App\Exceptions\DomainException;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Collection;
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
        return auth()->user()->organizations()->orderBy('name')->get();
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

    public function switchOrganization(int $organizationId): void
    {
        $organization = Organization::findOrFail($organizationId);

        Gate::authorize('view', $organization);

        session(['current_organization_id' => $organization->id]);
        auth()->user()->updateQuietly(['current_organization_id' => $organization->id]);

        $this->redirect(route('dashboard'), navigate: true);
    }

    public function updatedNewOrgName(string $value): void
    {
        $this->newOrgSlug = Str::slug($value);
    }

    public function createOrganization(CreateOrganization $action): void
    {
        Gate::authorize('create', Organization::class);

        $this->validate([
            'newOrgName' => ['required', 'max:255'],
            'newOrgSlug' => ['required', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
        ]);

        $organization = $action->execute(
            user: auth()->user(),
            name: $this->newOrgName,
            slug: $this->newOrgSlug,
        );

        session(['current_organization_id' => $organization->id]);
        auth()->user()->updateQuietly(['current_organization_id' => $organization->id]);

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

        if (! $organization) {
            return;
        }

        try {
            $action->execute(auth()->user(), $organization);
        } catch (DomainException $e) {
            $this->addError('leave', $e->getMessage());

            return;
        }

        $this->showLeaveModal = false;
        $this->reset('leaveOrganizationId');

        $this->redirect(route('dashboard'), navigate: true);
    }
}
