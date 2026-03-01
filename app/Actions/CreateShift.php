<?php

namespace App\Actions;

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
        return $job->shifts()->create([
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'capacity' => $capacity,
        ]);
    }
}
