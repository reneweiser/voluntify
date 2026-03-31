<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Org Organizer or any user with a project assignment in this org can view.
     */
    public function view(User $user, Organization $organization): bool
    {
        return $user->hasAccessToOrganization($organization);
    }

    public function update(User $user, Organization $organization): bool
    {
        return $user->isOrgOrganizerFor($organization);
    }

    /**
     * Only org-level Organizers can manage organization members.
     */
    public function manageMembers(User $user, Organization $organization): bool
    {
        return $user->isOrgOrganizerFor($organization);
    }
}
