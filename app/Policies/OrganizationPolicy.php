<?php

namespace App\Policies;

use App\Enums\StaffRole;
use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function view(User $user, Organization $organization): bool
    {
        return $organization->users()->where('user_id', $user->id)->exists();
    }

    public function update(User $user, Organization $organization): bool
    {
        return $this->hasRole($user, $organization, StaffRole::Organizer);
    }

    public function manageTeam(User $user, Organization $organization): bool
    {
        return $this->hasRole($user, $organization, StaffRole::Organizer);
    }

    private function hasRole(User $user, Organization $organization, StaffRole $role): bool
    {
        return $organization->users()
            ->where('user_id', $user->id)
            ->wherePivot('role', $role)
            ->exists();
    }
}
