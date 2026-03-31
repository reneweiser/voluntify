<?php

use App\Actions\CloseRegistration;
use App\Enums\EventStatus;
use App\Exceptions\DomainException;
use App\Models\Event;
use App\Models\Organization;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->action = new CloseRegistration;
});

it('closes registration for a published open event', function () {
    $event = Event::factory()->for($this->org)->published()->create();

    $closed = $this->action->execute($event);

    expect($closed->status)->toBe(EventStatus::PublishedClosed);
});

it('throws DomainException for a draft event', function () {
    $event = Event::factory()->for($this->org)->create();

    expect(fn () => $this->action->execute($event))
        ->toThrow(DomainException::class, 'Registration can only be closed for events with open registration.');
});

it('throws DomainException for an archived event', function () {
    $event = Event::factory()->for($this->org)->archived()->create();

    expect(fn () => $this->action->execute($event))
        ->toThrow(DomainException::class, 'Registration can only be closed for events with open registration.');
});

it('throws DomainException for an already closed event', function () {
    $event = Event::factory()->for($this->org)->publishedClosed()->create();

    expect(fn () => $this->action->execute($event))
        ->toThrow(DomainException::class, 'Registration can only be closed for events with open registration.');
});
