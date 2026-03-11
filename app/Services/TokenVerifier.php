<?php

namespace App\Services;

use App\Exceptions\InvalidTicketException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class TokenVerifier
{
    public function __construct(private JwtKeyService $jwtKeyService) {}

    /**
     * Validate a JWT token against all known keys (EdDSA current/previous + legacy HMAC current/previous).
     *
     * @throws InvalidTicketException
     */
    public function verify(string $jwt, int $eventId): \stdClass
    {
        $keys = $this->allVerificationKeys($eventId);

        foreach ($keys as $key) {
            try {
                return JWT::decode($jwt, $key);
            } catch (\Exception) {
                // Try next key
            }
        }

        throw new InvalidTicketException('Invalid or expired ticket.');
    }

    /**
     * @return Key[]
     */
    private function allVerificationKeys(int $eventId): array
    {
        $publicKeys = $this->jwtKeyService->publicKeys($eventId);

        return [
            // EdDSA keys (preferred)
            new Key($publicKeys['current'], 'EdDSA'),
            new Key($publicKeys['previous'], 'EdDSA'),
            // Legacy HMAC keys (backward compatibility)
            new Key($this->jwtKeyService->deriveKey($eventId), 'HS256'),
            new Key($this->jwtKeyService->previousPeriodKey($eventId), 'HS256'),
        ];
    }
}
