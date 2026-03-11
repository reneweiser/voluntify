<?php

use App\Actions\GenerateTicket;
use App\Enums\StaffRole;
use App\Models\Event;
use App\Models\EventArrival;
use App\Models\Volunteer;
use App\Services\JwtKeyService;
use App\Services\TokenVerifier;
use Firebase\JWT\JWT;

beforeEach(function () {
    ['user' => $this->organizer, 'organization' => $this->org] = createUserWithOrganization(StaffRole::Organizer);

    $this->entranceStaff = \App\Models\User::factory()->create();
    $this->org->users()->attach($this->entranceStaff, ['role' => StaffRole::EntranceStaff]);

    $this->event = Event::factory()->for($this->org)->create();
    $this->volunteer = Volunteer::factory()->create();
});

it('full round-trip: generate EdDSA ticket → scanner data → verify → sync → arrival', function () {
    // 1. Generate EdDSA ticket
    $ticket = app(GenerateTicket::class)->execute($this->volunteer, $this->event);
    expect($ticket->jwt_token)->toStartWith('eyJ');

    // Verify it's EdDSA
    $parts = explode('.', $ticket->jwt_token);
    $header = json_decode(base64_decode(strtr($parts[0], '-_', '+/')), true);
    expect($header['alg'])->toBe('EdDSA');

    // 2. GET scanner data — capture public keys
    $response = $this->actingAs($this->entranceStaff)
        ->withSession(['current_organization_id' => $this->org->id])
        ->getJson(route('scanner.data', $this->event->id));

    $response->assertOk();
    $keys = $response->json('keys');

    // Keys should be 44-char base64 (32 bytes), NOT 64-char hex (HMAC)
    expect(strlen($keys['current']))->toBe(44)
        ->and(strlen($keys['previous']))->toBe(44);

    // 3. Verify JWT validates against returned public keys
    $verifier = app(TokenVerifier::class);
    $decoded = $verifier->verify($ticket->jwt_token, $this->event->id);
    expect($decoded->volunteer_id)->toBe($this->volunteer->id);

    // 4. POST sync with jwt_token — arrival recorded
    $syncResponse = $this->actingAs($this->entranceStaff)
        ->withSession(['current_organization_id' => $this->org->id])
        ->postJson(route('scanner.sync', $this->event->id), [
            'arrivals' => [
                [
                    'ticket_id' => $ticket->id,
                    'method' => 'qr_scan',
                    'scanned_at' => now()->toDateTimeString(),
                    'jwt_token' => $ticket->jwt_token,
                ],
            ],
        ]);

    $syncResponse->assertOk();
    expect(EventArrival::count())->toBe(1)
        ->and($syncResponse->json('rejected'))->toBeEmpty();
});

it('rejects forged jwt_token in sync', function () {
    $ticket = app(GenerateTicket::class)->execute($this->volunteer, $this->event);

    // Forge a JWT with random key
    $keypair = sodium_crypto_sign_keypair();
    $forgedKey = base64_encode(sodium_crypto_sign_secretkey($keypair));
    $forgedJwt = JWT::encode(
        ['volunteer_id' => $this->volunteer->id, 'event_id' => $this->event->id, 'iat' => time()],
        $forgedKey,
        'EdDSA',
    );

    $response = $this->actingAs($this->entranceStaff)
        ->withSession(['current_organization_id' => $this->org->id])
        ->postJson(route('scanner.sync', $this->event->id), [
            'arrivals' => [
                [
                    'ticket_id' => $ticket->id,
                    'method' => 'qr_scan',
                    'scanned_at' => now()->toDateTimeString(),
                    'jwt_token' => $forgedJwt,
                ],
            ],
        ]);

    $response->assertOk();
    expect(EventArrival::count())->toBe(0)
        ->and($response->json('rejected'))->toHaveCount(1);
});

it('full round-trip with legacy HMAC ticket (backward compat)', function () {
    // Create an HMAC ticket manually (simulating existing DB record)
    $jwtKeyService = app(JwtKeyService::class);
    $hmacKey = $jwtKeyService->deriveKey($this->event->id);
    $hmacJwt = JWT::encode(
        ['volunteer_id' => $this->volunteer->id, 'event_id' => $this->event->id, 'iat' => time()],
        $hmacKey,
        'HS256',
    );

    $ticket = \App\Models\Ticket::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'event_id' => $this->event->id,
        'jwt_token' => $hmacJwt,
    ]);

    // Server-side TokenVerifier should still validate it
    $verifier = app(TokenVerifier::class);
    $decoded = $verifier->verify($ticket->jwt_token, $this->event->id);
    expect($decoded->volunteer_id)->toBe($this->volunteer->id);

    // Sync with legacy jwt_token should succeed
    $response = $this->actingAs($this->entranceStaff)
        ->withSession(['current_organization_id' => $this->org->id])
        ->postJson(route('scanner.sync', $this->event->id), [
            'arrivals' => [
                [
                    'ticket_id' => $ticket->id,
                    'method' => 'qr_scan',
                    'scanned_at' => now()->toDateTimeString(),
                    'jwt_token' => $hmacJwt,
                ],
            ],
        ]);

    $response->assertOk();
    expect(EventArrival::count())->toBe(1)
        ->and($response->json('rejected'))->toBeEmpty();
});
