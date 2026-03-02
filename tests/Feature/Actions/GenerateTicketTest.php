<?php

use App\Actions\GenerateTicket;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\Volunteer;
use App\Services\JwtKeyService;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->event = Event::factory()->for($this->org)->create();
    $this->volunteer = Volunteer::factory()->create();
    $this->action = app(GenerateTicket::class);
});

it('creates a ticket with a valid JWT', function () {
    $ticket = $this->action->execute($this->volunteer, $this->event);

    expect($ticket->exists)->toBeTrue()
        ->and($ticket->volunteer_id)->toBe($this->volunteer->id)
        ->and($ticket->event_id)->toBe($this->event->id)
        ->and($ticket->jwt_token)->toStartWith('eyJ');
});

it('creates a JWT that JwtKeyService can validate', function () {
    $ticket = $this->action->execute($this->volunteer, $this->event);

    $jwtKeyService = app(JwtKeyService::class);
    $decoded = $jwtKeyService->validateToken($ticket->jwt_token, $this->event->id);

    expect($decoded->volunteer_id)->toBe($this->volunteer->id)
        ->and($decoded->event_id)->toBe($this->event->id)
        ->and($decoded->iat)->toBeInt();
});

it('returns existing ticket for same volunteer and event', function () {
    $first = $this->action->execute($this->volunteer, $this->event);
    $second = $this->action->execute($this->volunteer, $this->event);

    expect($first->id)->toBe($second->id)
        ->and(Ticket::count())->toBe(1);
});

it('creates separate tickets for different events', function () {
    $otherEvent = Event::factory()->for($this->org)->create();

    $first = $this->action->execute($this->volunteer, $this->event);
    $second = $this->action->execute($this->volunteer, $otherEvent);

    expect($first->id)->not->toBe($second->id)
        ->and(Ticket::count())->toBe(2);
});
