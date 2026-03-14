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
        return $user->cachedRoleFor($organization) !== null;
    }

    public function view(User $user, Event $event): bool
    {
        return $user->cachedRoleFor($event->organization) !== null;
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->cachedRoleFor($organization) === StaffRole::Organizer;
    }

    public function update(User $user, Event $event): bool
    {
        return $user->cachedRoleFor($event->organization) === StaffRole::Organizer;
    }

    public function publish(User $user, Event $event): bool
    {
        return $user->cachedRoleFor($event->organization) === StaffRole::Organizer;
    }

    public function archive(User $user, Event $event): bool
    {
        return $user->cachedRoleFor($event->organization) === StaffRole::Organizer;
    }

    public function manageJobs(User $user, Event $event): bool
    {
        return $user->cachedRoleFor($event->organization) === StaffRole::Organizer;
    }

    public function markAttendance(User $user, Event $event): bool
    {
        return in_array($user->cachedRoleFor($event->organization), [StaffRole::Organizer, StaffRole::VolunteerAdmin]);
    }

    public function manageCustomFields(User $user, Event $event): bool
    {
        return $user->cachedRoleFor($event->organization) === StaffRole::Organizer;
    }

    public function manageGear(User $user, Event $event): bool
    {
        return $user->cachedRoleFor($event->organization) === StaffRole::Organizer;
    }

    public function trackGearPickup(User $user, Event $event): bool
    {
        return in_array($user->cachedRoleFor($event->organization), [StaffRole::Organizer, StaffRole::VolunteerAdmin]);
    }

    public function scan(User $user, Event $event): bool
    {
        return in_array($user->cachedRoleFor($event->organization), [StaffRole::Organizer, StaffRole::EntranceStaff]);
    }
}
