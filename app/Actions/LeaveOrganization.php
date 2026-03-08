<?php

namespace App\Actions;

use App\Enums\StaffRole;
use App\Exceptions\DomainException;
use App\Models\Organization;
use App\Models\User;

class LeaveOrganization
{
    public function execute(User $user, Organization $organization): void
    {
        if ($user->isPersonalOrganization($organization)) {
            throw new DomainException('You cannot leave your personal organization.');
        }

        $isSoleOrganizer = $organization->users()
            ->wherePivot('role', StaffRole::Organizer)
            ->count() === 1
            && $organization->users()
                ->wherePivot('role', StaffRole::Organizer)
                ->where('user_id', $user->id)
                ->exists();

        if ($isSoleOrganizer) {
            throw new DomainException('You are the sole organizer. Transfer the organizer role to another member first.');
        }

        $organization->users()->detach($user->id);

        if ($user->current_organization_id === $organization->id) {
            $user->update(['current_organization_id' => $user->personal_organization_id]);
        }
    }
}
