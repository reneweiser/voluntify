<?php

namespace App\Policies;

use App\Enums\StaffRole;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    /**
     * Org Organizer sees all projects; project-level users see the project list
     * (filtered in query to only their assigned projects).
     */
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->hasAccessToOrganization($organization);
    }

    /**
     * Any user with a project-level role (or org Organizer via inheritance) can view.
     */
    public function view(User $user, Project $project): bool
    {
        return $user->projectRoleFor($project) !== null;
    }

    /**
     * Only org-level Organizers can create projects.
     */
    public function create(User $user, Organization $organization): bool
    {
        return $user->isOrgOrganizerFor($organization);
    }

    /**
     * Project Organizers (direct or inherited from org) can update.
     */
    public function update(User $user, Project $project): bool
    {
        return $user->projectRoleFor($project) === StaffRole::Organizer;
    }

    /**
     * Only org-level Organizers can delete projects.
     */
    public function delete(User $user, Project $project): bool
    {
        return $user->isOrgOrganizerFor($project->organization);
    }

    /**
     * Only org-level Organizers can manage project members.
     * Prevents project Organizers from escalating their own access.
     */
    public function manageMembers(User $user, Project $project): bool
    {
        return $user->isOrgOrganizerFor($project->organization);
    }

    /**
     * Project Organizers (direct or inherited from org) can manage scanners.
     */
    public function manageScanners(User $user, Project $project): bool
    {
        return $user->projectRoleFor($project) === StaffRole::Organizer;
    }

    /**
     * Project Organizers (direct or inherited from org) can manage guest lists.
     */
    public function manageGuestLists(User $user, Project $project): bool
    {
        return $user->projectRoleFor($project) === StaffRole::Organizer;
    }
}
