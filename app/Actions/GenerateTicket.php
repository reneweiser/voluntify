<?php

namespace App\Actions;

use App\Models\Event;
use App\Models\Ticket;
use App\Models\Volunteer;
use App\Services\JwtKeyService;
use Firebase\JWT\JWT;

class GenerateTicket
{
    public function __construct(private JwtKeyService $jwtKeyService) {}

    public function execute(Volunteer $volunteer, Event $event): Ticket
    {
        $existing = Ticket::where('volunteer_id', $volunteer->id)
            ->where('project_id', $event->project_id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $signingKey = $this->jwtKeyService->signingKey($event->project_id);

        $payload = [
            'volunteer_id' => $volunteer->id,
            'project_id' => $event->project_id,
            'iat' => now()->timestamp,
        ];

        $jwt = JWT::encode($payload, $signingKey, 'EdDSA');

        return Ticket::create([
            'volunteer_id' => $volunteer->id,
            'project_id' => $event->project_id,
            'jwt_token' => $jwt,
        ]);
    }
}
