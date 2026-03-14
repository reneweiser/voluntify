<?php

namespace App\Policies;

use App\Enums\StaffRole;
use App\Models\EventGroup;
use App\Models\Organization;
use App\Models\User;

class EventGroupPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->cachedRoleFor($organization) !== null;
    }

    public function view(User $user, EventGroup $eventGroup): bool
    {
        return $user->cachedRoleFor($eventGroup->organization) !== null;
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->cachedRoleFor($organization) === StaffRole::Organizer;
    }

    public function update(User $user, EventGroup $eventGroup): bool
    {
        return $user->cachedRoleFor($eventGroup->organization) === StaffRole::Organizer;
    }

    public function delete(User $user, EventGroup $eventGroup): bool
    {
        return $user->cachedRoleFor($eventGroup->organization) === StaffRole::Organizer;
    }
}
