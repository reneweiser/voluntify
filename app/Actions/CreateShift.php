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
        bool $isPriority = false,
        ?string $displayText = null,
        ?User $causer = null,
    ): Shift {
        $hadAvailability = $job->event->publicSignupJobs()->isNotEmpty();

        $shift = $job->shifts()->create([
            'shift_date' => $shiftDate,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'capacity' => $capacity,
            'is_active' => $isActive,
            'is_priority' => $isPriority,
            'display_text' => $displayText,
        ]);

        $shift->volunteerJob->event->refresh()->evaluatePriorityGate();
        app(ScheduleEventSubscriberNotification::class)->execute($shift->volunteerJob->event->fresh(), $hadAvailability);

        if ($causer) {
            ShiftCreated::dispatch($shift, $causer);
        }

        return $shift;
    }
}
