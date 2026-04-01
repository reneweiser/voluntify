<?php

namespace App\Actions;

use App\Models\Event;
use Illuminate\Support\Facades\Storage;

class PermanentlyDeleteEvent
{
    public function execute(Event $event): void
    {
        if ($event->title_image_path) {
            Storage::disk('public')->delete($event->title_image_path);
        }

        // Database cascades handle all related records (jobs, shifts, signups, arrivals, etc.)
        $event->delete();
    }
}
