<?php

namespace App\ValueObjects;

use App\Enums\SignupOutcomeType;

/**
 * The top-level outcome of a volunteer signup attempt via the public form.
 *
 * Position in the signup pipeline:
 *   ReserveShifts -> ReservationResult
 *   ProcessVolunteerSignup -> SignupOutcome (this class)
 *   SignUpVolunteerForShifts -> ShiftSignupResult
 *
 * Either the signup completed (wrapping a ShiftSignupResult) or the volunteer
 * needs to verify their email first (pending verification).
 */
readonly class SignupOutcome
{
    private function __construct(
        public SignupOutcomeType $type,
        public ?ShiftSignupResult $batchResult = null,
        public ?string $pendingEmail = null,
    ) {}

    public static function completed(ShiftSignupResult $result): self
    {
        return new self(
            type: SignupOutcomeType::Completed,
            batchResult: $result,
        );
    }

    public static function pendingVerification(string $email): self
    {
        return new self(
            type: SignupOutcomeType::PendingVerification,
            pendingEmail: $email,
        );
    }

    public function isPendingVerification(): bool
    {
        return $this->type === SignupOutcomeType::PendingVerification;
    }
}
