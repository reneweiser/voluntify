<?php

namespace App\Actions;

use App\Models\Event;
use Illuminate\Support\Facades\Storage;

class DeleteEventImage
{
    public function execute(Event $event): Event
    {
        if ($event->title_image_path) {
            Storage::disk('public')->delete($event->title_image_path);
            $event->update(['title_image_path' => null]);
        }

        return $event->refresh();
    }
}
