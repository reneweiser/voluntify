<?php

namespace App\Actions;

use App\Events\Activity\ShiftCreated;
use App\Models\Shift;
use App\Models\User;
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
        bool $isActive,
        ?string $displayText = null,
        ?User $causer = null,
    ): Shift {
        $shift = $job->shifts()->create([
            'shift_date' => $shiftDate,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'capacity' => $capacity,
            'is_active' => $isActive,
            'display_text' => $displayText,
        ]);

        if ($causer) {
            ShiftCreated::dispatch($shift, $causer);
        }

        return $shift;
    }
}
