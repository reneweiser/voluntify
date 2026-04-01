<?php

namespace App\Actions;

use App\Events\Activity\ShiftDeleted;
use App\Exceptions\HasSignupsException;
use App\Models\Shift;

class DeleteShift
{
    public function execute(Shift $shift): void
    {
        if ($shift->signups()->exists()) {
            throw new HasSignupsException('Cannot delete a shift that has volunteer signups.');
        }

        $shift->loadMissing('volunteerJob.event');
        $shiftData = [
            'shift_date' => $shift->shift_date->toDateString(),
            'starts_at' => $shift->starts_at?->toDateTimeString(),
            'ends_at' => $shift->ends_at?->toDateTimeString(),
            'display_text' => $shift->display_text,
            'capacity' => $shift->capacity,
            'job_name' => $shift->volunteerJob->name,
            'event_id' => $shift->volunteerJob->event_id,
            'event_name' => $shift->volunteerJob->event->name,
        ];

        $shift->delete();

        if (auth()->user()) {
            ShiftDeleted::dispatch($shiftData, auth()->user());
        }
    }
}
