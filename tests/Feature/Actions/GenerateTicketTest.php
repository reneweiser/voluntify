<?php

use App\Actions\GenerateTicket;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\Volunteer;
use App\Services\JwtKeyService;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->project = Project::factory()->for($this->org)->create();
    $this->event = Event::factory()->for($this->org)->for($this->project)->create();
    $this->volunteer = Volunteer::factory()->for($this->project)->create();
    $this->action = app(GenerateTicket::class);
});

it('creates a ticket with an EdDSA JWT', function () {
    $ticket = $this->action->execute($this->volunteer, $this->event);

    expect($ticket->exists)->toBeTrue()
        ->and($ticket->volunteer_id)->toBe($this->volunteer->id)
        ->and($ticket->project_id)->toBe($this->project->id)
        ->and($ticket->jwt_token)->toStartWith('eyJ');

    // Decode header and verify algorithm
    $parts = explode('.', $ticket->jwt_token);
    $header = json_decode(base64_decode(strtr($parts[0], '-_', '+/')), true);
    expect($header['alg'])->toBe('EdDSA');
});

it('JWT payload contains volunteer_id, project_id, iat', function () {
    $ticket = $this->action->execute($this->volunteer, $this->event);

    $jwtKeyService = app(JwtKeyService::class);
    $publicKeyB64 = $jwtKeyService->publicKey($this->project->id);

    $decoded = JWT::decode($ticket->jwt_token, new Key($publicKeyB64, 'EdDSA'));

    expect($decoded->volunteer_id)->toBe($this->volunteer->id)
        ->and($decoded->project_id)->toBe($this->project->id)
        ->and($decoded->iat)->toBeInt();
});

it('creates a JWT that validates with the public key', function () {
    $ticket = $this->action->execute($this->volunteer, $this->event);

    $jwtKeyService = app(JwtKeyService::class);
    $publicKeyB64 = $jwtKeyService->publicKey($this->project->id);

    $decoded = JWT::decode($ticket->jwt_token, new Key($publicKeyB64, 'EdDSA'));

    expect($decoded->volunteer_id)->toBe($this->volunteer->id)
        ->and($decoded->project_id)->toBe($this->project->id);
});

it('returns existing ticket for same volunteer and project', function () {
    $first = $this->action->execute($this->volunteer, $this->event);
    $second = $this->action->execute($this->volunteer, $this->event);

    expect($first->id)->toBe($second->id)
        ->and(Ticket::count())->toBe(1);
});

it('creates separate tickets for different projects', function () {
    $otherProject = Project::factory()->for($this->org)->create();
    $otherEvent = Event::factory()->for($this->org)->for($otherProject)->create();
    $otherVolunteer = Volunteer::factory()->for($otherProject)->create();

    $first = $this->action->execute($this->volunteer, $this->event);
    $second = $this->action->execute($otherVolunteer, $otherEvent);

    expect($first->id)->not->toBe($second->id)
        ->and(Ticket::count())->toBe(2);
});
