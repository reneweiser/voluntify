<?php

namespace App\Events\Activity;

use App\Models\EventGroup;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class EventGroupCreated
{
    use Dispatchable;

    public function __construct(
        public readonly EventGroup $eventGroup,
        public readonly User $causer,
    ) {}
}
