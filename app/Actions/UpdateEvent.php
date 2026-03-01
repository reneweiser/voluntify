<?php

namespace App\Actions;

use App\Enums\EventStatus;
use App\Exceptions\DomainException;
use App\Models\Event;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UpdateEvent
{
    public function execute(
        Event $event,
        string $name,
        ?string $description,
        ?string $location,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        ?UploadedFile $titleImage = null,
    ): Event {
        if ($event->status === EventStatus::Archived) {
            throw new DomainException('Cannot update an archived event.');
        }

        $slug = $this->uniqueSlug($event, $name);

        $updateData = [
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'location' => $location,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ];

        if ($titleImage) {
            if ($event->title_image_path) {
                Storage::disk('public')->delete($event->title_image_path);
            }

            $updateData['title_image_path'] = $titleImage->store("events/{$event->id}", 'public');
        }

        $event->update($updateData);

        return $event->refresh();
    }

    private function uniqueSlug(Event $event, string $name): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $suffix = 2;

        while (
            $event->organization->events()
                ->where('slug', $slug)
                ->where('id', '!=', $event->id)
                ->exists()
        ) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
