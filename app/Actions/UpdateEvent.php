<?php

namespace App\Actions;

use App\Enums\EventStatus;
use App\Exceptions\DomainException;
use App\Models\Event;
use Carbon\CarbonInterface;
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
    ): Event {
        if ($event->status === EventStatus::Archived) {
            throw new DomainException('Cannot update an archived event.');
        }

        $slug = $this->uniqueSlug($event, $name);

        $event->update([
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'location' => $location,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

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
