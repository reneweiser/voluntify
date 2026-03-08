<?php

namespace App\Actions;

use App\Enums\EventStatus;
use App\Events\Activity\EventPublished;
use App\Exceptions\DomainException;
use App\Exceptions\EventNotReadyException;
use App\Models\Event;

class PublishEvent
{
    public function execute(Event $event): Event
    {
        if ($event->status === EventStatus::Archived) {
            throw new DomainException('Cannot publish an archived event.');
        }

        if ($event->status === EventStatus::Published) {
            throw new DomainException('Event is already published.');
        }

        $hasShifts = $event->volunteerJobs()
            ->whereHas('shifts')
            ->exists();

        if (! $hasShifts) {
            throw new EventNotReadyException('Event must have at least one job with shifts before publishing.');
        }

        $event->update(['status' => EventStatus::Published]);

        if (auth()->user()) {
            EventPublished::dispatch($event->refresh(), auth()->user());
        }

        return $event->refresh();
    }
}
