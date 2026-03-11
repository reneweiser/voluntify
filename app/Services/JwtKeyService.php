<?php

namespace App\Services;

use App\Exceptions\InvalidTicketException;
use Carbon\Carbon;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtKeyService
{
    public function __construct(private PeriodResolver $periodResolver) {}

    /**
     * Derive a deterministic Ed25519 signing key (64 bytes, base64-encoded) for the given event and current period.
     *
     * WARNING: Rotating APP_KEY invalidates all existing tickets.
     */
    public function signingKey(int $eventId, ?Carbon $at = null): string
    {
        [$signingKey] = $this->deriveKeyPair($eventId, $this->periodResolver->currentPeriodDate($at));

        return $signingKey;
    }

    /**
     * Derive the Ed25519 public key (32 bytes, base64-encoded) for the given event and current period.
     */
    public function publicKey(int $eventId, ?Carbon $at = null): string
    {
        [, $publicKey] = $this->deriveKeyPair($eventId, $this->periodResolver->currentPeriodDate($at));

        return $publicKey;
    }

    /**
     * Return current and previous period public keys for client-side verification.
     *
     * @return array{current: string, previous: string}
     */
    public function publicKeys(int $eventId, ?Carbon $at = null): array
    {
        [, $current] = $this->deriveKeyPair($eventId, $this->periodResolver->currentPeriodDate($at));
        [, $previous] = $this->deriveKeyPair($eventId, $this->periodResolver->previousPeriodDate($at));

        return [
            'current' => $current,
            'previous' => $previous,
        ];
    }

    /**
     * Derive a deterministic Ed25519 keypair from event ID and period date.
     *
     * @return array{0: string, 1: string} [signingKey (base64, 64 bytes), publicKey (base64, 32 bytes)]
     */
    private function deriveKeyPair(int $eventId, string $periodDate): array
    {
        // Use the same HMAC derivation as seed material (32 bytes = valid Ed25519 seed)
        $seed = hex2bin(hash_hmac('sha256', $eventId.':'.$periodDate, config('app.key')));
        $keypair = sodium_crypto_sign_seed_keypair($seed);

        $signingKey = sodium_crypto_sign_secretkey($keypair);
        $publicKey = sodium_crypto_sign_publickey($keypair);

        return [base64_encode($signingKey), base64_encode($publicKey)];
    }

    /**
     * @deprecated Use signingKey() for Ed25519. Kept for legacy HMAC fallback.
     */
    public function deriveKey(int $eventId, ?Carbon $at = null): string
    {
        $periodDate = $this->periodResolver->currentPeriodDate($at);

        return hash_hmac('sha256', $eventId.':'.$periodDate, config('app.key'));
    }

    /**
     * @deprecated Use signingKey() for Ed25519. Kept for legacy HMAC fallback.
     */
    public function previousPeriodKey(int $eventId, ?Carbon $at = null): string
    {
        $periodDate = $this->periodResolver->previousPeriodDate($at);

        return hash_hmac('sha256', $eventId.':'.$periodDate, config('app.key'));
    }

    public function currentPeriodDate(?Carbon $at = null): string
    {
        return $this->periodResolver->currentPeriodDate($at);
    }

    public function previousPeriodDate(?Carbon $at = null): string
    {
        return $this->periodResolver->previousPeriodDate($at);
    }

    /**
     * Validate a JWT token against current and previous period keys.
     *
     * @deprecated Use TokenVerifier::verify() instead.
     *
     * @throws InvalidTicketException
     */
    public function validateToken(string $jwt, int $eventId): \stdClass
    {
        $currentKey = $this->deriveKey($eventId);

        try {
            return JWT::decode($jwt, new Key($currentKey, 'HS256'));
        } catch (\Exception) {
            // Fall through to try previous period key
        }

        $previousKey = $this->previousPeriodKey($eventId);

        try {
            return JWT::decode($jwt, new Key($previousKey, 'HS256'));
        } catch (\Exception $e) {
            throw new InvalidTicketException('Invalid or expired ticket.', previous: $e);
        }
    }
}
