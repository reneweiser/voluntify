<?php

namespace App\Actions;

use App\Exceptions\DomainException;
use App\Models\Event;
use App\Models\Volunteer;
use App\Models\VolunteerGear;

class AssignGearToVolunteer
{
    /**
     * @param  array<int, string|null>  $gearSelections  Keyed by ProjectGearItem ID => size (or null)
     */
    public function execute(Volunteer $volunteer, Event $event, array $gearSelections = []): void
    {
        $gearItems = $event->project->gearItems;

        foreach ($gearItems as $item) {
            $size = $gearSelections[$item->id] ?? null;

            if ($item->requires_size) {
                if ($size === null) {
                    throw new DomainException("Size is required for \"{$item->name}\".");
                }

                if (! in_array($size, $item->available_sizes ?? [], true)) {
                    throw new DomainException("Invalid size \"{$size}\" for \"{$item->name}\".");
                }
            }

            VolunteerGear::firstOrCreate(
                [
                    'project_gear_item_id' => $item->id,
                    'volunteer_id' => $volunteer->id,
                ],
                ['size' => $size],
            );
        }
    }
}
