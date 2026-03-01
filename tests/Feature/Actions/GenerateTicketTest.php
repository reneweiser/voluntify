<?php

use App\Actions\GenerateTicket;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\Volunteer;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->event = Event::factory()->for($this->org)->create();
    $this->volunteer = Volunteer::factory()->create();
    $this->action = new GenerateTicket;
});

it('creates a ticket with a valid JWT', function () {
    $ticket = $this->action->execute($this->volunteer, $this->event);

    expect($ticket->exists)->toBeTrue()
        ->and($ticket->volunteer_id)->toBe($this->volunteer->id)
        ->and($ticket->event_id)->toBe($this->event->id)
        ->and($ticket->jwt_token)->toStartWith('eyJ');

    // Decode and verify JWT contents
    $key = hash_hmac('sha256', (string) $this->event->id, config('app.key'));
    $decoded = JWT::decode($ticket->jwt_token, new Key($key, 'HS256'));

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
