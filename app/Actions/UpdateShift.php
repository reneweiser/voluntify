<?php

namespace App\Actions;

use App\Models\Shift;
use Carbon\CarbonInterface;

class UpdateShift
{
    public function execute(
        Shift $shift,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        int $capacity,
    ): Shift {
        $shift->update([
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'capacity' => $capacity,
        ]);

        return $shift->refresh();
    }
}
