<?php

namespace App\Actions;

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
        $eventJobIds = $event->volunteerJobs()->active()->pluck('id');
        $validShiftIds = Shift::whereIn('volunteer_job_id', $eventJobIds)
            ->active()
            ->whereIn('id', $shiftIds)
            ->pluck('id')
            ->all();

        if (count($validShiftIds) !== count($shiftIds)) {
            throw new DomainException('One or more shifts do not belong to this event.');
        }

        sort($validShiftIds);

        return DB::transaction(function () use ($validShiftIds, $sessionId) {
            // D11: Delete existing reservations INSIDE the transaction to avoid race condition.
            // Without the transaction, another user could claim freed capacity between
            // the delete and the lockForUpdate below.
            ShiftReservation::forSession($sessionId)->delete();

            $reserved = [];
            $unavailable = [];
            $expiresAt = now()->addMinutes(self::TTL_MINUTES);

            foreach ($validShiftIds as $shiftId) {
                $shift = Shift::lockForUpdate()->findOrFail($shiftId);

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
