<?php

use App\Services\JwtKeyService;
use Carbon\Carbon;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

beforeEach(function () {
    $this->service = app(JwtKeyService::class);
});

afterEach(function () {
    Carbon::setTestNow();
});

// Ed25519 keypair derivation

describe('Ed25519 keypair derivation', function () {
    it('signingKey returns base64-encoded Ed25519 private key', function () {
        Carbon::setTestNow('2025-06-15 10:00:00');

        $signingKey = $this->service->signingKey(42);

        // Ed25519 signing key is 64 bytes → 88 chars base64
        $decoded = base64_decode($signingKey, true);
        expect($decoded)->not->toBeFalse()
            ->and(strlen($decoded))->toBe(64);
    });

    it('publicKey returns base64-encoded 32-byte Ed25519 public key', function () {
        Carbon::setTestNow('2025-06-15 10:00:00');

        $publicKey = $this->service->publicKey(42);

        $decoded = base64_decode($publicKey, true);
        expect($decoded)->not->toBeFalse()
            ->and(strlen($decoded))->toBe(32);
    });

    it('signingKey is deterministic for same event and period', function () {
        Carbon::setTestNow('2025-06-15 10:00:00');

        $key1 = $this->service->signingKey(42);
        $key2 = $this->service->signingKey(42);

        expect($key1)->toBe($key2);
    });

    it('publicKeys returns current and previous public keys', function () {
        Carbon::setTestNow('2025-06-15 10:00:00');

        $keys = $this->service->publicKeys(42);

        expect($keys)->toHaveKeys(['current', 'previous'])
            ->and($keys['current'])->toBe($this->service->publicKey(42))
            ->and(strlen(base64_decode($keys['previous'], true)))->toBe(32);
    });

    it('publicKey cannot sign a valid JWT', function () {
        Carbon::setTestNow('2025-06-15 10:00:00');

        $publicKeyB64 = $this->service->publicKey(42);

        // Attempt to use the public key (32 bytes base64) as a signing key (needs 64 bytes)
        // firebase/php-jwt base64-decodes internally, so pass the base64 string directly
        JWT::encode(['test' => 'data'], $publicKeyB64, 'EdDSA');
    })->throws(\Exception::class);

    it('sodium_crypto_sign_seed_keypair is available', function () {
        expect(function_exists('sodium_crypto_sign_seed_keypair'))->toBeTrue();
    });

    it('produces different keys for different events', function () {
        Carbon::setTestNow('2025-06-15 10:00:00');

        expect($this->service->signingKey(1))->not->toBe($this->service->signingKey(2));
    });

    it('produces different keys for different periods', function () {
        Carbon::setTestNow('2025-06-15 10:00:00');
        $key1 = $this->service->signingKey(1);

        Carbon::setTestNow('2025-06-16 10:00:00');
        $key2 = $this->service->signingKey(1);

        expect($key1)->not->toBe($key2);
    });

    it('signing key creates a JWT that can be verified with public key', function () {
        Carbon::setTestNow('2025-06-15 10:00:00');

        $signingKeyB64 = $this->service->signingKey(42);
        $publicKeyB64 = $this->service->publicKey(42);

        // firebase/php-jwt base64-decodes internally, so pass base64 strings directly
        $payload = ['volunteer_id' => 1, 'event_id' => 42, 'iat' => time()];
        $jwt = JWT::encode($payload, $signingKeyB64, 'EdDSA');

        $decoded = JWT::decode($jwt, new Key($publicKeyB64, 'EdDSA'));
        expect($decoded->volunteer_id)->toBe(1)
            ->and($decoded->event_id)->toBe(42);
    });
});

// Legacy HMAC (deprecated)

describe('legacy HMAC (deprecated)', function () {
    it('derives key using HMAC of event_id:period_date and APP_KEY', function () {
        Carbon::setTestNow('2025-06-15 10:00:00');

        $key = $this->service->deriveKey(42);

        $expected = hash_hmac('sha256', '42:2025-06-15', config('app.key'));
        expect($key)->toBe($expected);
    });

    it('rotates period at 4am, not midnight', function () {
        Carbon::setTestNow('2025-06-15 03:59:00');
        $keyBefore4am = $this->service->deriveKey(1);

        Carbon::setTestNow('2025-06-15 04:00:00');
        $keyAt4am = $this->service->deriveKey(1);

        expect($keyBefore4am)->not->toBe($keyAt4am);

        $expectedBefore = hash_hmac('sha256', '1:2025-06-14', config('app.key'));
        expect($keyBefore4am)->toBe($expectedBefore);

        $expectedAt = hash_hmac('sha256', '1:2025-06-15', config('app.key'));
        expect($keyAt4am)->toBe($expectedAt);
    });

    it('derives different keys for different events', function () {
        Carbon::setTestNow('2025-06-15 10:00:00');

        expect($this->service->deriveKey(1))->not->toBe($this->service->deriveKey(2));
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
    })->throws(\App\Exceptions\InvalidTicketException::class);

    it('rejects malformed JWT', function () {
        $this->service->validateToken('not-a-jwt', 5);
    })->throws(\App\Exceptions\InvalidTicketException::class);
});
