<?php

namespace App\Policies;

use App\Enums\StaffRole;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;

class EventPolicy
{
    /**
     * Same as ProjectPolicy::viewAny — if you can see any project, you can see events.
     */
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->hasAccessToOrganization($organization);
    }

    /**
     * Any user with a role on the event's project can view.
     */
    public function view(User $user, Event $event): bool
    {
        return $user->projectRoleFor($event->project) !== null;
    }

    /**
     * Only org-level Organizers can create events (D10).
     */
    public function create(User $user, Organization $organization): bool
    {
        return $user->isOrgOrganizerFor($organization);
    }

    public function update(User $user, Event $event): bool
    {
        return $this->isProjectOrganizer($user, $event);
    }

    public function publish(User $user, Event $event): bool
    {
        return $this->isProjectOrganizer($user, $event);
    }

    public function archive(User $user, Event $event): bool
    {
        return $this->isProjectOrganizer($user, $event);
    }

    public function manageJobs(User $user, Event $event): bool
    {
        return $this->isProjectOrganizer($user, $event);
    }

    /**
     * After M9, only Organizers can mark attendance.
     * M11 will add scanner-assignee-based access for VA role.
     */
    public function markAttendance(User $user, Event $event): bool
    {
        return $this->isProjectOrganizer($user, $event);
    }

    public function manageCustomFields(User $user, Event $event): bool
    {
        return $this->isProjectOrganizer($user, $event);
    }

    public function manageGear(User $user, Event $event): bool
    {
        return $this->isProjectOrganizer($user, $event);
    }

    /**
     * After M9, only Organizers can track gear pickup.
     * M11 will add scanner-assignee-based access for VA role.
     */
    public function trackGearPickup(User $user, Event $event): bool
    {
        return $this->isProjectOrganizer($user, $event);
    }

    /**
     * After M9, only Organizers can scan.
     * M11 will replace with temp-auth scanner links for ES role.
     */
    public function scan(User $user, Event $event): bool
    {
        return $this->isProjectOrganizer($user, $event);
    }

    private function isProjectOrganizer(User $user, Event $event): bool
    {
        return $user->projectRoleFor($event->project) === StaffRole::Organizer;
    }
}
