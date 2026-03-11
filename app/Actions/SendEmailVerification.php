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
     * @param  array<int, string|null>|null  $gearSelections
     * @param  array<int, mixed>|null  $customFieldResponses
     */
    public function execute(Volunteer $volunteer, Event $event, array $shiftIds, ?array $gearSelections = null, ?array $customFieldResponses = null): void
    {
        $plainToken = Str::random(64);
        $hashed = HashedToken::fromPlaintext($plainToken);

        EmailVerificationToken::create([
            'volunteer_id' => $volunteer->id,
            'event_id' => $event->id,
            'shift_ids' => $shiftIds,
            'gear_selections' => $gearSelections,
            'custom_field_responses' => $customFieldResponses,
            'token_hash' => $hashed->hash,
            'expires_at' => now()->addHours(24),
        ]);

        $verificationUrl = route('volunteer.verify-email', $plainToken);

        $volunteer->notify(new EmailVerification($event, $verificationUrl));
    }
}
