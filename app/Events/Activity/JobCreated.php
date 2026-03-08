<?php

namespace App\Events\Activity;

use App\Models\User;
use App\Models\VolunteerJob;
use Illuminate\Foundation\Events\Dispatchable;

class JobCreated
{
    use Dispatchable;

    public function __construct(
        public readonly VolunteerJob $job,
        public readonly User $causer,
    ) {}
}
