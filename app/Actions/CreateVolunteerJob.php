<?php

namespace App\Actions;

use App\Events\Activity\JobCreated;
use App\Models\Event;
use App\Models\User;
use App\Models\VolunteerJob;

class CreateVolunteerJob
{
    public function execute(
        Event $event,
        string $name,
        ?string $description,
        ?string $instructions,
        User $causer,
    ): VolunteerJob {
        $job = $event->volunteerJobs()->create([
            'name' => $name,
            'description' => $description,
            'instructions' => $instructions,
        ]);

        JobCreated::dispatch($job, $causer);

        return $job;
    }
}
