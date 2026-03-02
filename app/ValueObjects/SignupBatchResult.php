<?php

namespace App\ValueObjects;

use App\Models\Volunteer;

readonly class SignupBatchResult
{
    /**
     * @param  array<\App\Models\ShiftSignup>  $newSignups
     * @param  array<\App\Models\Shift>  $skippedFull
     * @param  array<\App\Models\Shift>  $skippedDuplicate
     */
    public function __construct(
        public Volunteer $volunteer,
        public array $newSignups = [],
        public array $skippedFull = [],
        public array $skippedDuplicate = [],
    ) {}

    public function hasNewSignups(): bool
    {
        return count($this->newSignups) > 0;
    }
}
