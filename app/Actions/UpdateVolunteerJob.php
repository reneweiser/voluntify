<?php

namespace App\Actions;

use App\Events\Activity\JobUpdated;
use App\Models\User;
use App\Models\VolunteerJob;

class UpdateVolunteerJob
{
    public function execute(
        VolunteerJob $job,
        string $name,
        ?string $description,
        ?string $instructions,
        User $causer,
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

        if ($changed) {
            JobUpdated::dispatch($job->refresh(), $causer, $changed);
        }

        return $job->refresh();
    }
}
