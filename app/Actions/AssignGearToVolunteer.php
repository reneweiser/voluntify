<?php

namespace App\Actions;

use App\Enums\GearItemType;
use App\Exceptions\DomainException;
use App\Models\Event;
use App\Models\Volunteer;
use App\Models\VolunteerGear;

class AssignGearToVolunteer
{
    /**
     * @param  array<int, string|null>  $gearSelections  Keyed by ProjectGearItem ID => size (or null)
     * @param  array<int>  $volunteerJobIds  Resolved job IDs the volunteer is signed up for
     */
    public function execute(Volunteer $volunteer, Event $event, array $gearSelections = [], array $volunteerJobIds = []): void
    {
        $gearItems = $event->project->gearItems;

        foreach ($gearItems as $item) {
            if ($item->type === GearItemType::Quantity) {
                if ($item->job_ids !== null && empty(array_intersect($item->job_ids, $volunteerJobIds))) {
                    continue;
                }

                VolunteerGear::firstOrCreate(
                    [
                        'project_gear_item_id' => $item->id,
                        'volunteer_id' => $volunteer->id,
                    ],
                    ['quantity_entitled' => $item->quantity_per_volunteer],
                );

                continue;
            }

            if (! array_key_exists($item->id, $gearSelections)) {
                continue;
            }

            $size = $gearSelections[$item->id];

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
