<?php

namespace App\Actions;

use App\Events\Activity\ShiftCreated;
use App\Models\Shift;
use App\Models\VolunteerJob;
use Carbon\CarbonInterface;

class CreateShift
{
    public function execute(
        VolunteerJob $job,
        CarbonInterface $shiftDate,
        ?CarbonInterface $startsAt,
        ?CarbonInterface $endsAt,
        int $capacity,
        ?string $displayText = null,
    ): Shift {
        $shift = $job->shifts()->create([
            'shift_date' => $shiftDate,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'capacity' => $capacity,
            'display_text' => $displayText,
        ]);

        if (auth()->user()) {
            ShiftCreated::dispatch($shift, auth()->user());
        }

        return $shift;
    }
}
