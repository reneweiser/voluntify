<?php

namespace App\Actions;

use App\Models\Ticket;
use App\Services\JwtKeyService;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class RefreshTicketJwt
{
    public function __construct(private JwtKeyService $jwtKeyService) {}

    /**
     * Re-sign the ticket JWT with the current period's signing key.
     *
     * Tickets are signed once at creation, but the Ed25519 key rotates daily.
     * The scanner only holds current + previous period public keys, so stale
     * JWTs fail verification. This re-signs with the current key on each view.
     */
    public function execute(Ticket $ticket): Ticket
    {
        if ($this->hasCurrentJwt($ticket)) {
            return $ticket;
        }

        $signingKey = $this->jwtKeyService->signingKey($ticket->project_id);

        $payload = [
            'volunteer_id' => $ticket->volunteer_id,
            'project_id' => $ticket->project_id,
            'iat' => now()->timestamp,
        ];

        $ticket->update(['jwt_token' => JWT::encode($payload, $signingKey, 'EdDSA')]);

        return $ticket;
    }

    private function hasCurrentJwt(Ticket $ticket): bool
    {
        if ($ticket->jwt_token === null || $ticket->jwt_token === '') {
            return false;
        }

        try {
            $decoded = JWT::decode(
                $ticket->jwt_token,
                new Key($this->jwtKeyService->publicKey($ticket->project_id), 'EdDSA'),
            );
        } catch (\Throwable) {
            return false;
        }

        return ($decoded->volunteer_id ?? null) === $ticket->volunteer_id
            && ($decoded->project_id ?? null) === $ticket->project_id;
    }
}
