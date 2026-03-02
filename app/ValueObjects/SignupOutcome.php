<?php

namespace App\ValueObjects;

use App\Enums\SignupOutcomeType;

readonly class SignupOutcome
{
    private function __construct(
        public SignupOutcomeType $type,
        public ?SignupBatchResult $batchResult = null,
        public ?string $pendingEmail = null,
    ) {}

    public static function completed(SignupBatchResult $result): self
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
