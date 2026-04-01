<?php

namespace App\Actions;

use App\Enums\EventStatus;
use App\Events\Activity\EventCloned;
use App\Models\Event;
use Illuminate\Support\Facades\DB;

class CloneEvent
{
    public function execute(Event $event, ?int $targetProjectId = null, ?int $dateOffsetDays = null): Event
    {
        $clonedEvent = DB::transaction(function () use ($event, $targetProjectId, $dateOffsetDays) {
            $event->load(['volunteerJobs.shifts', 'customRegistrationFields', 'emailTemplates']);

            $clonedEvent = $event->replicate([
                'id',
                'slug',
                'public_token',
                'title_image_path',
                'created_at',
                'updated_at',
                'was_previously_published',
                'deletion_requested_at',
            ]);

            $clonedEvent->name = "{$event->name} (Copy)";
            $clonedEvent->status = EventStatus::Draft;
            $clonedEvent->was_previously_published = false;
            $clonedEvent->slug = Event::generateUniqueSlug($event->organization, $clonedEvent->name);

            if ($targetProjectId) {
                $clonedEvent->project_id = $targetProjectId;
            }

            if ($dateOffsetDays) {
                $clonedEvent->starts_at = $event->starts_at->addDays($dateOffsetDays);
                $clonedEvent->ends_at = $event->ends_at->addDays($dateOffsetDays);
            }

            $clonedEvent->save();

            foreach ($event->volunteerJobs as $job) {
                $clonedJob = $job->replicate(['id', 'event_id', 'created_at', 'updated_at']);
                $clonedJob->event_id = $clonedEvent->id;
                $clonedJob->save();

                foreach ($job->shifts as $shift) {
                    $clonedShift = $shift->replicate(['id', 'volunteer_job_id', 'created_at', 'updated_at']);
                    $clonedShift->volunteer_job_id = $clonedJob->id;

                    if ($dateOffsetDays) {
                        $clonedShift->shift_date = $shift->shift_date->addDays($dateOffsetDays);
                        if ($shift->starts_at) {
                            $clonedShift->starts_at = $shift->starts_at->addDays($dateOffsetDays);
                        }
                        if ($shift->ends_at) {
                            $clonedShift->ends_at = $shift->ends_at->addDays($dateOffsetDays);
                        }
                    }

                    $clonedShift->save();
                }
            }

            foreach ($event->customRegistrationFields as $field) {
                $clonedField = $field->replicate(['id', 'event_id', 'deleted_at', 'created_at', 'updated_at']);
                $clonedField->event_id = $clonedEvent->id;
                $clonedField->save();
            }

            foreach ($event->emailTemplates as $template) {
                $clonedTemplate = $template->replicate(['id', 'event_id', 'created_at', 'updated_at']);
                $clonedTemplate->event_id = $clonedEvent->id;
                $clonedTemplate->save();
            }

            return $clonedEvent->fresh();
        });

        if (auth()->user()) {
            EventCloned::dispatch($clonedEvent, $event, auth()->user());
        }

        return $clonedEvent;
    }
}
