<?php

namespace App\Actions;

use App\Enums\EventStatus;
use App\Events\Activity\EventRevertedToDraft;
use App\Exceptions\DomainException;
use App\Models\Event;
use App\Models\User;

class RevertEventToDraft
{
    public function execute(Event $event, User $causer): Event
    {
        if ($event->status === EventStatus::Draft) {
            throw new DomainException('Event is already a draft.');
        }

        $event->update(['status' => EventStatus::Draft]);

        EventRevertedToDraft::dispatch($event->refresh(), $causer);

        return $event->refresh();
    }
}
