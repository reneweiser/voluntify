<?php

namespace App\Actions;

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
        return $event->volunteerJobs()->create([
            'name' => $name,
            'description' => $description,
            'instructions' => $instructions,
        ]);
    }
}
