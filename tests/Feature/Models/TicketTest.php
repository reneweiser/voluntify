<?php

use App\Models\Event;
use App\Models\Ticket;
use App\Models\Volunteer;

it('enforces unique volunteer per event', function () {
    $volunteer = Volunteer::factory()->create();
    $event = Event::factory()->create();

    Ticket::factory()->for($volunteer)->for($event)->create();

    expect(fn () => Ticket::factory()->for($volunteer)->for($event)->create())
        ->toThrow(\Illuminate\Database\UniqueConstraintViolationException::class);
});
