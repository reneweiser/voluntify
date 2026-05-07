<?php

namespace App\Actions;

use App\Enums\EventStatus;
use App\Exceptions\DomainException;
use App\Models\Event;
use App\Models\Shift;
use App\Models\ShiftReservation;
use App\ValueObjects\ReservationResult;
use Illuminate\Support\Facades\DB;

/**
 * Reserve shifts for a signup session. Atomic — uses DB locks to prevent double-booking.
 *
 * Reservations are session-scoped because volunteer identity is unknown at step 1
 * (shift selection). The session ID ties reservations to a browser session.
 *
 * Multi-tab limitation: Because reservations are keyed by Laravel session ID,
 * opening a second tab for the same event in the same browser will replace the
 * first tab's reservations. This is a known limitation for the target audience
 * (small/mid-sized volunteer organizations) and is acceptable.
 */
class ReserveShifts
{
    public const TTL_MINUTES = 20;

    /**
     * @param  array<int>  $shiftIds
     */
    public function execute(array $shiftIds, string $sessionId, Event $event): ReservationResult
    {
        $event = $event->fresh();

        if ($event->status !== EventStatus::PublishedOpen) {
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

        if (! $event->isPriorityGateOpen() && $selectedShifts->contains(fn (Shift $shift) => ! $shift->is_priority)) {
            throw new DomainException($event->priorityGateMessage());
        }

        sort($validShiftIds);

        return DB::transaction(function () use ($event, $validShiftIds, $sessionId) {
            // D11: Delete existing reservations INSIDE the transaction to avoid race condition.
            // Without the transaction, another user could claim freed capacity between
            // the delete and the lockForUpdate below.
            ShiftReservation::forSession($sessionId)->delete();

            $reserved = [];
            $unavailable = [];
            $expiresAt = now()->addMinutes(self::TTL_MINUTES);

            foreach ($validShiftIds as $shiftId) {
                $shift = Shift::lockForUpdate()->findOrFail($shiftId);

                if (! $shift->isSignupOpen($event->signup_grace_minutes)) {
                    throw new DomainException('One or more selected shifts are no longer available for signup.');
                }

                if ($shift->isFull()) {
                    $unavailable[] = $shift;

                    continue;
                }

                $reserved[] = ShiftReservation::create([
                    'shift_id' => $shiftId,
                    'session_id' => $sessionId,
                    'expires_at' => $expiresAt,
                ]);
            }

            return new ReservationResult(
                reserved: $reserved,
                unavailable: $unavailable,
                expiresAt: count($reserved) > 0 ? $expiresAt : null,
            );
        });
    }
}
