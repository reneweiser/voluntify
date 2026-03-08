<?php

namespace App\Events\Activity;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class EventImageDeleted
{
    use Dispatchable;

    public function __construct(
        public readonly Event $event,
        public readonly User $causer,
    ) {}
}
