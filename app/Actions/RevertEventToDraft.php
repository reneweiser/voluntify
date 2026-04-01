<?php

namespace App\Actions;

use App\Enums\EventStatus;
use App\Events\Activity\EventRevertedToDraft;
use App\Exceptions\DomainException;
use App\Models\Event;

class RevertEventToDraft
{
    public function execute(Event $event): Event
    {
        if ($event->status === EventStatus::Draft) {
            throw new DomainException('Event is already a draft.');
        }

        $event->update(['status' => EventStatus::Draft]);

        if (auth()->user()) {
            EventRevertedToDraft::dispatch($event->refresh(), auth()->user());
        }

        return $event->refresh();
    }
}
