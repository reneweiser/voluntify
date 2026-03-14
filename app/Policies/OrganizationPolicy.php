<?php

namespace App\Policies;

use App\Enums\StaffRole;
use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, Organization $organization): bool
    {
        return $user->cachedRoleFor($organization) !== null;
    }

    public function update(User $user, Organization $organization): bool
    {
        return $user->cachedRoleFor($organization) === StaffRole::Organizer;
    }

    public function manageMembers(User $user, Organization $organization): bool
    {
        return $user->cachedRoleFor($organization) === StaffRole::Organizer;
    }
}
