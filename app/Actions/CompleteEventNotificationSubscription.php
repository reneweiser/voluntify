<?php

namespace App\Actions;

use App\Exceptions\ExpiredVerificationException;
use App\Models\EventNotificationSubscriber;
use App\ValueObjects\HashedToken;

class CompleteEventNotificationSubscription
{
    public function execute(string $plainToken): EventNotificationSubscriber
    {
        $subscriber = EventNotificationSubscriber::query()
            ->where('verification_token_hash', HashedToken::fromPlaintext($plainToken)->hash)
            ->firstOrFail();

        if ($subscriber->isVerified()) {
            return $subscriber;
        }

        if ($subscriber->verification_expires_at?->isPast()) {
            throw new ExpiredVerificationException('This verification link has expired.');
        }

        $subscriber->update([
            'verified_at' => now(),
        ]);

        return $subscriber->fresh();
    }
}
