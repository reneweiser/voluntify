<?php

namespace App\Events\Activity;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class JobDeleted
{
    use Dispatchable;

    public function __construct(
        public readonly string $jobName,
        public readonly int $eventId,
        public readonly string $eventName,
        public readonly User $causer,
    ) {}
}
