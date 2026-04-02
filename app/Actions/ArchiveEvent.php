<?php

namespace App\Actions;

use App\Enums\EventStatus;
use App\Events\Activity\EventArchived;
use App\Exceptions\DomainException;
use App\Models\Event;
use App\Models\User;

class ArchiveEvent
{
    public function execute(Event $event, User $causer): Event
    {
        if ($event->status === EventStatus::Draft) {
            throw new DomainException('Cannot archive a draft event. Publish it first.');
        }

        if ($event->status === EventStatus::Archived) {
            throw new DomainException('Event is already archived.');
        }

        $event->update(['status' => EventStatus::Archived]);

        EventArchived::dispatch($event->refresh(), $causer);

        return $event->refresh();
    }
}
