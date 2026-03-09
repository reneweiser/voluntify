<?php

namespace App\Actions;

use App\Models\EventGroup;
use App\Models\Organization;
use Illuminate\Http\UploadedFile;

class CreateEventGroup
{
    public function execute(
        Organization $organization,
        string $name,
        ?string $description = null,
        ?UploadedFile $titleImage = null,
    ): EventGroup {
        $group = $organization->eventGroups()->create([
            'name' => $name,
            'description' => $description,
        ]);

        if ($titleImage) {
            $path = $titleImage->store("event-groups/{$group->id}", 'public');
            $group->update(['title_image_path' => $path]);
        }

        return $group;
    }
}
