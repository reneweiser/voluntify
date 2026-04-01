<?php

namespace App\Actions;

use App\Events\Activity\ShiftUpdated;
use App\Exceptions\DomainException;
use App\Models\Shift;
use Carbon\CarbonInterface;

class UpdateShift
{
    public function execute(
        Shift $shift,
        CarbonInterface $shiftDate,
        ?CarbonInterface $startsAt,
        ?CarbonInterface $endsAt,
        int $capacity,
        ?string $displayText = null,
    ): Shift {
        if ($capacity < $shift->activeSignups()->count()) {
            throw new DomainException('Cannot reduce capacity below current number of signups.');
        }

        $updateData = [
            'shift_date' => $shiftDate,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'capacity' => $capacity,
            'display_text' => $displayText,
        ];

        $changed = collect($updateData)
            ->filter(fn ($v, $k) => $shift->getOriginal($k) != $v)
            ->mapWithKeys(fn ($v, $k) => [$k => [$shift->getOriginal($k), $v]])
            ->all();

        $shift->update($updateData);

        if ($changed && auth()->user()) {
            ShiftUpdated::dispatch($shift->refresh(), auth()->user(), $changed);
        }

        return $shift->refresh();
    }
}
