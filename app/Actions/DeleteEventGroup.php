<?php

namespace App\Actions;

use App\Events\Activity\EventGroupDeleted as EventGroupDeletedActivity;
use App\Models\EventGroup;
use Illuminate\Support\Facades\Storage;

class DeleteEventGroup
{
    public function execute(EventGroup $eventGroup): void
    {
        $groupName = $eventGroup->name;
        $organizationId = $eventGroup->organization_id;
        $ungroupedEventNames = $eventGroup->events()->pluck('name')->all();

        if ($eventGroup->title_image_path) {
            Storage::disk('public')->delete($eventGroup->title_image_path);
        }

        $eventGroup->delete();

        if (auth()->user()) {
            EventGroupDeletedActivity::dispatch($groupName, $organizationId, $ungroupedEventNames, auth()->user());
        }
    }
}
