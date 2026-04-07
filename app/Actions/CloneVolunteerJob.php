<?php

namespace App\Actions;

use App\Models\VolunteerJob;
use Illuminate\Support\Facades\DB;

class CloneVolunteerJob
{
    public function execute(VolunteerJob $job, bool $includeShifts = true): VolunteerJob
    {
        return DB::transaction(function () use ($job, $includeShifts) {
            $job->load('shifts');

            $clonedJob = $job->replicate(['id', 'created_at', 'updated_at']);
            $clonedJob->name = "{$job->name} (Copy)";
            $clonedJob->save();

            if ($includeShifts) {
                foreach ($job->shifts as $shift) {
                    $clonedShift = $shift->replicate(['id', 'volunteer_job_id', 'created_at', 'updated_at']);
                    $clonedShift->volunteer_job_id = $clonedJob->id;
                    $clonedShift->save();
                }
            }

            return $clonedJob->fresh();
        });
    }
}
