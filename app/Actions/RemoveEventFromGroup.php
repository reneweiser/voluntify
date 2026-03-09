<?php

namespace App\Actions;

use App\Models\Event;

class RemoveEventFromGroup
{
    public function execute(Event $event): void
    {
        $event->update(['event_group_id' => null]);
    }
}
