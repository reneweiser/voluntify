<?php

namespace App\Events\Activity;

use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class ShiftCreated
{
    use Dispatchable;

    public function __construct(
        public readonly Shift $shift,
        public readonly User $causer,
    ) {}
}
