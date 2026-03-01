<?php

namespace App\Actions;

use App\Exceptions\DomainException;
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
        if ($capacity < $shift->signups()->count()) {
            throw new DomainException('Cannot reduce capacity below current number of signups.');
        }

        $shift->update([
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'capacity' => $capacity,
        ]);

        return $shift->refresh();
    }
}
