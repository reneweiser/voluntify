<?php

namespace App\Actions;

use App\Events\Activity\SignupCancelled;
use App\Exceptions\CancellationCutoffPassedException;
use App\Exceptions\DomainException;
use App\Models\ShiftSignup;

class CancelShiftSignup
{
    public function execute(ShiftSignup $signup): void
    {
        if ($signup->isCancelled()) {
            throw new DomainException('This signup has already been cancelled.');
        }

        $event = $signup->shift->volunteerJob->event;

        if (! $event->isCancellationAllowed()) {
            throw new DomainException('Cancellation is not enabled for this event.');
        }

        if (! $signup->isCancellable($event->cancellation_cutoff_hours)) {
            throw new CancellationCutoffPassedException;
        }

        $signup->cancelled_at = now();
        $signup->save();

        SignupCancelled::dispatch($signup, $signup->volunteer);
    }
}
