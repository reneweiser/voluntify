<?php

namespace App\Listeners;

use App\Actions\GenerateMagicLink;
use App\Events\Activity\SignupCancelled;
use App\Notifications\CancellationConfirmation;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Log;

class SendCancellationConfirmation implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private GenerateMagicLink $generateMagicLink,
    ) {}

    public function handleSignupCancelled(SignupCancelled $event): void
    {
        $signup = $event->signup;
        $volunteer = $event->volunteer;

        $signup->loadMissing('shift.volunteerJob.event');
        $eventModel = $signup->shift->volunteerJob->event;

        try {
            $remainingShiftIds = $volunteer->shiftSignups()
                ->active()
                ->whereHas('shift.volunteerJob', fn ($q) => $q->where('event_id', $eventModel->id))
                ->pluck('shift_id')
                ->all();

            $result = $this->generateMagicLink->execute($volunteer);

            $volunteer->notify(new CancellationConfirmation(
                event: $eventModel,
                cancelledSignup: $signup,
                remainingShiftIds: $remainingShiftIds,
                magicLinkToken: $result['plainToken'],
            ));
        } catch (\Throwable $e) {
            Log::error('Failed to send cancellation confirmation', [
                'volunteer_id' => $volunteer->id,
                'signup_id' => $signup->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
