<?php

namespace App\Actions;

use App\Enums\EventStatus;
use App\Exceptions\DomainException;
use App\Models\Event;
use App\Models\Shift;
use App\Models\ShiftReservation;
use App\Models\ShiftSignup;
use App\Models\Volunteer;
use App\Notifications\SignupConfirmation;
use App\ValueObjects\ShiftSignupResult;
use Illuminate\Support\Facades\DB;

class SignUpVolunteerForShifts
{
    public function __construct(
        private GenerateTicket $generateTicket,
        private GenerateMagicLink $generateMagicLink,
    ) {}

    /**
     * @param  array<int>  $shiftIds
     */
    public function execute(
        Volunteer $volunteer,
        Event $event,
        array $shiftIds,
        bool $sendNotification = true,
        ?string $sessionId = null,
        bool $bypassPriorityGate = false,
        bool $bypassSignupCutoff = false,
        bool $requirePublishedOpen = false,
    ): ShiftSignupResult {
        $event = $event->fresh();

        if ($requirePublishedOpen && $event->status !== EventStatus::PublishedOpen) {
            throw new DomainException('This event is no longer open for signup.');
        }

        $eventJobIds = $event->volunteerJobs()->active()->pluck('id');
        $selectedShifts = Shift::whereIn('volunteer_job_id', $eventJobIds)
            ->active()
            ->whereIn('id', $shiftIds)
            ->get(['id', 'is_priority']);

        $validShiftIds = $selectedShifts->pluck('id')->all();

        if (count($validShiftIds) !== count($shiftIds)) {
            throw new DomainException('One or more shifts do not belong to this event.');
        }

        if (! $bypassPriorityGate && ! $event->isPriorityGateOpen() && $selectedShifts->contains(fn (Shift $shift) => ! $shift->is_priority)) {
            throw new DomainException($event->priorityGateMessage());
        }

        $sortedShiftIds = $shiftIds;
        sort($sortedShiftIds);

        $result = DB::transaction(function () use ($bypassSignupCutoff, $volunteer, $event, $sortedShiftIds, $sessionId) {
            // Release this session's reservations BEFORE checking capacity.
            // This is safe within the transaction: releasing the reservation frees capacity,
            // and the signup immediately claims it. No other transaction can grab the spot
            // between release and claim because the shift row is locked below.
            if ($sessionId !== null) {
                ShiftReservation::forSession($sessionId)->delete();
            }

            $newSignups = [];
            $skippedFull = [];
            $skippedDuplicate = [];
            $skippedOverlap = [];

            // Load volunteer's existing confirmed shift times for this event.
            // Scoped to current event only — cross-event overlap is intentionally not checked.
            // lockForUpdate() serialises reads of the volunteer's existing signups, preventing
            // a concurrent transaction from bypassing the overlap check by reading a stale snapshot.
            $committedShifts = collect(
                ShiftSignup::where('volunteer_id', $volunteer->id)
                    ->whereNull('cancelled_at')
                    ->whereHas('shift.volunteerJob', fn ($q) => $q->where('event_id', $event->id))
                    ->lockForUpdate()
                    ->with('shift:id,shift_date,starts_at,ends_at')
                    ->get()
                    ->pluck('shift')
                    ->filter()
            );

            foreach ($sortedShiftIds as $shiftId) {
                $shift = Shift::lockForUpdate()->findOrFail($shiftId);

                if (! $bypassSignupCutoff && ! $shift->isSignupOpen($event->signup_grace_minutes)) {
                    throw new DomainException('One or more selected shifts are no longer available for signup.');
                }

                // Duplicate check (active signups only).
                $existingSignup = ShiftSignup::where('volunteer_id', $volunteer->id)
                    ->where('shift_id', $shift->id)
                    ->first();

                if ($existingSignup && ! $existingSignup->isCancelled()) {
                    $skippedDuplicate[] = $shift;

                    continue;
                }

                // Overlap check — positioned before the reactivation branch so cancelled signups
                // cannot be re-activated into a time slot that now conflicts with a newer signup.
                // Null guard on incoming shift: open-ended roles (null times) skip the check.
                // Null guard on committed shifts: open-ended committed shifts never block anything.
                if ($shift->starts_at !== null && $shift->ends_at !== null) {
                    $hasOverlap = $committedShifts->contains(
                        fn ($committed) => $committed->starts_at !== null
                            && $committed->ends_at !== null
                            // No date guard — full datetime comparison handles cross-midnight shifts correctly.
                            && $shift->starts_at < $committed->ends_at
                            && $shift->ends_at > $committed->starts_at
                    );

                    if ($hasOverlap) {
                        $skippedOverlap[] = $shift;

                        continue;
                    }
                }

                // Capacity check.
                if ($shift->isFull()) {
                    $skippedFull[] = $shift;

                    continue;
                }

                // Create or reactivate signup.
                if ($existingSignup && $existingSignup->isCancelled()) {
                    $existingSignup->cancelled_at = null;
                    $existingSignup->signed_up_at = now();
                    $existingSignup->save();
                    $newSignups[] = $existingSignup;
                } else {
                    $newSignups[] = ShiftSignup::create([
                        'volunteer_id' => $volunteer->id,
                        'shift_id' => $shift->id,
                        'signed_up_at' => now(),
                    ]);
                }
                // Push AFTER both creation branches, outside the if/else block.
                // All failure paths above use `continue`, so this line only executes on success.
                // Grows intra-batch awareness: subsequent shifts are checked against this one.
                $committedShifts->push($shift);
            }

            $this->generateTicket->execute($volunteer, $event);

            $plainToken = null;
            if (count($newSignups) > 0) {
                ['plainToken' => $plainToken] = $this->generateMagicLink->execute($volunteer);
            }

            return [
                'volunteer' => $volunteer,
                'newSignups' => $newSignups,
                'skippedFull' => $skippedFull,
                'skippedDuplicate' => $skippedDuplicate,
                'skippedOverlap' => $skippedOverlap,
                'plainToken' => $plainToken,
            ];
        });

        $batchResult = new ShiftSignupResult(
            volunteer: $result['volunteer'],
            newSignups: $result['newSignups'],
            skippedFull: $result['skippedFull'],
            skippedDuplicate: $result['skippedDuplicate'],
            skippedOverlap: $result['skippedOverlap'],
        );

        $event->fresh()->evaluatePriorityGate();

        if ($sendNotification && $batchResult->hasNewSignups()) {
            $shiftIds = collect($result['newSignups'])
                ->map(fn (ShiftSignup $signup) => $signup->shift_id)
                ->all();

            $result['volunteer']->notify(
                new SignupConfirmation($event, $shiftIds, $result['plainToken']),
            );
        }

        return $batchResult;
    }
}
