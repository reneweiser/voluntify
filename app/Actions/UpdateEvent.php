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

        $event->update([
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => $description,
            'location' => $location,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        return $event->refresh();
    }
}
