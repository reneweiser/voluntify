<?php

use App\Enums\ArrivalMethod;
use App\Enums\StaffRole;
use App\Models\Event;
use App\Models\EventArrival;
use App\Models\Ticket;
use App\Models\Volunteer;
use App\Services\JwtKeyService;
use Firebase\JWT\JWT;

beforeEach(function () {
    ['user' => $this->organizer, 'organization' => $this->org] = createUserWithOrganization(StaffRole::Organizer);

    $this->entranceStaff = \App\Models\User::factory()->create();
    $this->org->users()->attach($this->entranceStaff, ['role' => StaffRole::EntranceStaff]);

    $this->event = Event::factory()->for($this->org)->create();
    $this->volunteer = Volunteer::factory()->create();
    $this->ticket = Ticket::factory()->for($this->volunteer)->for($this->event)->create();
});

it('syncs a single arrival', function () {
    $response = $this->actingAs($this->entranceStaff)
        ->withSession(['current_organization_id' => $this->org->id])
        ->postJson(route('scanner.sync', $this->event->id), [
            'arrivals' => [
                [
                    'ticket_id' => $this->ticket->id,
                    'method' => 'qr_scan',
                    'scanned_at' => '2025-06-15 10:00:00',
                ],
            ],
        ]);

    $response->assertOk();
    expect(EventArrival::count())->toBe(1);

    $arrival = EventArrival::first();
    expect($arrival->ticket_id)->toBe($this->ticket->id)
        ->and($arrival->volunteer_id)->toBe($this->volunteer->id)
        ->and($arrival->event_id)->toBe($this->event->id)
        ->and($arrival->method)->toBe(ArrivalMethod::QrScan)
        ->and($arrival->scanned_at->toDateTimeString())->toBe('2025-06-15 10:00:00');
});

it('syncs batch of arrivals', function () {
    $volunteer2 = Volunteer::factory()->create();
    $ticket2 = Ticket::factory()->for($volunteer2)->for($this->event)->create();

    $response = $this->actingAs($this->entranceStaff)
        ->withSession(['current_organization_id' => $this->org->id])
        ->postJson(route('scanner.sync', $this->event->id), [
            'arrivals' => [
                [
                    'ticket_id' => $this->ticket->id,
                    'method' => 'qr_scan',
                    'scanned_at' => '2025-06-15 10:00:00',
                ],
                [
                    'ticket_id' => $ticket2->id,
                    'method' => 'manual_lookup',
                    'scanned_at' => '2025-06-15 10:05:00',
                ],
            ],
        ]);

    $response->assertOk();
    expect(EventArrival::count())->toBe(2);
});

it('handles duplicate arrivals gracefully', function () {
    // First sync
    $this->actingAs($this->entranceStaff)
        ->withSession(['current_organization_id' => $this->org->id])
        ->postJson(route('scanner.sync', $this->event->id), [
            'arrivals' => [
                [
                    'ticket_id' => $this->ticket->id,
                    'method' => 'qr_scan',
                    'scanned_at' => '2025-06-15 10:00:00',
                ],
            ],
        ]);

    // Second sync with same ticket
    $response = $this->actingAs($this->entranceStaff)
        ->withSession(['current_organization_id' => $this->org->id])
        ->postJson(route('scanner.sync', $this->event->id), [
            'arrivals' => [
                [
                    'ticket_id' => $this->ticket->id,
                    'method' => 'qr_scan',
                    'scanned_at' => '2025-06-15 10:30:00',
                ],
            ],
        ]);

    $response->assertOk();
    expect(EventArrival::count())->toBe(2);

    $flagged = EventArrival::where('flagged', true)->first();
    expect($flagged)->not->toBeNull();
});

it('returns updated arrivals after sync', function () {
    $response = $this->actingAs($this->entranceStaff)
        ->withSession(['current_organization_id' => $this->org->id])
        ->postJson(route('scanner.sync', $this->event->id), [
            'arrivals' => [
                [
                    'ticket_id' => $this->ticket->id,
                    'method' => 'qr_scan',
                    'scanned_at' => '2025-06-15 10:00:00',
                ],
            ],
        ]);

    $response->assertOk();
    expect($response->json('arrivals'))->toHaveCount(1);
});

it('validates input', function () {
    $this->actingAs($this->entranceStaff)
        ->withSession(['current_organization_id' => $this->org->id])
        ->postJson(route('scanner.sync', $this->event->id), [
            'arrivals' => [
                [
                    'ticket_id' => null,
                    'method' => 'invalid_method',
                ],
            ],
        ])
        ->assertUnprocessable();
});

it('rejects syncing a ticket from a different event', function () {
    $otherEvent = Event::factory()->for($this->org)->create();
    $otherVolunteer = Volunteer::factory()->create();
    $otherTicket = Ticket::factory()->for($otherVolunteer)->for($otherEvent)->create();

    $response = $this->actingAs($this->entranceStaff)
        ->withSession(['current_organization_id' => $this->org->id])
        ->postJson(route('scanner.sync', $this->event->id), [
            'arrivals' => [
                [
                    'ticket_id' => $otherTicket->id,
                    'method' => 'qr_scan',
                    'scanned_at' => '2025-06-15 10:00:00',
                ],
            ],
        ]);

    $response->assertNotFound();
    expect(EventArrival::count())->toBe(0);
});

