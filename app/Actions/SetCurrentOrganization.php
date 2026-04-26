<?php

namespace App\Actions;

use App\Models\Organization;
use App\Models\User;

class SetCurrentOrganization
{
    public function execute(User $user, Organization $organization): void
    {
        session(['current_organization_id' => $organization->id]);

        if ($user->current_organization_id !== $organization->id) {
            $user->updateQuietly(['current_organization_id' => $organization->id]);
        }
    }
}
