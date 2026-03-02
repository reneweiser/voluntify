<?php

namespace App\Actions;

use App\Enums\EventStatus;
use App\Exceptions\DomainException;
use App\Exceptions\ExpiredVerificationException;
use App\Models\EmailVerificationToken;
use App\ValueObjects\HashedToken;
use App\ValueObjects\SignupBatchResult;

class CompleteEmailVerification
{
    public function __construct(
        private SignUpVolunteerForShifts $signUpAction,
    ) {}

    public function execute(string $plainToken): SignupBatchResult
    {
        $hashed = HashedToken::fromPlaintext($plainToken);

        $token = EmailVerificationToken::where('token_hash', $hashed->hash)->firstOrFail();

        if ($token->expires_at->isPast()) {
            throw new ExpiredVerificationException('This verification link has expired. Please sign up again.');
        }

        $event = $token->event;

        if ($event->status !== EventStatus::Published) {
            throw new DomainException('This event is no longer accepting signups.');
        }

        $volunteer = $token->volunteer;
        $volunteer->markEmailAsVerified();

        $result = $this->signUpAction->execute(
            name: $volunteer->name,
            email: $volunteer->email,
            event: $event,
            shiftIds: $token->shift_ids,
            phone: $volunteer->phone,
        );

        $token->delete();

        return $result;
    }
}
