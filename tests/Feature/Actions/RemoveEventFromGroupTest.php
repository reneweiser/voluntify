<?php

use App\Actions\RemoveEventFromGroup;
use App\Models\Event;
use App\Models\EventGroup;
use App\Models\Organization;

it('sets event_group_id to null', function () {
    $org = Organization::factory()->create();
    $group = EventGroup::factory()->for($org)->create();
    $event = Event::factory()->for($org)->create(['event_group_id' => $group->id]);

    $action = new RemoveEventFromGroup;
    $action->execute($event);

    expect($event->fresh()->event_group_id)->toBeNull();
});

it('handles event already without a group', function () {
    $event = Event::factory()->for(Organization::factory())->create(['event_group_id' => null]);

    $action = new RemoveEventFromGroup;
    $action->execute($event);

    expect($event->fresh()->event_group_id)->toBeNull();
});
