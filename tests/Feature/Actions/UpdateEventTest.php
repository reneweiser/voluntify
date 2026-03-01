<?php

use App\Actions\UpdateEvent;
use App\Exceptions\DomainException;
use App\Models\Event;
use App\Models\Organization;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->action = new UpdateEvent;
});

it('updates event fields', function () {
    $event = Event::factory()->for($this->org)->create();

    $updated = $this->action->execute(
        event: $event,
        name: 'Updated Name',
        description: 'New description',
        location: 'New Location',
        startsAt: Carbon::parse('2026-08-01 10:00'),
        endsAt: Carbon::parse('2026-08-01 18:00'),
    );

    expect($updated->name)->toBe('Updated Name')
        ->and($updated->slug)->toBe('updated-name')
        ->and($updated->description)->toBe('New description')
        ->and($updated->location)->toBe('New Location');
});

it('cannot update archived events', function () {
    $event = Event::factory()->for($this->org)->archived()->create();

    expect(fn () => $this->action->execute(
        event: $event,
        name: 'Updated Name',
        description: null,
        location: null,
        startsAt: Carbon::parse('2026-08-01 10:00'),
        endsAt: Carbon::parse('2026-08-01 18:00'),
    ))->toThrow(DomainException::class, 'Cannot update an archived event.');
});

it('can update published events', function () {
    $event = Event::factory()->for($this->org)->published()->create();

    $updated = $this->action->execute(
        event: $event,
        name: 'Updated Published',
        description: null,
        location: null,
        startsAt: Carbon::parse('2026-08-01 10:00'),
        endsAt: Carbon::parse('2026-08-01 18:00'),
    );

    expect($updated->name)->toBe('Updated Published');
});
