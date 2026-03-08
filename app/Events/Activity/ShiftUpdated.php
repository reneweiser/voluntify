<?php

namespace App\Events\Activity;

use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class ShiftUpdated
{
    use Dispatchable;

    /**
     * @param  array<string, array{0: mixed, 1: mixed}>  $changed
     */
    public function __construct(
        public readonly Shift $shift,
        public readonly User $causer,
        public readonly array $changed,
    ) {}
}
