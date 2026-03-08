<?php

namespace App\Actions;

use App\Events\Activity\JobUpdated;
use App\Models\VolunteerJob;

class UpdateVolunteerJob
{
    public function execute(
        VolunteerJob $job,
        string $name,
        ?string $description,
        ?string $instructions,
    ): VolunteerJob {
        $updateData = [
            'name' => $name,
            'description' => $description,
            'instructions' => $instructions,
        ];

        $changed = collect($updateData)
            ->filter(fn ($v, $k) => $job->getOriginal($k) != $v)
            ->mapWithKeys(fn ($v, $k) => [$k => [$job->getOriginal($k), $v]])
            ->all();

        $job->update($updateData);

        if ($changed && auth()->user()) {
            JobUpdated::dispatch($job->refresh(), auth()->user(), $changed);
        }

        return $job->refresh();
    }
}
