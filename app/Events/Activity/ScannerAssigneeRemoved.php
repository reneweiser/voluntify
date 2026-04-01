<?php

namespace App\Events\Activity;

use App\Models\ProjectScanner;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class ScannerAssigneeRemoved
{
    use Dispatchable;

    public function __construct(
        public readonly ProjectScanner $scanner,
        public readonly string $email,
        public readonly User $causer,
    ) {}
}
