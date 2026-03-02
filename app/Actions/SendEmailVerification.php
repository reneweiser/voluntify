<?php

namespace App\Actions;

use App\Models\EmailVerificationToken;
use App\Models\Event;
use App\Models\Volunteer;
use App\Notifications\EmailVerification;
use App\ValueObjects\HashedToken;
use Illuminate\Support\Str;

class SendEmailVerification
{
    /**
     * @param  array<int>  $shiftIds
     */
    public function execute(Volunteer $volunteer, Event $event, array $shiftIds): void
    {
        $plainToken = Str::random(64);
        $hashed = HashedToken::fromPlaintext($plainToken);

        EmailVerificationToken::create([
            'volunteer_id' => $volunteer->id,
            'event_id' => $event->id,
            'shift_ids' => $shiftIds,
            'token_hash' => $hashed->hash,
            'expires_at' => now()->addHours(24),
        ]);

        $verificationUrl = route('volunteer.verify-email', $plainToken);

        $volunteer->notify(new EmailVerification($event, $verificationUrl));
    }
}
