<?php

namespace App\Actions;

use App\Enums\EventStatus;
use App\Exceptions\DomainException;
use App\Models\Event;

class CloseRegistration
{
    public function execute(Event $event): Event
    {
        if ($event->status !== EventStatus::PublishedOpen) {
            throw new DomainException('Registration can only be closed for events with open registration.');
        }

        $event->update(['status' => EventStatus::PublishedClosed]);

        return $event->refresh();
    }
}
