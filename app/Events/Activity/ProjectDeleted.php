<?php

namespace App\Events\Activity;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class ProjectDeleted
{
    use Dispatchable;

    /**
     * @param  array<string>  $orphanedEventNames
     */
    public function __construct(
        public readonly string $projectName,
        public readonly int $organizationId,
        public readonly array $orphanedEventNames,
        public readonly User $causer,
    ) {}
}
