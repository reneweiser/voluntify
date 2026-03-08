<?php

namespace App\Events\Activity;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class ShiftDeleted
{
    use Dispatchable;

    /**
     * @param  array{starts_at: string, ends_at: string, capacity: int, job_name: string, event_id: int, event_name: string}  $shiftData
     */
    public function __construct(
        public readonly array $shiftData,
        public readonly User $causer,
    ) {}
}
