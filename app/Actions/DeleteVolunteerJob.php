<?php

namespace App\Actions;

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

        $job->shifts()->delete();
        $job->delete();
    }
}
