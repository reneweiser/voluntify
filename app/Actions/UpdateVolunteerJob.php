<?php

namespace App\Actions;

use App\Models\VolunteerJob;

class UpdateVolunteerJob
{
    public function execute(
        VolunteerJob $job,
        string $name,
        ?string $description,
        ?string $instructions,
    ): VolunteerJob {
        $job->update([
            'name' => $name,
            'description' => $description,
            'instructions' => $instructions,
        ]);

        return $job->refresh();
    }
}
