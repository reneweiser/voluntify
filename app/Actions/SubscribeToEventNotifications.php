<?php

namespace App\Actions;

use App\Models\Event;
use App\Models\EventNotificationSubscriber;
use App\Notifications\EventNotificationSubscriptionVerification;
use App\ValueObjects\HashedToken;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class SubscribeToEventNotifications
{
    public function execute(Event $event, string $email): EventNotificationSubscriber
    {
        $normalizedEmail = Str::lower(trim($email));

        $subscriber = EventNotificationSubscriber::firstOrNew([
            'event_id' => $event->id,
            'email' => $normalizedEmail,
        ]);

        if ($subscriber->isVerified()) {
            return $subscriber;
        }

        $plainToken = Str::random(64);

        $subscriber->fill([
            'verification_token_hash' => HashedToken::fromPlaintext($plainToken)->hash,
            'verification_expires_at' => now()->addDays(7),
        ]);

        $subscriber->save();

        Notification::route('mail', $subscriber->email)->notify(
            new EventNotificationSubscriptionVerification(
                $event,
                route('events.notifications.verify', $plainToken),
            ),
        );

        return $subscriber->fresh();
    }
}
