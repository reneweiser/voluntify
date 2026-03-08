<?php

namespace App\Events\Activity;

use App\Models\EventArrival;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class ArrivalScanned
{
    use Dispatchable;

    public function __construct(
        public readonly EventArrival $arrival,
        public readonly User $causer,
    ) {}
}
