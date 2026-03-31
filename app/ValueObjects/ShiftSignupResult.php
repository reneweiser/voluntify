<?php

namespace App\ValueObjects;

use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Volunteer;

/**
 * The outcome of signing a volunteer up for one or more shifts.
 *
 * Position in the signup pipeline:
 *   ReserveShifts -> ReservationResult
 *   ProcessVolunteerSignup -> SignupOutcome (wraps ShiftSignupResult)
 *   SignUpVolunteerForShifts -> ShiftSignupResult (this class)
 *
 * Contains the volunteer, successfully created signups, and any shifts
 * that were skipped due to being full or already signed up.
 */
readonly class ShiftSignupResult
{
    /**
     * @param  array<ShiftSignup>  $newSignups
     * @param  array<Shift>  $skippedFull
     * @param  array<Shift>  $skippedDuplicate
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
