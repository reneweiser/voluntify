<?php

use App\Actions\RevertEventToDraft;
use App\Enums\EventStatus;
use App\Exceptions\DomainException;
use App\Models\Event;
use App\Models\Organization;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->action = new RevertEventToDraft;
});

it('reverts a published event to draft', function () {
    $event = Event::factory()->for($this->org)->published()->create();

    $reverted = $this->action->execute($event);

    expect($reverted->status)->toBe(EventStatus::Draft);
});

it('reverts a published closed event to draft', function () {
    $event = Event::factory()->for($this->org)->publishedClosed()->create();

    $reverted = $this->action->execute($event);

    expect($reverted->status)->toBe(EventStatus::Draft);
});

it('reverts an archived event to draft', function () {
    $event = Event::factory()->for($this->org)->archived()->create();

    $reverted = $this->action->execute($event);

    expect($reverted->status)->toBe(EventStatus::Draft);
});

it('cannot revert an already draft event', function () {
    $event = Event::factory()->for($this->org)->create();

    expect(fn () => $this->action->execute($event))
        ->toThrow(DomainException::class, 'Event is already a draft.');
});
