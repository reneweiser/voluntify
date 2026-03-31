<?php

namespace App\Actions;

use App\Events\Activity\ProjectDeleted as ProjectDeletedActivity;
use App\Models\Project;
use Illuminate\Support\Facades\Storage;

class DeleteProject
{
    public function execute(Project $project): void
    {
        $projectName = $project->name;
        $organizationId = $project->organization_id;
        $orphanedEventNames = $project->events()->pluck('name')->all();

        if ($project->title_image_path) {
            Storage::disk('public')->delete($project->title_image_path);
        }

        $project->delete();

        if (auth()->user()) {
            ProjectDeletedActivity::dispatch($projectName, $organizationId, $orphanedEventNames, auth()->user());
        }
    }
}
