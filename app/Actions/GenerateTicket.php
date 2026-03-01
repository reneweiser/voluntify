<?php

namespace App\Actions;

use App\Models\Event;
use App\Models\Ticket;
use App\Models\Volunteer;
use Firebase\JWT\JWT;

class GenerateTicket
{
    public function execute(Volunteer $volunteer, Event $event): Ticket
    {
        $existing = Ticket::where('volunteer_id', $volunteer->id)
            ->where('event_id', $event->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $key = $this->deriveKey($event);

        $payload = [
            'volunteer_id' => $volunteer->id,
            'event_id' => $event->id,
            'iat' => now()->timestamp,
        ];

        $jwt = JWT::encode($payload, $key, 'HS256');

        return Ticket::create([
            'volunteer_id' => $volunteer->id,
            'event_id' => $event->id,
            'jwt_token' => $jwt,
        ]);
    }

    private function deriveKey(Event $event): string
    {
        return hash_hmac('sha256', (string) $event->id, config('app.key'));
    }
}
