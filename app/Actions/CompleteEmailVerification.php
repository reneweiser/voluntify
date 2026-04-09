<?php

namespace App\Actions;

use App\Enums\EventStatus;
use App\Events\Activity\VolunteerVerified;
use App\Exceptions\DomainException;
use App\Exceptions\ExpiredVerificationException;
use App\Models\EmailVerificationToken;
use App\ValueObjects\HashedToken;

class CompleteEmailVerification
{
    public function execute(string $plainToken): EmailVerificationToken
    {
        $hashed = HashedToken::fromPlaintext($plainToken);

        $token = EmailVerificationToken::where('token_hash', $hashed->hash)->firstOrFail();

        if ($token->isVerified()) {
            return $token;
        }

        if ($token->expires_at->isPast()) {
            throw new ExpiredVerificationException('This verification link has expired. Please sign up again.');
        }

        $event = $token->event;

        if ($event->status !== EventStatus::PublishedOpen) {
            throw new DomainException('This event is no longer accepting signups.');
        }

        $token->update(['verified_at' => now()]);

        if ($token->volunteer_id) {
            $token->volunteer->markEmailAsVerified();
            VolunteerVerified::dispatch($token->volunteer, $event);
        }

        return $token->fresh();
    }
}
