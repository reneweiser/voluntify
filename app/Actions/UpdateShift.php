<?php

namespace App\Actions;

use App\Events\Activity\ShiftUpdated;
use App\Exceptions\DomainException;
use App\Models\Shift;
use App\Models\User;
use Carbon\CarbonInterface;

class UpdateShift
{
    public function execute(
        Shift $shift,
        CarbonInterface $shiftDate,
        ?CarbonInterface $startsAt,
        ?CarbonInterface $endsAt,
        int $capacity,
        bool $isActive,
        bool $isPriority = false,
        ?string $displayText = null,
        ?User $causer = null,
    ): Shift {
        $event = $shift->volunteerJob->event;
        $hadAvailability = $event->publicSignupJobs()->isNotEmpty();

        if ($capacity < $shift->activeSignups()->count()) {
            throw new DomainException('Cannot reduce capacity below current number of signups.');
        }

        $updateData = [
            'shift_date' => $shiftDate,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'capacity' => $capacity,
            'is_active' => $isActive,
            'is_priority' => $isPriority,
            'display_text' => $displayText,
        ];

        $changed = collect($updateData)
            ->filter(fn ($v, $k) => $shift->getOriginal($k) != $v)
            ->mapWithKeys(fn ($v, $k) => [$k => [$shift->getOriginal($k), $v]])
            ->all();

        $shift->update($updateData);
        $shift->volunteerJob->event->refresh()->evaluatePriorityGate();
        app(ScheduleEventSubscriberNotification::class)->execute($shift->volunteerJob->event->fresh(), $hadAvailability);

        if ($changed && $causer) {
            ShiftUpdated::dispatch($shift->refresh(), $causer, $changed);
        }

        return $shift->refresh();
    }
}
