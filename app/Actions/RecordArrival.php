<?php

namespace App\Actions;

use App\Enums\ArrivalMethod;
use App\Events\Activity\ArrivalScanned;
use App\Models\Event;
use App\Models\EventArrival;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RecordArrival
{
    public function execute(
        Ticket $ticket,
        Event $event,
        ?User $scannedBy,
        ArrivalMethod $method,
        ?Carbon $scannedAt = null,
    ): EventArrival {
        return DB::transaction(function () use ($ticket, $event, $scannedBy, $method, $scannedAt) {
            $lockedTicket = Ticket::query()
                ->whereKey($ticket->id)
                ->lockForUpdate()
                ->firstOrFail();

            $existingArrival = EventArrival::query()
                ->where('ticket_id', $lockedTicket->id)
                ->where('event_id', $event->id)
                ->lockForUpdate()
                ->first();

            if ($existingArrival instanceof EventArrival) {
                $existingArrival->setAttribute('duplicate_detected', true);
                $existingArrival->setAttribute('duplicate_message', 'Duplicate scan - volunteer already checked in.');

                return $existingArrival;
            }

            $arrival = EventArrival::create([
                'ticket_id' => $lockedTicket->id,
                'volunteer_id' => $lockedTicket->volunteer_id,
                'event_id' => $event->id,
                'scanned_by' => $scannedBy?->id,
                'scanned_at' => $scannedAt ?? now(),
                'method' => $method,
                'flagged' => false,
                'flag_reason' => null,
            ]);

            ArrivalScanned::dispatch($arrival, $scannedBy);

            return $arrival;
        });
    }
}
