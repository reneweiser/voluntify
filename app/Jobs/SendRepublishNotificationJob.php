<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\Volunteer;
use App\Notifications\EventRepublishedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendRepublishNotificationJob implements ShouldQueue
{
    use Queueable;

    /** @var int[] */
    public array $backoff = [10, 30, 60];

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public Event $event,
        public ?string $organizerNote = null,
    ) {}

    public function handle(): void
    {
        $volunteers = Volunteer::forEvent($this->event->id)
            ->whereHas('shiftSignups', function ($query) {
                $query->active()
                    ->whereHas('shift.volunteerJob', fn ($q) => $q->where('event_id', $this->event->id));
            })
            ->whereNotNull('email_verified_at')
            ->get();

        foreach ($volunteers as $volunteer) {
            $volunteer->notify(new EventRepublishedNotification(
                event: $this->event,
                organizerNote: $this->organizerNote,
            ));
        }
    }
}
