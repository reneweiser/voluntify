<?php

namespace App\Actions;

use App\Exceptions\DomainException;
use App\Models\Event;
use App\Models\EventGroup;

class AssignEventsToGroup
{
    /**
     * @param  array<int>  $eventIds
     */
    public function execute(EventGroup $eventGroup, array $eventIds): void
    {
        $events = Event::whereIn('id', $eventIds)->get();

        foreach ($events as $event) {
            if ($event->organization_id !== $eventGroup->organization_id) {
                throw new DomainException('Cannot assign events from a different organization to this group.');
            }
        }

        Event::whereIn('id', $eventIds)->update(['event_group_id' => $eventGroup->id]);
    }
}
