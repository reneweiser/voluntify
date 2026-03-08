<?php

namespace App\Events\Activity;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class EventCloned
{
    use Dispatchable;

    public function __construct(
        public readonly Event $newEvent,
        public readonly Event $sourceEvent,
        public readonly User $causer,
    ) {}
}
