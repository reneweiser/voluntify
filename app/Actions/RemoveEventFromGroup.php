<?php

namespace App\Actions;

use App\Events\Activity\EventRemovedFromGroup;
use App\Models\Event;

class RemoveEventFromGroup
{
    public function execute(Event $event): void
    {
        $event->loadMissing('eventGroup');

        if (! $event->eventGroup) {
            return;
        }

        $groupName = $event->eventGroup->name;
        $organizationId = $event->eventGroup->organization_id;

        $event->update(['event_group_id' => null]);

        if (auth()->user()) {
            EventRemovedFromGroup::dispatch($groupName, $organizationId, $event, auth()->user());
        }
    }
}
