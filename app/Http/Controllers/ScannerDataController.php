<?php

namespace App\Http\Controllers;

use App\Actions\CheckInGuest;
use App\Actions\RecordArrival;
use App\Actions\RecordGearPickup;
use App\Actions\RecordGuestGearPickup;
use App\Enums\ArrivalMethod;
use App\Enums\ScannerType;
use App\Exceptions\DomainException;
use App\Models\AttendanceRecord;
use App\Models\Event;
use App\Models\EventArrival;
use App\Models\GuestEntry;
use App\Models\GuestEntryGear;
use App\Models\ProjectGearItem;
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

        $eagerLoads = [
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
        ];

        if ($scanner->type === ScannerType::VolunteerAdmin) {
            $eagerLoads['volunteerGear'] = fn ($q) => $q->whereHas('gearItem', fn ($sq) => $sq->where('project_id', $projectId));
            $eagerLoads['volunteerGear.gearItem'] = fn ($q) => $q;
            $eagerLoads['volunteerGear.pickups'] = fn ($q) => $q;
        }

        $volunteers = $volunteerQuery->with($eagerLoads)->get();

        $events = $eventId
            ? Event::where('id', $eventId)->get()
            : Event::where('project_id', $projectId)->get();

        $eventIds = $events->pluck('id');
        $arrivals = EventArrival::whereIn('event_id', $eventIds)->get();

        $shiftSignupIds = $volunteers->flatMap(fn ($v) => $v->shiftSignups->pluck('id'));
        $attendanceRecords = AttendanceRecord::whereIn('shift_signup_id', $shiftSignupIds)->get();

        $guestEntries = $this->loadGuestEntries($scanner);

        $gearItems = [];
        $volunteerGearMap = (object) [];

        if ($scanner->type === ScannerType::VolunteerAdmin) {
            $gearItems = ProjectGearItem::where('project_id', $projectId)
                ->orderBy('sort_order')
                ->get()
                ->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'type' => $item->type->value,
                    'available_sizes' => $item->available_sizes,
                    'available_states' => $item->available_states,
                ]);

            $volunteerGearMap = $volunteers->mapWithKeys(fn ($v) => [
                $v->id => $v->volunteerGear->map(fn ($g) => [
                    'id' => $g->id,
                    'project_gear_item_id' => $g->project_gear_item_id,
                    'size' => $g->size,
                    'picked_up' => $g->isPickedUp(),
                    'pickups' => $g->pickups->map(fn ($p) => [
                        'state' => $p->state,
                        'quantity' => $p->quantity,
                        'picked_up_at' => $p->picked_up_at?->toISOString(),
                    ]),
                ]),
            ])->filter(fn ($gear) => $gear->isNotEmpty());
        }

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
                        'shift_date' => $signup->shift->shift_date->toDateString(),
                        'starts_at' => $signup->shift->starts_at,
                        'ends_at' => $signup->shift->ends_at,
                        'display_text' => $signup->shift->display_text,
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
            'guest_entries' => $guestEntries,
            'gear_items' => $gearItems,
            'volunteer_gear' => $volunteerGearMap,
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

    public function guestCheckin(
        int $scannerId,
        Request $request,
        CheckInGuest $checkInGuest,
    ): JsonResponse {
        /** @var ProjectScanner $scanner */
        $scanner = $request->attributes->get('scanner');

        if ($scanner->id !== $scannerId) {
            return response()->json(['error' => 'Scanner ID mismatch.'], 403);
        }

        if ($scanner->type !== ScannerType::EntryStaff) {
            return response()->json(['error' => 'Only entry staff scanners can check in guests.'], 403);
        }

        $request->validate([
            'guest_entry_id' => ['required', 'integer'],
        ]);

        $entry = GuestEntry::whereHas('group.guestList', function ($q) use ($scanner) {
            $q->confirmed()->where('scanner_id', $scanner->id);
        })->findOrFail($request->integer('guest_entry_id'));

        try {
            $result = $checkInGuest->execute($entry);

            return response()->json([
                'success' => true,
                'guest_entry' => $result,
                'already_checked_in' => false,
            ]);
        } catch (DomainException) {
            return response()->json([
                'success' => true,
                'guest_entry' => $entry->fresh(),
                'already_checked_in' => true,
            ]);
        }
    }

    public function guestGearPickup(
        int $scannerId,
        Request $request,
        RecordGuestGearPickup $recordGuestGearPickup,
    ): JsonResponse {
        /** @var ProjectScanner $scanner */
        $scanner = $request->attributes->get('scanner');

        if ($scanner->id !== $scannerId) {
            return response()->json(['error' => 'Scanner ID mismatch.'], 403);
        }

        if ($scanner->type !== ScannerType::VolunteerAdmin) {
            return response()->json(['error' => 'Only volunteer admin scanners can record guest gear pickups.'], 403);
        }

        $request->validate([
            'guest_entry_gear_id' => ['required', 'integer'],
            'selection' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:255'],
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $gear = GuestEntryGear::whereHas('entry.group.guestList', function ($q) use ($scanner) {
            $q->confirmed()->where('project_id', $scanner->project_id);
        })->findOrFail($request->integer('guest_entry_gear_id'));

        $result = $recordGuestGearPickup->execute($gear, $request->only(['selection', 'status', 'quantity']));

        return response()->json([
            'success' => true,
            'guest_entry_gear' => $result,
        ]);
    }

    public function guestSync(
        int $scannerId,
        Request $request,
        CheckInGuest $checkInGuest,
    ): JsonResponse {
        /** @var ProjectScanner $scanner */
        $scanner = $request->attributes->get('scanner');

        if ($scanner->id !== $scannerId) {
            return response()->json(['error' => 'Scanner ID mismatch.'], 403);
        }

        if ($scanner->type !== ScannerType::EntryStaff) {
            return response()->json(['error' => 'Only entry staff scanners can sync guest check-ins.'], 403);
        }

        $validated = $request->validate([
            'guest_checkins' => ['present', 'array'],
            'guest_checkins.*.guest_entry_id' => ['required', 'integer'],
            'guest_checkins.*.checked_in_at' => ['required', 'date'],
        ]);

        foreach ($validated['guest_checkins'] as $checkinData) {
            $entry = GuestEntry::whereHas('group.guestList', function ($q) use ($scanner) {
                $q->confirmed()->where('scanner_id', $scanner->id);
            })->find($checkinData['guest_entry_id']);

            if ($entry && ! $entry->isCheckedIn()) {
                $entry->update([
                    'checked_in_at' => Carbon::parse($checkinData['checked_in_at']),
                ]);
            }
        }

        $guestEntries = $this->loadGuestEntries($scanner);

        return response()->json([
            'guest_entries' => $guestEntries,
        ]);
    }

    /**
     * Load guest entries for the scanner data payload.
     * Entry Staff: entries from confirmed lists linked to this scanner (includes qr_token).
     * Volunteer Admin: entries with gear from all confirmed lists in the project (excludes qr_token).
     */
    private function loadGuestEntries(ProjectScanner $scanner): array
    {
        if ($scanner->type === ScannerType::EntryStaff) {
            $entries = GuestEntry::whereHas('group.guestList', function ($q) use ($scanner) {
                $q->confirmed()->where('scanner_id', $scanner->id);
            })->with(['group', 'gear.gearItem'])->get();

            return $entries->map(fn (GuestEntry $e) => [
                'id' => $e->id,
                'guest_group_id' => $e->guest_group_id,
                'group_label' => $e->group->label,
                'group_guest_count' => $e->group->guest_count,
                'number' => $e->number,
                'name' => $e->name,
                'qr_token' => $e->qr_token,
                'checked_in_at' => $e->checked_in_at,
                'gear' => $e->gear->map(fn ($g) => [
                    'id' => $g->id,
                    'gear_item_name' => $g->gearItem->name,
                    'gear_item_type' => $g->gearItem->type->value,
                    'quantity' => $g->quantity,
                    'picked_up_count' => $g->picked_up_count,
                    'selection' => $g->selection,
                    'status' => $g->status,
                ]),
            ])->all();
        }

        if ($scanner->type === ScannerType::VolunteerAdmin) {
            $entries = GuestEntry::whereHas('gear')
                ->whereHas('group.guestList', function ($q) use ($scanner) {
                    $q->confirmed()->where('project_id', $scanner->project_id);
                })->with(['group', 'gear.gearItem'])->get();

            return $entries->map(fn (GuestEntry $e) => [
                'id' => $e->id,
                'guest_group_id' => $e->guest_group_id,
                'group_label' => $e->group->label,
                'group_guest_count' => $e->group->guest_count,
                'number' => $e->number,
                'name' => $e->name,
                'checked_in_at' => $e->checked_in_at,
                'gear' => $e->gear->map(fn ($g) => [
                    'id' => $g->id,
                    'gear_item_name' => $g->gearItem->name,
                    'gear_item_type' => $g->gearItem->type->value,
                    'available_sizes' => $g->gearItem->available_sizes,
                    'available_states' => $g->gearItem->available_states,
                    'quantity' => $g->quantity,
                    'picked_up_count' => $g->picked_up_count,
                    'selection' => $g->selection,
                    'status' => $g->status,
                ]),
            ])->all();
        }

        return [];
    }
}
