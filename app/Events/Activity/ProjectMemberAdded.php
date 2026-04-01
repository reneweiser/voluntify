<?php

namespace App\Events\Activity;

use App\Enums\StaffRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class ProjectMemberAdded
{
    use Dispatchable;

    public function __construct(
        public readonly Project $project,
        public readonly User $user,
        public readonly StaffRole $role,
        public readonly User $causer,
    ) {}
}
