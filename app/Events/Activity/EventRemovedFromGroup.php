<?php

namespace App\Events\Activity;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class EventRemovedFromGroup
{
    use Dispatchable;

    public function __construct(
        public readonly string $groupName,
        public readonly int $organizationId,
        public readonly Event $event,
        public readonly User $causer,
    ) {}
}
