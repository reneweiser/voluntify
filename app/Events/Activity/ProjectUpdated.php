<?php

namespace App\Events\Activity;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class ProjectUpdated
{
    use Dispatchable;

    /**
     * @param  array<string, array{0: mixed, 1: mixed}>  $changed
     */
    public function __construct(
        public readonly Project $project,
        public readonly User $causer,
        public readonly array $changed,
    ) {}
}
