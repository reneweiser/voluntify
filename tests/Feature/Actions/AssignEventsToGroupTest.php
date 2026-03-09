<?php

use App\Actions\AssignEventsToGroup;
use App\Exceptions\DomainException;
use App\Models\Event;
use App\Models\EventGroup;
use App\Models\Organization;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->group = EventGroup::factory()->for($this->org)->create();
});

it('assigns events to the group', function () {
    $events = Event::factory()->for($this->org)->count(2)->create();

    $action = new AssignEventsToGroup;
    $action->execute($this->group, $events->pluck('id')->all());

    expect($events[0]->fresh()->event_group_id)->toBe($this->group->id)
        ->and($events[1]->fresh()->event_group_id)->toBe($this->group->id);
});

it('is additive — does not remove existing group members', function () {
    $existing = Event::factory()->for($this->org)->create(['event_group_id' => $this->group->id]);
    $newEvent = Event::factory()->for($this->org)->create();

    $action = new AssignEventsToGroup;
    $action->execute($this->group, [$newEvent->id]);

    expect($existing->fresh()->event_group_id)->toBe($this->group->id)
        ->and($newEvent->fresh()->event_group_id)->toBe($this->group->id);
});

it('reassigns event from another group silently', function () {
    $otherGroup = EventGroup::factory()->for($this->org)->create();
    $event = Event::factory()->for($this->org)->create(['event_group_id' => $otherGroup->id]);

    $action = new AssignEventsToGroup;
    $action->execute($this->group, [$event->id]);

    expect($event->fresh()->event_group_id)->toBe($this->group->id);
});

it('throws DomainException for cross-org events', function () {
    $otherOrg = Organization::factory()->create();
    $event = Event::factory()->for($otherOrg)->create();

    $action = new AssignEventsToGroup;

    expect(fn () => $action->execute($this->group, [$event->id]))
        ->toThrow(DomainException::class);
});
