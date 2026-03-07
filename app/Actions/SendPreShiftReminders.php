<?php

namespace App\Actions;

use App\Enums\ReminderWindow;
use App\Models\ShiftSignup;
use App\Notifications\PreShiftReminder;
use Illuminate\Support\Facades\Log;

class SendPreShiftReminders
{
    public function execute(ReminderWindow $window): int
    {
        $signups = ShiftSignup::query()
            ->where($window->flagColumn(), false)
            ->whereHas('shift', fn ($q) => $q
                ->where('starts_at', '>', now())
                ->where('starts_at', '<=', now()->addHours($window->hours()))
            )
            ->whereHas('shift.volunteerJob.event', fn ($q) => $q->published())
            ->whereHas('volunteer', fn ($q) => $q->whereNotNull('email_verified_at'))
            ->with(['shift.volunteerJob.event.organization', 'volunteer'])
            ->get();

        $count = 0;

        foreach ($signups as $signup) {
            $signup->update([$window->flagColumn() => true]);

            try {
                $signup->volunteer->notify(new PreShiftReminder(
                    $signup->shift->volunteerJob->event,
                    $signup->shift,
                    $window->templateType(),
                ));
                $count++;
            } catch (\Throwable $e) {
                Log::error('Failed to send pre-shift reminder', [
                    'volunteer_id' => $signup->volunteer_id,
                    'shift_id' => $signup->shift_id,
                    'signup_id' => $signup->id,
                    'window' => $window->value,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $count;
    }
}
