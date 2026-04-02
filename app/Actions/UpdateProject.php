<?php

namespace App\Actions;

use App\Events\Activity\ProjectUpdated as ProjectUpdatedActivity;
use App\Models\Project;
use App\Models\User;
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
        ?string $senderName = null,
        ?string $contactEmail = null,
        ?bool $cancellationEnabled = null,
        ?int $cancellationCutoffHours = null,
        ?User $causer = null,
    ): Project {
        $original = [
            'name' => $project->name,
            'description' => $project->description,
            'title_image_path' => $project->title_image_path,
            'sender_name' => $project->sender_name,
            'contact_email' => $project->contact_email,
            'cancellation_enabled' => $project->cancellation_enabled,
            'cancellation_cutoff_hours' => $project->cancellation_cutoff_hours,
        ];

        $updateData = [
            'name' => $name,
            'description' => $description,
            'sender_name' => $senderName,
            'contact_email' => $contactEmail,
        ];

        if ($cancellationEnabled !== null) {
            $updateData['cancellation_enabled'] = $cancellationEnabled;
            $updateData['cancellation_cutoff_hours'] = $cancellationCutoffHours;
        }

        $project->update($updateData);

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

        if ($changed && $causer) {
            ProjectUpdatedActivity::dispatch($project, $causer, $changed);
        }

        return $project;
    }
}
