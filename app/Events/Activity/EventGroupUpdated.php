<?php

namespace App\Events\Activity;

use App\Models\EventGroup;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class EventGroupUpdated
{
    use Dispatchable;

    /**
     * @param  array<string, array{0: mixed, 1: mixed}>  $changed
     */
    public function __construct(
        public readonly EventGroup $eventGroup,
        public readonly User $causer,
        public readonly array $changed,
    ) {}
}
