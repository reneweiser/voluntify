<?php

namespace App\Actions;

use App\Events\Activity\ProjectDeleted as ProjectDeletedActivity;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class DeleteProject
{
    public function execute(Project $project, User $causer): void
    {
        $projectName = $project->name;
        $organizationId = $project->organization_id;
        $orphanedEventNames = $project->events()->pluck('name')->all();

        if ($project->title_image_path) {
            Storage::disk('public')->delete($project->title_image_path);
        }

        $project->delete();

        ProjectDeletedActivity::dispatch($projectName, $organizationId, $orphanedEventNames, $causer);
    }
}
