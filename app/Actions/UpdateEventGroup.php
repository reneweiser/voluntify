<?php

namespace App\Actions;

use App\Models\EventGroup;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UpdateEventGroup
{
    public function execute(
        EventGroup $eventGroup,
        string $name,
        ?string $description = null,
        ?UploadedFile $titleImage = null,
        bool $removeTitleImage = false,
    ): EventGroup {
        $eventGroup->update([
            'name' => $name,
            'description' => $description,
        ]);

        if ($titleImage) {
            if ($eventGroup->title_image_path) {
                Storage::disk('public')->delete($eventGroup->title_image_path);
            }

            $path = $titleImage->store("event-groups/{$eventGroup->id}", 'public');
            $eventGroup->update(['title_image_path' => $path]);
        } elseif ($removeTitleImage && $eventGroup->title_image_path) {
            Storage::disk('public')->delete($eventGroup->title_image_path);
            $eventGroup->update(['title_image_path' => null]);
        }

        return $eventGroup;
    }
}
