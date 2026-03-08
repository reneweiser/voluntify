<?php

namespace App\Actions;

use App\Events\Activity\JobCreated;
use App\Models\Event;
use App\Models\VolunteerJob;

class CreateVolunteerJob
{
    public function execute(
        Event $event,
        string $name,
        ?string $description,
        ?string $instructions,
    ): VolunteerJob {
        $job = $event->volunteerJobs()->create([
            'name' => $name,
            'description' => $description,
            'instructions' => $instructions,
        ]);

        if (auth()->user()) {
            JobCreated::dispatch($job, auth()->user());
        }

        return $job;
    }
}