it('returns 403 for unauthorized user', function () {
    $volunteerAdmin = \App\Models\User::factory()->create();
    $this->org->users()->attach($volunteerAdmin, ['role' => StaffRole::VolunteerAdmin]);

    $this->actingAs($volunteerAdmin)
        ->withSession(['current_organization_id' => $this->org->id])
        ->postJson(route('scanner.sync', $this->event->id), [
            'arrivals' => [
                [
                    'ticket_id' => $this->ticket->id,
                    'method' => 'qr_scan',
                    'scanned_at' => '2025-06-15 10:00:00',
                ],
            ],
        ])
        ->assertForbidden();
});

// JWT validation tests

it('accepts arrival with valid EdDSA jwt_token', function () {
    $jwtKeyService = app(JwtKeyService::class);
    $signingKey = $jwtKeyService->signingKey($this->event->id);

    $jwt = JWT::encode(
        ['volunteer_id' => $this->volunteer->id, 'event_id' => $this->event->id, 'iat' => time()],
        $signingKey,
        'EdDSA',
    );

    $response = $this->actingAs($this->entranceStaff)
        ->withSession(['current_organization_id' => $this->org->id])
        ->postJson(route('scanner.sync', $this->event->id), [
            'arrivals' => [
                [
                    'ticket_id' => $this->ticket->id,
                    'method' => 'qr_scan',
                    'scanned_at' => '2025-06-15 10:00:00',
                    'jwt_token' => $jwt,
                ],
            ],
        ]);

    $response->assertOk();
    expect(EventArrival::count())->toBe(1);
    expect($response->json('rejected'))->toBeEmpty();
});

it('accepts arrival without jwt_token (backward compat)', function () {
    $response = $this->actingAs($this->entranceStaff)
        ->withSession(['current_organization_id' => $this->org->id])
        ->postJson(route('scanner.sync', $this->event->id), [
            'arrivals' => [
                [
                    'ticket_id' => $this->ticket->id,
                    'method' => 'qr_scan',
                    'scanned_at' => '2025-06-15 10:00:00',
                ],
            ],
        ]);

    $response->assertOk();
    expect(EventArrival::count())->toBe(1);
});

it('rejects arrival with forged jwt_token', function () {
    $keypair = sodium_crypto_sign_keypair();
    $wrongKey = base64_encode(sodium_crypto_sign_secretkey($keypair));

    $forgedJwt = JWT::encode(
        ['volunteer_id' => $this->volunteer->id, 'event_id' => $this->event->id, 'iat' => time()],
        $wrongKey,
        'EdDSA',
    );

    $response = $this->actingAs($this->entranceStaff)
        ->withSession(['current_organization_id' => $this->org->id])
        ->postJson(route('scanner.sync', $this->event->id), [
            'arrivals' => [
                [
                    'ticket_id' => $this->ticket->id,
                    'method' => 'qr_scan',
                    'scanned_at' => '2025-06-15 10:00:00',
                    'jwt_token' => $forgedJwt,
                ],
            ],
        ]);

    $response->assertOk();
    expect(EventArrival::count())->toBe(0);
    expect($response->json('rejected'))->toHaveCount(1)
        ->and($response->json('rejected.0.ticket_id'))->toBe($this->ticket->id);
});

it('accepts arrival with legacy HS256 jwt_token', function () {
    $jwtKeyService = app(JwtKeyService::class);
    $hmacKey = $jwtKeyService->deriveKey($this->event->id);

    $jwt = JWT::encode(
        ['volunteer_id' => $this->volunteer->id, 'event_id' => $this->event->id, 'iat' => time()],
        $hmacKey,
        'HS256',
    );

    $response = $this->actingAs($this->entranceStaff)
        ->withSession(['current_organization_id' => $this->org->id])
        ->postJson(route('scanner.sync', $this->event->id), [
            'arrivals' => [
                [
                    'ticket_id' => $this->ticket->id,
                    'method' => 'qr_scan',
                    'scanned_at' => '2025-06-15 10:00:00',
                    'jwt_token' => $jwt,
                ],
            ],
        ]);

    $response->assertOk();
    expect(EventArrival::count())->toBe(1);
    expect($response->json('rejected'))->toBeEmpty();
});

it('handles mixed HS256/EdDSA batch', function () {
    $volunteer2 = Volunteer::factory()->create();
    $ticket2 = Ticket::factory()->for($volunteer2)->for($this->event)->create();

    $jwtKeyService = app(JwtKeyService::class);

    // EdDSA token for first ticket
    $eddsaJwt = JWT::encode(
        ['volunteer_id' => $this->volunteer->id, 'event_id' => $this->event->id, 'iat' => time()],
        $jwtKeyService->signingKey($this->event->id),
        'EdDSA',
    );

    // HS256 token for second ticket
    $hs256Jwt = JWT::encode(
        ['volunteer_id' => $volunteer2->id, 'event_id' => $this->event->id, 'iat' => time()],
        $jwtKeyService->deriveKey($this->event->id),
        'HS256',
    );

    $response = $this->actingAs($this->entranceStaff)
        ->withSession(['current_organization_id' => $this->org->id])
        ->postJson(route('scanner.sync', $this->event->id), [
            'arrivals' => [
                [
                    'ticket_id' => $this->ticket->id,
                    'method' => 'qr_scan',
                    'scanned_at' => '2025-06-15 10:00:00',
                    'jwt_token' => $eddsaJwt,
                ],
                [
                    'ticket_id' => $ticket2->id,
                    'method' => 'qr_scan',
                    'scanned_at' => '2025-06-15 10:05:00',
                    'jwt_token' => $hs256Jwt,
                ],
            ],
        ]);

    $response->assertOk();
    expect(EventArrival::count())->toBe(2);
    expect($response->json('rejected'))->toBeEmpty();
});
