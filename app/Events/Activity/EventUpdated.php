<?php

namespace App\Events\Activity;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class EventUpdated
{
    use Dispatchable;

    /**
     * @param  array<string, array{0: mixed, 1: mixed}>  $changed
     */
    public function __construct(
        public readonly Event $event,
        public readonly User $causer,
        public readonly array $changed,
    ) {}
}
