<?php

use App\Models\Event;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\Volunteer;
use App\Services\JwtKeyService;
use App\Services\TokenVerifier;
use Firebase\JWT\JWT;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->event = Event::factory()->for($this->org)->create();
    $this->volunteer = Volunteer::factory()->create();
    $this->jwtKeyService = app(JwtKeyService::class);
});

it('re-signs HMAC ticket with EdDSA', function () {
    $hmacKey = $this->jwtKeyService->deriveKey($this->event->id);
    $payload = ['volunteer_id' => $this->volunteer->id, 'event_id' => $this->event->id, 'iat' => time()];
    $hmacJwt = JWT::encode($payload, $hmacKey, 'HS256');

    $ticket = Ticket::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'event_id' => $this->event->id,
        'jwt_token' => $hmacJwt,
    ]);

    $this->artisan('app:resign-tickets')->assertSuccessful();

    $ticket->refresh();
    $parts = explode('.', $ticket->jwt_token);
    $header = json_decode(base64_decode(strtr($parts[0], '-_', '+/')), true);

    expect($header['alg'])->toBe('EdDSA');
});

it('preserves original payload (volunteer_id, event_id, iat)', function () {
    $hmacKey = $this->jwtKeyService->deriveKey($this->event->id);
    $originalIat = time() - 3600;
    $payload = ['volunteer_id' => $this->volunteer->id, 'event_id' => $this->event->id, 'iat' => $originalIat];
    $hmacJwt = JWT::encode($payload, $hmacKey, 'HS256');

    Ticket::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'event_id' => $this->event->id,
        'jwt_token' => $hmacJwt,
    ]);

    $this->artisan('app:resign-tickets')->assertSuccessful();

    $ticket = Ticket::first();
    $verifier = app(TokenVerifier::class);
    $decoded = $verifier->verify($ticket->jwt_token, $this->event->id);

    expect($decoded->volunteer_id)->toBe($this->volunteer->id)
        ->and($decoded->event_id)->toBe($this->event->id)
        ->and($decoded->iat)->toBe($originalIat);
});

it('re-signed token validates via TokenVerifier', function () {
    $hmacKey = $this->jwtKeyService->deriveKey($this->event->id);
    $payload = ['volunteer_id' => $this->volunteer->id, 'event_id' => $this->event->id, 'iat' => time()];
    $hmacJwt = JWT::encode($payload, $hmacKey, 'HS256');

    Ticket::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'event_id' => $this->event->id,
        'jwt_token' => $hmacJwt,
    ]);

    $this->artisan('app:resign-tickets')->assertSuccessful();

    $ticket = Ticket::first();
    $verifier = app(TokenVerifier::class);
    $decoded = $verifier->verify($ticket->jwt_token, $this->event->id);

    expect($decoded->volunteer_id)->toBe($this->volunteer->id);
});

it('skips already-EdDSA tickets (idempotent)', function () {
    $signingKey = $this->jwtKeyService->signingKey($this->event->id);
    $payload = ['volunteer_id' => $this->volunteer->id, 'event_id' => $this->event->id, 'iat' => time()];
    $eddsaJwt = JWT::encode($payload, $signingKey, 'EdDSA');

    $ticket = Ticket::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'event_id' => $this->event->id,
        'jwt_token' => $eddsaJwt,
    ]);

    $originalToken = $ticket->jwt_token;

    $this->artisan('app:resign-tickets')->assertSuccessful();

    $ticket->refresh();
    expect($ticket->jwt_token)->toBe($originalToken);
});

it('running twice is a no-op', function () {
    $hmacKey = $this->jwtKeyService->deriveKey($this->event->id);
    $payload = ['volunteer_id' => $this->volunteer->id, 'event_id' => $this->event->id, 'iat' => time()];
    $hmacJwt = JWT::encode($payload, $hmacKey, 'HS256');

    Ticket::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'event_id' => $this->event->id,
        'jwt_token' => $hmacJwt,
    ]);

    $this->artisan('app:resign-tickets')->assertSuccessful();
    $tokenAfterFirst = Ticket::first()->jwt_token;

    $this->artisan('app:resign-tickets')->assertSuccessful();
    $tokenAfterSecond = Ticket::first()->jwt_token;

    expect($tokenAfterSecond)->toBe($tokenAfterFirst);
});

it('handles corrupt token gracefully (logs warning, continues)', function () {
    Ticket::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'event_id' => $this->event->id,
        'jwt_token' => 'corrupt-not-a-jwt',
    ]);

    // Create a valid HMAC ticket too
    $volunteer2 = Volunteer::factory()->create();
    $hmacKey = $this->jwtKeyService->deriveKey($this->event->id);
    $hmacJwt = JWT::encode(['volunteer_id' => $volunteer2->id, 'event_id' => $this->event->id, 'iat' => time()], $hmacKey, 'HS256');
    Ticket::factory()->create([
        'volunteer_id' => $volunteer2->id,
        'event_id' => $this->event->id,
        'jwt_token' => $hmacJwt,
    ]);

    $this->artisan('app:resign-tickets')->assertSuccessful();

    // The valid ticket should be re-signed
    $validTicket = Ticket::where('volunteer_id', $volunteer2->id)->first();
    $parts = explode('.', $validTicket->jwt_token);
    $header = json_decode(base64_decode(strtr($parts[0], '-_', '+/')), true);
    expect($header['alg'])->toBe('EdDSA');
});

it('--dry-run does not modify tickets', function () {
    $hmacKey = $this->jwtKeyService->deriveKey($this->event->id);
    $payload = ['volunteer_id' => $this->volunteer->id, 'event_id' => $this->event->id, 'iat' => time()];
    $hmacJwt = JWT::encode($payload, $hmacKey, 'HS256');

    $ticket = Ticket::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'event_id' => $this->event->id,
        'jwt_token' => $hmacJwt,
    ]);

    $this->artisan('app:resign-tickets --dry-run')->assertSuccessful();

    $ticket->refresh();
    expect($ticket->jwt_token)->toBe($hmacJwt);
});
