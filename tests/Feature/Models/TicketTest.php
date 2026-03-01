<?php

use App\Models\Event;
use App\Models\Ticket;
use App\Models\Volunteer;

it('belongs to a volunteer', function () {
    $volunteer = Volunteer::factory()->create();
    $ticket = Ticket::factory()->for($volunteer)->create();

    expect($ticket->volunteer->id)->toBe($volunteer->id);
});

it('belongs to an event', function () {
    $event = Event::factory()->create();
    $ticket = Ticket::factory()->for($event)->create();

    expect($ticket->event->id)->toBe($event->id);
});

it('enforces unique volunteer per event', function () {
    $volunteer = Volunteer::factory()->create();
    $event = Event::factory()->create();

    Ticket::factory()->for($volunteer)->for($event)->create();

    expect(fn () => Ticket::factory()->for($volunteer)->for($event)->create())
        ->toThrow(\Illuminate\Database\UniqueConstraintViolationException::class);
});
