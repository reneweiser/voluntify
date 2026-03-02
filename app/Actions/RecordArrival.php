<?php

namespace App\Actions;

use App\Enums\ArrivalMethod;
use App\Models\EventArrival;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;

class RecordArrival
{
    public function execute(
        Ticket $ticket,
        User $scannedBy,
        ArrivalMethod $method,
        ?Carbon $scannedAt = null,
    ): EventArrival {
        $existingArrival = EventArrival::where('ticket_id', $ticket->id)->first();

        $flagged = $existingArrival !== null;

        return EventArrival::create([
            'ticket_id' => $ticket->id,
            'volunteer_id' => $ticket->volunteer_id,
            'event_id' => $ticket->event_id,
            'scanned_by' => $scannedBy->id,
            'scanned_at' => $scannedAt ?? now(),
            'method' => $method,
            'flagged' => $flagged,
            'flag_reason' => $flagged ? 'Duplicate scan — volunteer already checked in.' : null,
        ]);
    }
}
