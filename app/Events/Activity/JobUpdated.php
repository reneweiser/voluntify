<?php

namespace App\Events\Activity;

use App\Models\User;
use App\Models\VolunteerJob;
use Illuminate\Foundation\Events\Dispatchable;

class JobUpdated
{
    use Dispatchable;

    /**
     * @param  array<string, array{0: mixed, 1: mixed}>  $changed
     */
    public function __construct(
        public readonly VolunteerJob $job,
        public readonly User $causer,
        public readonly array $changed,
    ) {}
}
