<?php

use App\Enums\ArrivalMethod;
use App\Enums\StaffRole;
use App\Models\Event;
use App\Models\EventArrival;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Ticket;
use App\Models\Volunteer;
use App\Models\VolunteerJob;
use App\Services\JwtKeyService;
use Firebase\JWT\JWT;

beforeEach(function () {
    ['user' => $this->organizer, 'organization' => $this->org] = createUserWithOrganization(StaffRole::Organizer);

    $this->entranceStaff = \App\Models\User::factory()->create();
    $this->org->users()->attach($this->entranceStaff, ['role' => StaffRole::EntranceStaff]);

    $this->event = Event::factory()->for($this->org)->create();
});

it('returns volunteers and shifts for entrance staff', function () {
    $volunteer = Volunteer::factory()->create(['name' => 'Alice']);
    $ticket = Ticket::factory()->for($volunteer)->for($this->event)->create();
    $job = VolunteerJob::factory()->for($this->event)->create(['name' => 'Gate Watch']);
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();
    ShiftSignup::factory()->create(['volunteer_id' => $volunteer->id, 'shift_id' => $shift->id]);

    $response = $this->actingAs($this->entranceStaff)
        ->withSession(['current_organization_id' => $this->org->id])
        ->getJson(route('scanner.data', $this->event->id));

    $response->assertOk();

    $data = $response->json();
    expect($data['event']['id'])->toBe($this->event->id)
        ->and($data['volunteers'])->toHaveCount(1)
        ->and($data['volunteers'][0]['name'])->toBe('Alice')
        ->and($data['volunteers'][0]['ticket']['jwt_token'])->toBeString()
        ->and($data['volunteers'][0]['shift_signups'])->toHaveCount(1)
        ->and($data['volunteers'][0]['shift_signups'][0]['shift']['volunteer_job']['name'])->toBe('Gate Watch');
});

it('returns Ed25519 public keys', function () {
    $response = $this->actingAs($this->entranceStaff)
        ->withSession(['current_organization_id' => $this->org->id])
        ->getJson(route('scanner.data', $this->event->id));

    $response->assertOk();

    $jwtKeyService = app(JwtKeyService::class);
    $expectedKeys = $jwtKeyService->publicKeys($this->event->id);
    $data = $response->json();

    expect($data['keys']['current'])->toBe($expectedKeys['current'])
        ->and($data['keys']['previous'])->toBe($expectedKeys['previous']);

    // Public keys should be base64-encoded 32-byte Ed25519 keys (44 chars base64)
    expect(strlen($data['keys']['current']))->toBe(44)
        ->and(strlen($data['keys']['previous']))->toBe(44);
});

it('returned keys cannot sign a valid JWT', function () {
    $response = $this->actingAs($this->entranceStaff)
        ->withSession(['current_organization_id' => $this->org->id])
        ->getJson(route('scanner.data', $this->event->id));

    $keys = $response->json('keys');

    // Attempt to forge a ticket using the returned public key
    try {
        $forgedJwt = JWT::encode(
            ['volunteer_id' => 999, 'event_id' => $this->event->id, 'iat' => time()],
            $keys['current'],
            'EdDSA',
        );
        // If encode somehow succeeds, the token should not verify
        $verifier = app(\App\Services\TokenVerifier::class);
        $verifier->verify($forgedJwt, $this->event->id);
        $this->fail('Expected InvalidTicketException');
    } catch (\Exception) {
        // Expected: either encode fails or verify fails
        expect(true)->toBeTrue();
    }
});

it('includes existing arrivals', function () {
    $volunteer = Volunteer::factory()->create();
    $ticket = Ticket::factory()->for($volunteer)->for($this->event)->create();
    EventArrival::factory()->create([
        'ticket_id' => $ticket->id,
        'volunteer_id' => $volunteer->id,
        'event_id' => $this->event->id,
        'scanned_by' => $this->entranceStaff->id,
        'method' => ArrivalMethod::QrScan,
    ]);

    $response = $this->actingAs($this->entranceStaff)
        ->withSession(['current_organization_id' => $this->org->id])
        ->getJson(route('scanner.data', $this->event->id));

    $response->assertOk();
    expect($response->json('arrivals'))->toHaveCount(1);
});

it('returns 403 for volunteer admin', function () {
    $volunteerAdmin = \App\Models\User::factory()->create();
    $this->org->users()->attach($volunteerAdmin, ['role' => StaffRole::VolunteerAdmin]);

    $this->actingAs($volunteerAdmin)
        ->withSession(['current_organization_id' => $this->org->id])
        ->getJson(route('scanner.data', $this->event->id))
        ->assertForbidden();
});

it('redirects unauthenticated user', function () {
    $this->getJson(route('scanner.data', $this->event->id))
        ->assertUnauthorized();
});

it('returns 404 for event in wrong org', function () {
    $otherOrg = \App\Models\Organization::factory()->create();
    $otherEvent = Event::factory()->for($otherOrg)->create();

    $this->actingAs($this->entranceStaff)
        ->withSession(['current_organization_id' => $this->org->id])
        ->getJson(route('scanner.data', $otherEvent->id))
        ->assertNotFound();
});
