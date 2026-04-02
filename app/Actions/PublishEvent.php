<?php

namespace App\Actions;

use App\Enums\EventStatus;
use App\Events\Activity\EventPublished;
use App\Exceptions\DomainException;
use App\Exceptions\EventNotReadyException;
use App\Jobs\SendRepublishNotificationJob;
use App\Models\Event;
use App\Models\User;

class PublishEvent
{
    public function execute(Event $event, ?string $organizerNote = null, ?User $causer = null): Event
    {
        if ($event->status === EventStatus::Archived) {
            throw new DomainException('Cannot publish an archived event.');
        }

        if ($event->status->isPublished()) {
            throw new DomainException('Event is already published.');
        }

        $hasShifts = $event->volunteerJobs()
            ->whereHas('shifts')
            ->exists();

        if (! $hasShifts) {
            throw new EventNotReadyException('Event must have at least one job with shifts before publishing.');
        }

        $wasPublished = $event->was_previously_published;

        $event->update([
            'status' => EventStatus::PublishedOpen,
            'was_previously_published' => true,
        ]);

        if ($wasPublished) {
            SendRepublishNotificationJob::dispatch($event, $organizerNote);
        }

        if ($causer) {
            EventPublished::dispatch($event->refresh(), $causer);
        }

        return $event->refresh();
    }
}
