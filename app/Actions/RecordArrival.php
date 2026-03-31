<?php

namespace App\Actions;

use App\Enums\ArrivalMethod;
use App\Events\Activity\ArrivalScanned;
use App\Models\Event;
use App\Models\EventArrival;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;

class RecordArrival
{
    public function execute(
        Ticket $ticket,
        Event $event,
        User $scannedBy,
        ArrivalMethod $method,
        ?Carbon $scannedAt = null,
    ): EventArrival {
        $existingArrival = EventArrival::where('ticket_id', $ticket->id)
            ->where('event_id', $event->id)
            ->first();

        $flagged = $existingArrival !== null;

        $arrival = EventArrival::create([
            'ticket_id' => $ticket->id,
            'volunteer_id' => $ticket->volunteer_id,
            'event_id' => $event->id,
            'scanned_by' => $scannedBy->id,
            'scanned_at' => $scannedAt ?? now(),
            'method' => $method,
            'flagged' => $flagged,
            'flag_reason' => $flagged ? 'Duplicate scan — volunteer already checked in.' : null,
        ]);

        ArrivalScanned::dispatch($arrival, $scannedBy);

        return $arrival;
    }
}
