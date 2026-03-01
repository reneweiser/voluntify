<?php

namespace App\Policies;

use App\Enums\StaffRole;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;

class EventPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $organization->users()->where('user_id', $user->id)->exists();
    }

    public function view(User $user, Event $event): bool
    {
        return $event->organization->users()->where('user_id', $user->id)->exists();
    }

    public function create(User $user, Organization $organization): bool
    {
        return $this->isOrganizer($user, $organization);
    }

    public function update(User $user, Event $event): bool
    {
        return $this->isOrganizer($user, $event->organization);
    }

    public function publish(User $user, Event $event): bool
    {
        return $this->isOrganizer($user, $event->organization);
    }

    public function archive(User $user, Event $event): bool
    {
        return $this->isOrganizer($user, $event->organization);
    }

    public function manageJobs(User $user, Event $event): bool
    {
        return $this->isOrganizer($user, $event->organization);
    }

    private function isOrganizer(User $user, Organization $organization): bool
    {
        return $organization->users()
            ->where('user_id', $user->id)
            ->wherePivot('role', StaffRole::Organizer)
            ->exists();
    }
}
