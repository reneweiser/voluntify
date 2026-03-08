<?php

namespace App\Livewire;

use App\Actions\CreateOrganization;
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

    public function switchOrganization(int $organizationId): void
    {
        $organization = Organization::findOrFail($organizationId);

        Gate::authorize('view', $organization);

        session(['current_organization_id' => $organization->id]);

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

        $this->reset('showCreateModal', 'newOrgName', 'newOrgSlug');

        $this->redirect(route('dashboard'), navigate: true);
    }
}
