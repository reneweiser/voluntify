<?php

namespace App\ValueObjects;

use App\Models\Shift;
use App\Models\ShiftReservation;
use Carbon\CarbonInterface;

/**
 * The outcome of attempting to reserve shifts for a signup session.
 *
 * Position in the signup pipeline:
 *   ReserveShifts -> ReservationResult (this class)
 *   ProcessVolunteerSignup -> ShiftSignupResult
 *   SignUpVolunteerForShifts -> ShiftSignupResult
 *
 * Contains the successfully reserved shifts, any shifts that were unavailable
 * (already at capacity), and the expiry time for the reservations.
 */
readonly class ReservationResult
{
    /**
     * @param  array<ShiftReservation>  $reserved
     * @param  array<Shift>  $unavailable
     */
    public function __construct(
        public array $reserved = [],
        public array $unavailable = [],
        public ?CarbonInterface $expiresAt = null,
    ) {}

    public function hasReservations(): bool
    {
        return count($this->reserved) > 0;
    }

    /** @return array<int> */
    public function reservedShiftIds(): array
    {
        return array_map(fn (ShiftReservation $r) => $r->shift_id, $this->reserved);
    }
}
