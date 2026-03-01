<?php

use App\Actions\ArchiveEvent;
use App\Enums\EventStatus;
use App\Exceptions\DomainException;
use App\Models\Event;
use App\Models\Organization;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->action = new ArchiveEvent;
});

it('archives a published event', function () {
    $event = Event::factory()->for($this->org)->published()->create();

    $archived = $this->action->execute($event);

    expect($archived->status)->toBe(EventStatus::Archived);
});

it('cannot archive a draft event', function () {
    $event = Event::factory()->for($this->org)->create();

    expect(fn () => $this->action->execute($event))
        ->toThrow(DomainException::class, 'Cannot archive a draft event.');
});

it('cannot archive an already archived event', function () {
    $event = Event::factory()->for($this->org)->archived()->create();

    expect(fn () => $this->action->execute($event))
        ->toThrow(DomainException::class, 'Event is already archived.');
});
