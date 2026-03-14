<?php

namespace App\Http\Controllers;

use App\Actions\RecordArrival;
use App\Actions\RecordAttendance;
use App\Enums\ArrivalMethod;
use App\Enums\AttendanceStatus;
use App\Enums\StaffRole;
use App\Http\Requests\SyncArrivalsRequest;
use App\Http\Requests\SyncAttendanceRequest;
use App\Models\AttendanceRecord;
use App\Models\Event;
use App\Models\EventArrival;
use App\Models\ShiftSignup;
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

        if (! Gate::any(['scan', 'markAttendance'], $event)) {
            abort(403);
        }

        $user = auth()->user();
        $role = $user->cachedRoleFor($organization);
        $userRole = match ($role) {
            StaffRole::Organizer => 'organizer',
            StaffRole::EntranceStaff => 'entrance_staff',
            StaffRole::VolunteerAdmin => 'volunteer_admin',
            default => null,
        };

        $volunteers = $event->volunteers()
            ->with([
                'tickets' => fn ($q) => $q->where('event_id', $event->id),
                'shiftSignups.shift.volunteerJob',
                'shiftSignups.attendanceRecord',
            ])
            ->get();

        $arrivals = EventArrival::where('event_id', $event->id)->get();

        $shiftSignupIds = $volunteers->flatMap(fn ($v) => $v->shiftSignups->pluck('id'));
        $attendanceRecords = AttendanceRecord::whereIn('shift_signup_id', $shiftSignupIds)->get();

        return response()->json([
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'attendance_grace_minutes' => $event->attendance_grace_minutes,
            ],
            'user_role' => $userRole,
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
                    'attendance_record' => $signup->attendanceRecord ? [
                        'id' => $signup->attendanceRecord->id,
                        'shift_signup_id' => $signup->attendanceRecord->shift_signup_id,
                        'status' => $signup->attendanceRecord->status->value,
                    ] : null,
                ]),
            ]),
            'arrivals' => $arrivals,
            'attendance_records' => $attendanceRecords,
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

    public function syncAttendance(
        int $eventId,
        SyncAttendanceRequest $request,
        RecordAttendance $recordAttendance,
    ): JsonResponse {
        $organization = currentOrganization();
        $event = Event::where('id', $eventId)
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        Gate::authorize('markAttendance', $event);

        $eventJobIds = $event->volunteerJobs()->pluck('id');

        foreach ($request->validated()['attendance'] as $entry) {
            $signup = ShiftSignup::whereHas(
                'shift',
                fn ($q) => $q->whereIn('volunteer_job_id', $eventJobIds),
            )->findOrFail($entry['shift_signup_id']);

            $recordAttendance->execute(
                signup: $signup,
                status: AttendanceStatus::from($entry['status']),
                recordedBy: $request->user(),
            );
        }

        $shiftSignupIds = ShiftSignup::whereHas(
            'shift',
            fn ($q) => $q->whereIn('volunteer_job_id', $eventJobIds),
        )->pluck('id');

        $attendanceRecords = AttendanceRecord::whereIn('shift_signup_id', $shiftSignupIds)->get();

        return response()->json([
            'attendance_records' => $attendanceRecords,
        ]);
    }
}
