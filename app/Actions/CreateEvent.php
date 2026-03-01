<?php

namespace App\Actions;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Organization;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

class CreateEvent
{
    public function __construct(private Organization $organization) {}

    public function execute(
        string $name,
        ?string $description,
        ?string $location,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
    ): Event {
        return $this->organization->events()->create([
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => $description,
            'location' => $location,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => EventStatus::Draft,
        ]);
    }
}
