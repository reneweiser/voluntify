<?php

use App\Actions\GenerateTicket;
use App\Actions\RefreshTicketJwt;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Volunteer;
use App\Services\JwtKeyService;
use Carbon\Carbon;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->project = Project::factory()->for($this->org)->create();
    $this->event = Event::factory()->for($this->org)->for($this->project)->create();
    $this->volunteer = Volunteer::factory()->for($this->project)->create();
    $this->jwtKeyService = app(JwtKeyService::class);
});

it('re-signs a ticket with the current period key', function () {
    Carbon::setTestNow('2026-04-07 10:00:00');
    $ticket = app(GenerateTicket::class)->execute($this->volunteer, $this->event);
    $oldJwt = $ticket->jwt_token;

    // Advance to a new period (next day)
    Carbon::setTestNow('2026-04-08 10:00:00');
    app(RefreshTicketJwt::class)->execute($ticket);

    expect($ticket->jwt_token)->not->toBe($oldJwt);

    // New JWT should verify with the current period key
    $publicKey = $this->jwtKeyService->publicKey($this->project->id);
    $decoded = JWT::decode($ticket->jwt_token, new Key($publicKey, 'EdDSA'));

    expect($decoded->volunteer_id)->toBe($this->volunteer->id)
        ->and($decoded->project_id)->toBe($this->project->id);
});

it('produces a JWT that the scanner can verify after key rotation', function () {
    // Ticket created 3 days ago
    Carbon::setTestNow('2026-04-05 10:00:00');
    $ticket = app(GenerateTicket::class)->execute($this->volunteer, $this->event);

    // Fast-forward: scanner has only current + previous keys
    Carbon::setTestNow('2026-04-08 10:00:00');
    $currentPublicKey = $this->jwtKeyService->publicKey($this->project->id);

    // Old JWT should NOT verify with current key
    $oldVerified = false;
    try {
        JWT::decode($ticket->jwt_token, new Key($currentPublicKey, 'EdDSA'));
        $oldVerified = true;
    } catch (Exception) {
    }
    expect($oldVerified)->toBeFalse();

    // After refresh, it should verify
    app(RefreshTicketJwt::class)->execute($ticket);
    $decoded = JWT::decode($ticket->jwt_token, new Key($currentPublicKey, 'EdDSA'));

    expect($decoded->volunteer_id)->toBe($this->volunteer->id);
});

it('preserves volunteer_id and project_id in refreshed JWT', function () {
    $ticket = app(GenerateTicket::class)->execute($this->volunteer, $this->event);
    app(RefreshTicketJwt::class)->execute($ticket);

    $publicKey = $this->jwtKeyService->publicKey($this->project->id);
    $decoded = JWT::decode($ticket->jwt_token, new Key($publicKey, 'EdDSA'));

    expect($decoded->volunteer_id)->toBe($this->volunteer->id)
        ->and($decoded->project_id)->toBe($this->project->id);
});
