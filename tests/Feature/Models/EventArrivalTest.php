<?php

use App\Enums\ArrivalMethod;
use App\Models\Event;
use App\Models\EventArrival;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Volunteer;

it('belongs to a ticket', function () {
    $arrival = EventArrival::factory()->create();

    expect($arrival->ticket)->toBeInstanceOf(Ticket::class);
});

it('belongs to a volunteer', function () {
    $volunteer = Volunteer::factory()->create();
    $arrival = EventArrival::factory()->create(['volunteer_id' => $volunteer->id]);

    expect($arrival->volunteer->id)->toBe($volunteer->id);
});

it('belongs to an event', function () {
    $event = Event::factory()->create();
    $arrival = EventArrival::factory()->create(['event_id' => $event->id]);

    expect($arrival->event->id)->toBe($event->id);
});

it('belongs to a scanner (user)', function () {
    $user = User::factory()->create();
    $arrival = EventArrival::factory()->create(['scanned_by' => $user->id]);

    expect($arrival->scanner->id)->toBe($user->id);
});

it('has nullable scanner', function () {
    $arrival = EventArrival::factory()->create(['scanned_by' => null]);

    expect($arrival->scanner)->toBeNull();
});

it('casts method to ArrivalMethod enum', function () {
    $arrival = EventArrival::factory()->create(['method' => ArrivalMethod::QrScan]);

    expect($arrival->method)->toBe(ArrivalMethod::QrScan);
});

it('casts flagged to boolean', function () {
    $arrival = EventArrival::factory()->create(['flagged' => true]);

    expect($arrival->flagged)->toBeTrue();
});

it('casts scanned_at to datetime', function () {
    $arrival = EventArrival::factory()->create();

    expect($arrival->scanned_at)->toBeInstanceOf(\Carbon\CarbonInterface::class);
});
