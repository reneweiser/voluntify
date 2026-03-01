<?php

namespace App\Actions;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Organization;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

class CreateEvent
{
    public function execute(
        Organization $organization,
        string $name,
        ?string $description,
        ?string $location,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
    ): Event {
        $slug = $this->uniqueSlug($organization, $name);

        return $organization->events()->create([
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'location' => $location,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => EventStatus::Draft,
        ]);
    }

    private function uniqueSlug(Organization $organization, string $name): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $suffix = 2;

        while ($organization->events()->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
