<?php

namespace App\Jobs;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Notifications\EventNewShiftsAvailable;
use App\ValueObjects\HashedToken;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class NotifyEventSubscribers implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 120;

    public function __construct(public int $eventId) {}

    public function uniqueId(): string
    {
        return (string) $this->eventId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $event = Event::query()->find($this->eventId);

        if (! $event || $event->status !== EventStatus::PublishedOpen || $event->publicSignupJobs()->isEmpty()) {
            return;
        }

        foreach ($event->notificationSubscribers()->verified()->get() as $subscriber) {
            $plainToken = Str::random(64);

            $subscriber->update([
                'unsubscribe_token_hash' => HashedToken::fromPlaintext($plainToken)->hash,
                'last_notified_at' => now(),
            ]);

            Notification::route('mail', $subscriber->email)->notify(
                new EventNewShiftsAvailable(
                    $event,
                    route('events.public', $event->public_token),
                    route('events.notifications.unsubscribe', $plainToken),
                ),
            );
        }
    }
}
