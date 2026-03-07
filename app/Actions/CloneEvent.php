<?php

namespace App\Actions;

use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Support\Facades\DB;

class CloneEvent
{
    public function execute(Event $event): Event
    {
        return DB::transaction(function () use ($event) {
            $event->load('volunteerJobs.shifts');

            $clonedEvent = $event->replicate([
                'id',
                'slug',
                'public_token',
                'title_image_path',
                'created_at',
                'updated_at',
            ]);

            $clonedEvent->name = "{$event->name} (Copy)";
            $clonedEvent->status = EventStatus::Draft;
            $clonedEvent->slug = Event::generateUniqueSlug($event->organization, $clonedEvent->name);
            $clonedEvent->save();

            foreach ($event->volunteerJobs as $job) {
                $clonedJob = $job->replicate(['id', 'event_id', 'created_at', 'updated_at']);
                $clonedJob->event_id = $clonedEvent->id;
                $clonedJob->save();

                foreach ($job->shifts as $shift) {
                    $clonedShift = $shift->replicate(['id', 'volunteer_job_id', 'created_at', 'updated_at']);
                    $clonedShift->volunteer_job_id = $clonedJob->id;
                    $clonedShift->save();
                }
            }

            return $clonedEvent->fresh();
        });
    }
}
