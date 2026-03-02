<?php

namespace App\Services;

use App\Exceptions\InvalidTicketException;
use Carbon\Carbon;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtKeyService
{
    public function deriveKey(int $eventId, ?Carbon $at = null): string
    {
        $periodDate = $this->currentPeriodDate($at);

        return hash_hmac('sha256', $eventId.':'.$periodDate, config('app.key'));
    }

    public function previousPeriodKey(int $eventId, ?Carbon $at = null): string
    {
        $periodDate = $this->previousPeriodDate($at);

        return hash_hmac('sha256', $eventId.':'.$periodDate, config('app.key'));
    }

    public function currentPeriodDate(?Carbon $at = null): string
    {
        $now = $at ?? now();

        if ($now->hour < 4) {
            return $now->copy()->subDay()->toDateString();
        }

        return $now->toDateString();
    }

    public function previousPeriodDate(?Carbon $at = null): string
    {
        $now = $at ?? now();

        if ($now->hour < 4) {
            return $now->copy()->subDays(2)->toDateString();
        }

        return $now->copy()->subDay()->toDateString();
    }

    /**
     * Validate a JWT token against current and previous period keys.
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
