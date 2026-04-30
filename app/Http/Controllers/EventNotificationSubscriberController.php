<?php

namespace App\Http\Controllers;

use App\Actions\CompleteEventNotificationSubscription;
use App\Exceptions\ExpiredVerificationException;
use App\Models\EventNotificationSubscriber;
use App\ValueObjects\HashedToken;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\View\View;

class EventNotificationSubscriberController extends Controller
{
    public function verify(string $token): View
    {
        try {
            $subscriber = app(CompleteEventNotificationSubscription::class)->execute($token);

            return view('public.event-notification-subscriber-status', [
                'title' => __('Notification Confirmed'),
                'heading' => __('You are on the list'),
                'message' => __('We will email :email as soon as new shifts are available for :event.', [
                    'email' => $subscriber->email,
                    'event' => $subscriber->event->name,
                ]),
                'actionLabel' => __('Back to event signup'),
                'actionUrl' => route('events.public', $subscriber->event->public_token),
            ]);
        } catch (ExpiredVerificationException) {
            return view('public.event-notification-subscriber-status', [
                'title' => __('Link Expired'),
                'heading' => __('Verification link expired'),
                'message' => __('Please submit the notification form again if you still want to be notified.'),
                'actionLabel' => null,
                'actionUrl' => null,
            ]);
        } catch (ModelNotFoundException) {
            abort(404);
        }
    }

    public function unsubscribe(string $token): View
    {
        $subscriber = EventNotificationSubscriber::query()
            ->where('unsubscribe_token_hash', HashedToken::fromPlaintext($token)->hash)
            ->firstOrFail();

        $event = $subscriber->event;
        $subscriber->delete();

        return view('public.event-notification-subscriber-status', [
            'title' => __('Unsubscribed'),
            'heading' => __('Notifications turned off'),
            'message' => __('You will no longer receive availability updates for :event.', [
                'event' => $event->name,
            ]),
            'actionLabel' => __('Back to event signup'),
            'actionUrl' => route('events.public', $event->public_token),
        ]);
    }
}
