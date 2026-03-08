<?php

namespace App\Events\Activity;

use App\Enums\StaffRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class MemberInvited
{
    use Dispatchable;

    public function __construct(
        public readonly Organization $organization,
        public readonly string $name,
        public readonly string $email,
        public readonly StaffRole $role,
        public readonly User $causer,
    ) {}
}
