<?php

namespace App\Actions;

use App\Enums\ActivityCategory;
use App\Enums\GearItemType;
use App\Exceptions\DomainException;
use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\User;
use App\Models\Volunteer;
use App\Models\VolunteerGear;
use App\Notifications\VolunteerEventUpdatedNotification;

class UpdateVolunteerGearSelection
{
    public function __construct(
        private GenerateMagicLink $generateMagicLink,
    ) {}

    public function execute(VolunteerGear $gear, Event $event, string $selection, User $causer): VolunteerGear
    {
        $gear->loadMissing('gearItem', 'volunteer');

        if ($gear->gearItem->project_id !== $event->project_id || $gear->volunteer->project_id !== $event->project_id) {
            throw new DomainException('Dieses Gear gehört nicht zu diesem Event.');
        }

        if ($gear->gearItem->type !== GearItemType::SizeSelection || ! $gear->gearItem->requires_size) {
            throw new DomainException('Nur Gear vom Typ 1 kann hier bearbeitet werden.');
        }

        if (! in_array($selection, $gear->gearItem->available_sizes ?? [], true)) {
            throw new DomainException('Die gewählte Auswahl ist für dieses Gear nicht verfügbar.');
        }

        if ($gear->size === $selection) {
            return $gear->refresh();
        }

        $previousSelection = $gear->size ?? 'Auswahl ausstehend';

        $gear->update(['size' => $selection]);

        ActivityLog::create([
            'organization_id' => $event->organization_id,
            'project_id' => $event->project_id,
            'event_id' => $event->id,
            'causer_type' => $causer::class,
            'causer_id' => $causer->id,
            'subject_type' => Volunteer::class,
            'subject_id' => $gear->volunteer->id,
            'action' => 'updated',
            'category' => ActivityCategory::Volunteer,
            'description' => "Updated {$gear->gearItem->name} selection for {$gear->volunteer->full_name}",
            'properties' => [
                'gear_item_name' => $gear->gearItem->name,
                'previous_selection' => $previousSelection,
                'new_selection' => $selection,
            ],
        ]);

        ['plainToken' => $plainToken] = $this->generateMagicLink->execute($gear->volunteer);

        $gear->volunteer->notify(new VolunteerEventUpdatedNotification(
            event: $event,
            organizerNote: "Die Auswahl fuer \"{$gear->gearItem->name}\" wurde von \"{$previousSelection}\" auf \"{$selection}\" geaendert.",
            magicLinkToken: $plainToken,
        ));

        return $gear->refresh();
    }
}
