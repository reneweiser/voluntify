<?php

namespace App\Actions;

use App\Events\Activity\ProjectUpdated as ProjectUpdatedActivity;
use App\Models\Project;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UpdateProject
{
    public function execute(
        Project $project,
        string $name,
        ?string $description = null,
        ?UploadedFile $titleImage = null,
        bool $removeTitleImage = false,
    ): Project {
        $original = [
            'name' => $project->name,
            'description' => $project->description,
            'title_image_path' => $project->title_image_path,
        ];

        $project->update([
            'name' => $name,
            'description' => $description,
        ]);

        if ($titleImage) {
            if ($project->title_image_path) {
                Storage::disk('public')->delete($project->title_image_path);
            }

            $path = $titleImage->store("projects/{$project->id}", 'public');
            $project->update(['title_image_path' => $path]);
        } elseif ($removeTitleImage && $project->title_image_path) {
            Storage::disk('public')->delete($project->title_image_path);
            $project->update(['title_image_path' => null]);
        }

        $changed = [];
        foreach ($original as $key => $value) {
            if ($project->{$key} !== $value) {
                $changed[$key] = [$value, $project->{$key}];
            }
        }

        if ($changed && auth()->user()) {
            ProjectUpdatedActivity::dispatch($project, auth()->user(), $changed);
        }

        return $project;
    }
}
