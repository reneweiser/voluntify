<?php

namespace App\Events\Activity;

use App\Models\Event;
use App\Models\EventGroup;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class EventAssignedToGroup
{
    use Dispatchable;

    public function __construct(
        public readonly EventGroup $eventGroup,
        public readonly Event $event,
        public readonly User $causer,
    ) {}
}
