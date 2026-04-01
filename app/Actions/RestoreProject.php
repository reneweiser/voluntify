<?php

namespace App\Actions;

use App\Exceptions\DomainException;
use App\Models\Project;

class RestoreProject
{
    public function execute(Project $project): Project
    {
        if (! $project->isPendingDeletion()) {
            throw new DomainException('Projekt ist nicht zur Löschung vorgemerkt.');
        }

        $project->update(['deletion_requested_at' => null]);

        return $project->refresh();
    }
}
