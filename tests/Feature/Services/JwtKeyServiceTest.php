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
