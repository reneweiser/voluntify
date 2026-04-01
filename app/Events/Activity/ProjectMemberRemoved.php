<?php

namespace App\Events\Activity;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class ProjectMemberRemoved
{
    use Dispatchable;

    public function __construct(
        public readonly Project $project,
        public readonly User $user,
        public readonly User $causer,
    ) {}
}
