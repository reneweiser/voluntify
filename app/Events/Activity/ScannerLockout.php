<?php

namespace App\Events\Activity;

use App\Models\ProjectScanner;
use Illuminate\Foundation\Events\Dispatchable;

class ScannerLockout
{
    use Dispatchable;

    public function __construct(
        public readonly ProjectScanner $scanner,
    ) {}
}
