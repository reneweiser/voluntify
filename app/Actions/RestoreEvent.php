<?php

namespace App\Actions;

use App\Exceptions\DomainException;
use App\Models\Event;

class RestoreEvent
{
    public function execute(Event $event): Event
    {
        if (! $event->isPendingDeletion()) {
            throw new DomainException('Event ist nicht zur Löschung vorgemerkt.');
        }

        $event->update(['deletion_requested_at' => null]);

        return $event->refresh();
    }
}
