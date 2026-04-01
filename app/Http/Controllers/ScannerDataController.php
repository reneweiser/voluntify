<?php

namespace App\Http\Controllers;

use App\Actions\RecordArrival;
use App\Actions\RecordGearPickup;
use App\Enums\ArrivalMethod;
use App\Enums\ScannerType;
use App\Models\AttendanceRecord;
use App\Models\Event;
use App\Models\EventArrival;
use App\Models\ProjectScanner;
use App\Models\Ticket;
use App\Models\Volunteer;
use App\Models\VolunteerGear;
use App\Services\JwtKeyService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ScannerDataController extends Controller
{
    public function data(int $scannerId, JwtKeyService $jwtKeyService): JsonResponse
    {
        /** @var ProjectScanner $scanner */
        $scanner = request()->attributes->get('scanner');

        if ($scanner->id !== $scannerId) {
            return response()->json(['error' => 'Scanner ID mismatch.'], 403);
        }

        $projectId = $scanner->project_id;
        $eventId = $scanner->event_id;

        $volunteerQuery = $eventId
            ? Volunteer::forEvent($eventId)
            : Volunteer::where('project_id', $projectId);

        $volunteers = $volunteerQuery
            ->with([
                'tickets' => fn ($q) => $q->where('project_id', $projectId),
                'shiftSignups' => function ($q) use ($eventId, $projectId) {
                    if ($eventId) {
                        $q->whereHas('shift.volunteerJob', fn ($sq) => $sq->where('event_id', $eventId));
                    } else {
                        $q->whereHas('shift.volunteerJob.event', fn ($sq) => $sq->where('project_id', $projectId));
                    }
                },
                'shiftSignups.shift.volunteerJob',
                'shiftSignups.attendanceRecord',
            ])
            ->get();

        $events = $eventId
            ? Event::where('id', $eventId)->get()
            : Event::where('project_id', $projectId)->get();

        $eventIds = $events->pluck('id');
        $arrivals = EventArrival::whereIn('event_id', $eventIds)->get();

        $shiftSignupIds = $volunteers->flatMap(fn ($v) => $v->shiftSignups->pluck('id'));
        $attendanceRecords = AttendanceRecord::whereIn('shift_signup_id', $shiftSignupIds)->get();

        return response()->json([
            'scanner' => [
                'id' => $scanner->id,
                'type' => $scanner->type->value,
                'modes' => $scanner->modes,
            ],
            'events' => $events->map(fn ($e) => [
                'id' => $e->id,
                'name' => $e->name,
                'attendance_grace_minutes' => $e->attendance_grace_minutes,
            ]),
            'volunteers' => $volunteers->map(fn ($v) => [
                'id' => $v->id,
                'first_name' => $v->first_name,
                'last_name' => $v->last_name,
                'name' => $v->full_name,
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
                    'attendance_record' => $signup->attendanceRecord ? [
                        'id' => $signup->attendanceRecord->id,
                        'shift_signup_id' => $signup->attendanceRecord->shift_signup_id,
                        'status' => $signup->attendanceRecord->status->value,
                    ] : null,
                ]),
            ]),
            'arrivals' => $arrivals,
            'attendance_records' => $attendanceRecords,
            'keys' => $jwtKeyService->publicKeys($projectId),
        ]);
    }

    public function sync(
        int $scannerId,
        Request $request,
        RecordArrival $recordArrival,
    ): JsonResponse {
        /** @var ProjectScanner $scanner */
        $scanner = $request->attributes->get('scanner');

        if ($scanner->id !== $scannerId) {
            return response()->json(['error' => 'Scanner ID mismatch.'], 403);
        }

        if ($scanner->type !== ScannerType::EntryStaff) {
            return response()->json(['error' => 'Only entry staff scanners can sync arrivals.'], 403);
        }

        $validated = $request->validate([
            'arrivals' => ['required', 'array', 'min:1'],
            'arrivals.*.ticket_id' => ['required', 'integer', 'exists:tickets,id'],
            'arrivals.*.event_id' => ['nullable', 'integer', 'exists:events,id'],
            'arrivals.*.method' => ['required', 'string', Rule::in(array_column(ArrivalMethod::cases(), 'value'))],
            'arrivals.*.scanned_at' => ['required', 'date'],
        ]);

        $eventIds = $scanner->event_id
            ? [$scanner->event_id]
            : Event::where('project_id', $scanner->project_id)->pluck('id')->toArray();

        foreach ($validated['arrivals'] as $arrivalData) {
            $ticket = Ticket::where('project_id', $scanner->project_id)
                ->findOrFail($arrivalData['ticket_id']);

            $eventId = $arrivalData['event_id'] ?? $scanner->event_id;
            $event = Event::whereIn('id', $eventIds)->findOrFail($eventId);

            $recordArrival->execute(
                ticket: $ticket,
                event: $event,
                scannedBy: null,
                method: ArrivalMethod::from($arrivalData['method']),
                scannedAt: Carbon::parse($arrivalData['scanned_at']),
            );
        }

        $arrivals = EventArrival::whereIn('event_id', $eventIds)->get();

        return response()->json([
            'arrivals' => $arrivals,
        ]);
    }

    public function gearPickup(
        int $scannerId,
        Request $request,
        RecordGearPickup $recordGearPickup,
    ): JsonResponse {
        /** @var ProjectScanner $scanner */
        $scanner = $request->attributes->get('scanner');

        if ($scanner->id !== $scannerId) {
            return response()->json(['error' => 'Scanner ID mismatch.'], 403);
        }

        if ($scanner->type !== ScannerType::VolunteerAdmin) {
            return response()->json(['error' => 'Only volunteer admin scanners can record gear pickups.'], 403);
        }

        $request->validate([
            'volunteer_gear_id' => ['required', 'integer', 'exists:volunteer_gear,id'],
            'state' => ['nullable', 'string'],
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $gear = VolunteerGear::whereHas('gearItem', fn ($q) => $q->where('project_id', $scanner->project_id))
            ->findOrFail($request->integer('volunteer_gear_id'));

        $pickup = $recordGearPickup->execute(
            gear: $gear,
            user: null,
            state: $request->input('state'),
            quantity: $request->integer('quantity', 1),
        );

        return response()->json([
            'success' => true,
            'pickup' => $pickup,
        ]);
    }
}
