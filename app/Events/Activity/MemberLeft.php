<?php

namespace App\Events\Activity;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class MemberLeft
{
    use Dispatchable;

    public function __construct(
        public readonly Organization $organization,
        public readonly User $user,
    ) {}
}
