<?php

namespace App\Actions;

use App\Events\Activity\EventImageDeleted;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class DeleteEventImage
{
    public function execute(Event $event, User $causer): Event
    {
        if ($event->title_image_path) {
            Storage::disk('public')->delete($event->title_image_path);
            $event->update(['title_image_path' => null]);

            EventImageDeleted::dispatch($event, $causer);
        }

        return $event->refresh();
    }
}
