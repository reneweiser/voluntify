<?php

namespace App\Actions;

use App\Enums\StaffRole;
use App\Events\Activity\ProjectMemberAdded;
use App\Exceptions\DomainException;
use App\Exceptions\MemberAlreadyExistsException;
use App\Models\Project;
use App\Models\User;

class AddProjectMember
{
    public function execute(Project $project, User $user, ?User $causer = null): void
    {
        if ($project->users()->where('user_id', $user->id)->exists()) {
            throw new MemberAlreadyExistsException;
        }

        if ($user->isOrgOrganizerFor($project->organization)) {
            throw new DomainException('User is already an organization Organizer with full access.');
        }

        $project->users()->attach($user, ['role' => StaffRole::Organizer]);

        if (! $user->current_organization_id) {
            $user->updateQuietly(['current_organization_id' => $project->organization_id]);
        }

        if ($causer) {
            ProjectMemberAdded::dispatch($project, $user, StaffRole::Organizer, $causer);
        }
    }
}
