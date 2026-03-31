<?php

namespace App\Policies;

use App\Enums\StaffRole;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->cachedRoleFor($organization) !== null;
    }

    public function view(User $user, Project $project): bool
    {
        return $user->cachedRoleFor($project->organization) !== null;
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->cachedRoleFor($organization) === StaffRole::Organizer;
    }

    public function update(User $user, Project $project): bool
    {
        return $user->cachedRoleFor($project->organization) === StaffRole::Organizer;
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->cachedRoleFor($project->organization) === StaffRole::Organizer;
    }
}
