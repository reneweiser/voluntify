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
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        int $capacity,
    ): Shift {
        $shift = $job->shifts()->create([
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'capacity' => $capacity,
        ]);

        if (auth()->user()) {
            ShiftCreated::dispatch($shift, auth()->user());
        }

        return $shift;
    }
}
