<?php

namespace App\Events\Activity;

use App\Models\Organization;
use App\Models\User;
use App\Models\VolunteerPromotion;
use Illuminate\Foundation\Events\Dispatchable;

class VolunteerPromotedEvent
{
    use Dispatchable;

    public function __construct(
        public readonly VolunteerPromotion $promotion,
        public readonly Organization $organization,
        public readonly User $causer,
    ) {}
}
