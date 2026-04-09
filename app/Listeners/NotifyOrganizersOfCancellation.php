<?php

namespace App\Listeners;

use App\Events\Activity\SignupCancelled;
use App\Notifications\ImmediateCancellationNotification;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class NotifyOrganizersOfCancellation implements ShouldHandleEventsAfterCommit
{
    public function handleSignupCancelled(SignupCancelled $event): void
    {
        $signup = $event->signup;
        $volunteer = $event->volunteer;

        $signup->loadMissing('shift.volunteerJob.event.project');
        $eventModel = $signup->shift->volunteerJob->event;
        $project = $eventModel->project;

        try {
            $recipientEmails = collect([$eventModel->notification_email])
                ->filter()
                ->unique()
                ->values();

            if ($recipientEmails->isEmpty() && $project->contact_email) {
                $recipientEmails = collect([$project->contact_email]);
            }

            if ($recipientEmails->isEmpty()) {
                return;
            }

            foreach ($recipientEmails as $email) {
                Notification::route('mail', $email)
                    ->notify(new ImmediateCancellationNotification(
                        event: $eventModel,
                        signup: $signup,
                        volunteerName: $volunteer->full_name,
                    ));
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send immediate cancellation notification to organizers', [
                'volunteer_id' => $volunteer->id,
                'signup_id' => $signup->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
