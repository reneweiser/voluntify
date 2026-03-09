<?php

namespace App\Actions;

use App\Models\EventGroup;
use Illuminate\Support\Facades\Storage;

class DeleteEventGroup
{
    public function execute(EventGroup $eventGroup): void
    {
        if ($eventGroup->title_image_path) {
            Storage::disk('public')->delete($eventGroup->title_image_path);
        }

        $eventGroup->delete();
    }
}
