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
        bool $isActive,
        User $causer,
    ): VolunteerJob {
        $event = $job->event;
        $hadAvailability = $event->publicSignupJobs()->isNotEmpty();

        $updateData = [
            'name' => $name,
            'description' => $description,
            'instructions' => $instructions,
            'is_active' => $isActive,
        ];

        $changed = collect($updateData)
            ->filter(fn ($v, $k) => $job->getOriginal($k) != $v)
            ->mapWithKeys(fn ($v, $k) => [$k => [$job->getOriginal($k), $v]])
            ->all();

        $job->update($updateData);
        app(ScheduleEventSubscriberNotification::class)->execute($job->event->fresh(), $hadAvailability);

        if ($changed) {
            JobUpdated::dispatch($job->refresh(), $causer, $changed);
        }

        return $job->refresh();
    }
}
