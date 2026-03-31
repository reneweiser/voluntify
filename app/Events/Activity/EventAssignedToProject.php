<?php

namespace App\Events\Activity;

use App\Models\Event;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class EventAssignedToProject
{
    use Dispatchable;

    public function __construct(
        public readonly Project $project,
        public readonly Event $event,
        public readonly User $causer,
    ) {}
}
