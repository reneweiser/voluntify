<?php

namespace App\Services;

use Carbon\Carbon;

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

    public function currentPeriodDate(?Carbon $at = null): string
    {
        return $this->periodResolver->currentPeriodDate($at);
    }

    public function previousPeriodDate(?Carbon $at = null): string
    {
        return $this->periodResolver->previousPeriodDate($at);
    }
}
