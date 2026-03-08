<?php

namespace App\Actions;

use App\Events\Activity\JobDeleted;
use App\Exceptions\HasSignupsException;
use App\Models\VolunteerJob;

class DeleteVolunteerJob
{
    public function execute(VolunteerJob $job): void
    {
        $hasSignups = $job->shifts()
            ->whereHas('signups')
            ->exists();

        if ($hasSignups) {
            throw new HasSignupsException('Cannot delete a job that has volunteer signups.');
        }

        $job->loadMissing('event');
        $jobName = $job->name;
        $eventId = $job->event_id;
        $eventName = $job->event->name;

        $job->shifts()->delete();
        $job->delete();

        if (auth()->user()) {
            JobDeleted::dispatch($jobName, $eventId, $eventName, auth()->user());
        }
    }
}
