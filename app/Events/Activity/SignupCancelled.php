<?php

namespace App\Events\Activity;

use App\Models\ShiftSignup;
use App\Models\Volunteer;
use Illuminate\Foundation\Events\Dispatchable;

class SignupCancelled
{
    use Dispatchable;

    public function __construct(
        public readonly ShiftSignup $signup,
        public readonly Volunteer $volunteer,
    ) {}
}
