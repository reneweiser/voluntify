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
        return $organization->users()->where('user_id', $user->id)->exists();
    }

    public function view(User $user, EventGroup $eventGroup): bool
    {
        return $eventGroup->organization->users()->where('user_id', $user->id)->exists();
    }

    public function create(User $user, Organization $organization): bool
    {
        return $this->isOrganizer($user, $organization);
    }

    public function update(User $user, EventGroup $eventGroup): bool
    {
        return $this->isOrganizer($user, $eventGroup->organization);
    }

    public function delete(User $user, EventGroup $eventGroup): bool
    {
        return $this->isOrganizer($user, $eventGroup->organization);
    }

    private function isOrganizer(User $user, Organization $organization): bool
    {
        return $organization->users()
            ->where('user_id', $user->id)
            ->wherePivot('role', StaffRole::Organizer)
            ->exists();
    }
}
