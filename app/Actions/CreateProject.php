<?php

namespace App\Actions;

use App\Events\Activity\ProjectCreated as ProjectCreatedActivity;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\UploadedFile;

class CreateProject
{
    public function execute(
        Organization $organization,
        string $name,
        ?string $description = null,
        ?UploadedFile $titleImage = null,
        ?User $causer = null,
    ): Project {
        $project = $organization->projects()->create([
            'name' => $name,
            'description' => $description,
        ]);

        if ($titleImage) {
            $path = $titleImage->store("projects/{$project->id}", 'public');
            $project->update(['title_image_path' => $path]);
        }

        if ($causer) {
            ProjectCreatedActivity::dispatch($project, $causer);
        }

        return $project;
    }
}
