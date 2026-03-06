<?php

namespace App\Actions;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Organization;
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
    ): Event {
        $slug = Event::generateUniqueSlug($organization, $name);

        $event = $organization->events()->create([
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'location' => $location,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => EventStatus::Draft,
        ]);

        if ($titleImage) {
            $path = $titleImage->store("events/{$event->id}", 'public');
            $event->update(['title_image_path' => $path]);
        }

        return $event;
    }
}
