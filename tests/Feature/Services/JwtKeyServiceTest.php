<?php

use App\Exceptions\InvalidTicketException;
use App\Services\JwtKeyService;
use Carbon\Carbon;
use Firebase\JWT\JWT;

beforeEach(function () {
    $this->service = new JwtKeyService;
});

afterEach(function () {
    Carbon::setTestNow();
});

// A2: Key Derivation

it('derives key using HMAC of event_id:period_date and APP_KEY', function () {
    Carbon::setTestNow('2025-06-15 10:00:00');

    $key = $this->service->deriveKey(42);

    $expected = hash_hmac('sha256', '42:2025-06-15', config('app.key'));
    expect($key)->toBe($expected);
});

it('rotates period at 4am, not midnight', function () {
    // At 3:59am, should use previous day's period
    Carbon::setTestNow('2025-06-15 03:59:00');
    $keyBefore4am = $this->service->deriveKey(1);

    // At 4:00am, should use current day's period
    Carbon::setTestNow('2025-06-15 04:00:00');
    $keyAt4am = $this->service->deriveKey(1);

    expect($keyBefore4am)->not->toBe($keyAt4am);

    // Before 4am uses previous day
    $expectedBefore = hash_hmac('sha256', '1:2025-06-14', config('app.key'));
    expect($keyBefore4am)->toBe($expectedBefore);

    // At 4am uses current day
    $expectedAt = hash_hmac('sha256', '1:2025-06-15', config('app.key'));
    expect($keyAt4am)->toBe($expectedAt);
});

it('derives different keys for different events', function () {
    Carbon::setTestNow('2025-06-15 10:00:00');

    $key1 = $this->service->deriveKey(1);
    $key2 = $this->service->deriveKey(2);

    expect($key1)->not->toBe($key2);
});

it('derives different keys for different periods', function () {
    Carbon::setTestNow('2025-06-15 10:00:00');
    $key1 = $this->service->deriveKey(1);

    Carbon::setTestNow('2025-06-16 10:00:00');
    $key2 = $this->service->deriveKey(1);

    expect($key1)->not->toBe($key2);
});

it('returns current period date', function () {
    Carbon::setTestNow('2025-06-15 10:00:00');
    expect($this->service->currentPeriodDate())->toBe('2025-06-15');

    Carbon::setTestNow('2025-06-15 03:59:00');
    expect($this->service->currentPeriodDate())->toBe('2025-06-14');
});

it('returns previous period date', function () {
    Carbon::setTestNow('2025-06-15 10:00:00');
    expect($this->service->previousPeriodDate())->toBe('2025-06-14');

    Carbon::setTestNow('2025-06-15 03:59:00');
    expect($this->service->previousPeriodDate())->toBe('2025-06-13');
});

// A3: Dual-Key Validation

it('validates JWT with current period key', function () {
    $key = $this->service->deriveKey(5);
    $payload = ['volunteer_id' => 1, 'event_id' => 5, 'iat' => time()];
    $jwt = JWT::encode($payload, $key, 'HS256');

    $decoded = $this->service->validateToken($jwt, 5);

    expect($decoded->volunteer_id)->toBe(1)
        ->and($decoded->event_id)->toBe(5);
});

it('validates JWT with previous period key (dual-key fallback)', function () {
    $previousKey = $this->service->previousPeriodKey(5);
    $payload = ['volunteer_id' => 1, 'event_id' => 5, 'iat' => time()];
    $jwt = JWT::encode($payload, $previousKey, 'HS256');

    $decoded = $this->service->validateToken($jwt, 5);

    expect($decoded->volunteer_id)->toBe(1)
        ->and($decoded->event_id)->toBe(5);
});

it('rejects JWT signed with wrong key', function () {
    $wrongKey = hash_hmac('sha256', 'totally-wrong-data', 'totally-wrong-secret');
    $jwt = JWT::encode(
        ['volunteer_id' => 1, 'event_id' => 5, 'iat' => time()],
        $wrongKey,
        'HS256',
    );

    $this->service->validateToken($jwt, 5);
})->throws(InvalidTicketException::class);

it('rejects malformed JWT', function () {
    $this->service->validateToken('not-a-jwt', 5);
})->throws(InvalidTicketException::class);
