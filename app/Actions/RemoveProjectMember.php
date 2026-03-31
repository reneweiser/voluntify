<?php

namespace App\Actions;

use App\Models\Project;
use App\Models\User;

class RemoveProjectMember
{
    public function execute(Project $project, User $user): void
    {
        $project->users()->detach($user->id);
    }
}
