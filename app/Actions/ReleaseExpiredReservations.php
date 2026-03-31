<?php

namespace App\Actions;

use App\Models\ShiftReservation;

class ReleaseExpiredReservations
{
    /**
     * Delete expired shift reservations in batches to prevent a single massive DELETE
     * after a scheduler stall or prolonged downtime.
     *
     * @return int Total number of deleted reservations.
     */
    public function execute(): int
    {
        $total = 0;

        do {
            $deleted = ShiftReservation::expired()->limit(1000)->delete();
            $total += $deleted;
        } while ($deleted > 0);

        return $total;
    }
}
