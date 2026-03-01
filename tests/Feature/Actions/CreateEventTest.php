<?php

use App\Actions\CreateEvent;
use App\Enums\EventStatus;
use App\Models\Organization;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->org = Organization::factory()->create();
});

it('creates a draft event for the organization', function () {
    $action = new CreateEvent($this->org);

    $event = $action->execute(
        name: 'Summer Carnival',
        description: 'A fun summer event',
        location: 'Central Park',
        startsAt: Carbon::parse('2026-07-01 10:00'),
        endsAt: Carbon::parse('2026-07-01 18:00'),
    );

    expect($event->exists)->toBeTrue()
        ->and($event->organization_id)->toBe($this->org->id)
        ->and($event->name)->toBe('Summer Carnival')
        ->and($event->status)->toBe(EventStatus::Draft)
        ->and($event->description)->toBe('A fun summer event')
        ->and($event->location)->toBe('Central Park');
});

it('generates a slug from the event name', function () {
    $action = new CreateEvent($this->org);

    $event = $action->execute(
        name: 'My Great Event',
        description: null,
        location: null,
        startsAt: Carbon::parse('2026-07-01 10:00'),
        endsAt: Carbon::parse('2026-07-01 18:00'),
    );

    expect($event->slug)->toBe('my-great-event');
});

it('auto-generates a public token', function () {
    $action = new CreateEvent($this->org);

    $event = $action->execute(
        name: 'Token Event',
        description: null,
        location: null,
        startsAt: Carbon::parse('2026-07-01 10:00'),
        endsAt: Carbon::parse('2026-07-01 18:00'),
    );

    expect($event->public_token)->toBeString()
        ->and(strlen($event->public_token))->toBe(32);
});

it('allows nullable description and location', function () {
    $action = new CreateEvent($this->org);

    $event = $action->execute(
        name: 'Minimal Event',
        description: null,
        location: null,
        startsAt: Carbon::parse('2026-07-01 10:00'),
        endsAt: Carbon::parse('2026-07-01 18:00'),
    );

    expect($event->description)->toBeNull()
        ->and($event->location)->toBeNull();
});
