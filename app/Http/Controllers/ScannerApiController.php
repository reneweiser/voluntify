<?php

namespace App\Http\Controllers;

use App\Actions\RecordArrival;
use App\Enums\ArrivalMethod;
use App\Http\Requests\SyncArrivalsRequest;
use App\Models\Event;
use App\Models\EventArrival;
use App\Models\Ticket;
use App\Services\JwtKeyService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ScannerApiController extends Controller
{
    public function data(int $eventId, JwtKeyService $jwtKeyService): JsonResponse
    {
        $organization = currentOrganization();
        $event = Event::where('id', $eventId)
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        Gate::authorize('scan', $event);

        $volunteers = $event->volunteers()
            ->with([
                'tickets' => fn ($q) => $q->where('event_id', $event->id),
                'shiftSignups.shift.volunteerJob',
            ])
            ->get();

        $arrivals = EventArrival::where('event_id', $event->id)->get();

        return response()->json([
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
            ],
            'volunteers' => $volunteers->map(fn ($v) => [
                'id' => $v->id,
                'name' => $v->name,
                'email' => $v->email,
                'ticket' => $v->tickets->first(),
                'shift_signups' => $v->shiftSignups->map(fn ($signup) => [
                    'id' => $signup->id,
                    'shift' => [
                        'id' => $signup->shift->id,
                        'starts_at' => $signup->shift->starts_at,
                        'ends_at' => $signup->shift->ends_at,
                        'volunteer_job' => [
                            'id' => $signup->shift->volunteerJob->id,
                            'name' => $signup->shift->volunteerJob->name,
                        ],
                    ],
                ]),
            ]),
            'arrivals' => $arrivals,
            'keys' => $jwtKeyService->publicKeys($event->id),
        ]);
    }

    public function sync(
        int $eventId,
        SyncArrivalsRequest $request,
        RecordArrival $recordArrival,
    ): JsonResponse {
        $organization = currentOrganization();
        $event = Event::where('id', $eventId)
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        Gate::authorize('scan', $event);

        foreach ($request->validated()['arrivals'] as $arrivalData) {
            $ticket = Ticket::where('event_id', $event->id)->findOrFail($arrivalData['ticket_id']);

            $recordArrival->execute(
                ticket: $ticket,
                scannedBy: $request->user(),
                method: ArrivalMethod::from($arrivalData['method']),
                scannedAt: Carbon::parse($arrivalData['scanned_at']),
            );
        }

        $arrivals = EventArrival::where('event_id', $event->id)->get();

        return response()->json([
            'arrivals' => $arrivals,
        ]);
    }
}
