<?php

namespace App\Actions;

use App\Models\EmailVerificationToken;
use App\Models\Event;
use App\Models\Volunteer;
use App\Notifications\EmailVerification;
use App\ValueObjects\HashedToken;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class SendEmailVerification
{
    public function execute(string $email, Event $event, ?Volunteer $volunteer = null): EmailVerificationToken
    {
        $plainToken = Str::random(64);
        $hashed = HashedToken::fromPlaintext($plainToken);

        $token = EmailVerificationToken::create([
            'volunteer_id' => $volunteer?->id,
            'event_id' => $event->id,
            'project_id' => $event->project_id,
            'email' => $email,
            'shift_ids' => null,
            'token_hash' => $hashed->hash,
            'expires_at' => now()->addHours(24),
        ]);

        $verificationUrl = route('volunteer.verify-email', $plainToken);
        $notification = new EmailVerification($event, $verificationUrl);

        if ($volunteer) {
            $volunteer->notify($notification);
        } else {
            Notification::route('mail', $email)->notify($notification);
        }

        return $token;
    }
}
