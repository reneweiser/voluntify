<?php

namespace App\Actions;

use App\Events\Activity\EventGroupUpdated as EventGroupUpdatedActivity;
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
        $original = [
            'name' => $eventGroup->name,
            'description' => $eventGroup->description,
            'title_image_path' => $eventGroup->title_image_path,
        ];

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

        $changed = [];
        foreach ($original as $key => $value) {
            if ($eventGroup->{$key} !== $value) {
                $changed[$key] = [$value, $eventGroup->{$key}];
            }
        }

        if ($changed && auth()->user()) {
            EventGroupUpdatedActivity::dispatch($eventGroup, auth()->user(), $changed);
        }

        return $eventGroup;
    }
}
