<?php

namespace App\Events\Activity;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class EventGroupDeleted
{
    use Dispatchable;

    /**
     * @param  array<string>  $ungroupedEventNames
     */
    public function __construct(
        public readonly string $groupName,
        public readonly int $organizationId,
        public readonly array $ungroupedEventNames,
        public readonly User $causer,
    ) {}
}
