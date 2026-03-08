<?php

namespace App\Events\Activity;

use App\Models\Event;
use App\Models\Volunteer;
use Illuminate\Foundation\Events\Dispatchable;

class VolunteerVerified
{
    use Dispatchable;

    public function __construct(
        public readonly Volunteer $volunteer,
        public readonly Event $event,
    ) {}
}
