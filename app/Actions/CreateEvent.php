<?php

namespace App\Actions;

use App\Enums\EventStatus;
use App\Events\Activity\EventCreated;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;

class CreateEvent
{
    public function execute(
        Organization $organization,
        string $name,
        ?string $description,
        ?string $location,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        ?UploadedFile $titleImage = null,
        ?Project $project = null,
    ): Event {
        $slug = Event::generateUniqueSlug($organization, $name);

        if (! $project) {
            $project = $organization->projects()->create(['name' => $name]);
        }

        $event = new Event;
        $event->organization_id = $organization->id;
        $event->project_id = $project->id;
        $event->name = $name;
        $event->slug = $slug;
        $event->description = $description;
        $event->location = $location;
        $event->starts_at = $startsAt;
        $event->ends_at = $endsAt;
        $event->status = EventStatus::Draft;
        $event->save();

        if ($titleImage) {
            $path = $titleImage->store("events/{$event->id}", 'public');
            $event->update(['title_image_path' => $path]);
        }

        if (auth()->user()) {
            EventCreated::dispatch($event, auth()->user());
        }

        return $event;
    }
}
