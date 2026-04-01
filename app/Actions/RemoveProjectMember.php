<?php

namespace App\Actions;

use App\Events\Activity\ProjectMemberRemoved;
use App\Models\Project;
use App\Models\User;

class RemoveProjectMember
{
    public function execute(Project $project, User $user, ?User $causer = null): void
    {
        $project->users()->detach($user->id);

        if ($causer) {
            ProjectMemberRemoved::dispatch($project, $user, $causer);
        }
    }
}
