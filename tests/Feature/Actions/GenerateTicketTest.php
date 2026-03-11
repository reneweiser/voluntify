<?php

use App\Actions\GenerateTicket;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\Volunteer;
use App\Services\JwtKeyService;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->event = Event::factory()->for($this->org)->create();
    $this->volunteer = Volunteer::factory()->create();
    $this->action = app(GenerateTicket::class);
});

it('creates a ticket with an EdDSA JWT', function () {
    $ticket = $this->action->execute($this->volunteer, $this->event);

    expect($ticket->exists)->toBeTrue()
        ->and($ticket->volunteer_id)->toBe($this->volunteer->id)
        ->and($ticket->event_id)->toBe($this->event->id)
        ->and($ticket->jwt_token)->toStartWith('eyJ');

    // Decode header and verify algorithm
    $parts = explode('.', $ticket->jwt_token);
    $header = json_decode(base64_decode(strtr($parts[0], '-_', '+/')), true);
    expect($header['alg'])->toBe('EdDSA');
});

it('JWT payload contains volunteer_id, event_id, iat', function () {
    $ticket = $this->action->execute($this->volunteer, $this->event);

    $jwtKeyService = app(JwtKeyService::class);
    $publicKeyB64 = $jwtKeyService->publicKey($this->event->id);

    $decoded = JWT::decode($ticket->jwt_token, new Key($publicKeyB64, 'EdDSA'));

    expect($decoded->volunteer_id)->toBe($this->volunteer->id)
        ->and($decoded->event_id)->toBe($this->event->id)
        ->and($decoded->iat)->toBeInt();
});

it('creates a JWT that validates with the public key', function () {
    $ticket = $this->action->execute($this->volunteer, $this->event);

    $jwtKeyService = app(JwtKeyService::class);
    $publicKeyB64 = $jwtKeyService->publicKey($this->event->id);

    $decoded = JWT::decode($ticket->jwt_token, new Key($publicKeyB64, 'EdDSA'));

    expect($decoded->volunteer_id)->toBe($this->volunteer->id)
        ->and($decoded->event_id)->toBe($this->event->id);
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
