<?php

namespace App\Actions;

use App\Jobs\SendGuestInvitationsJob;
use App\Models\GuestEntry;
use App\Models\GuestGroup;

class AddGuestEntry
{
    public function execute(GuestGroup $group, ?string $name = null, ?string $email = null, array $gearSelections = []): GuestEntry
    {
        $nextNumber = ($group->entries()->max('number') ?? 0) + 1;

        $entry = $group->entries()->create([
            'number' => $nextNumber,
            'name' => $name,
            'email' => $email,
        ]);

        foreach ($gearSelections as $gearSelection) {
            $entry->gear()->create([
                'project_gear_item_id' => $gearSelection['project_gear_item_id'],
                'quantity' => $gearSelection['quantity'] ?? 1,
                'selection' => $gearSelection['selection'] ?? null,
            ]);
        }

        $guestList = $group->guestList;

        if ($guestList->isConfirmed()) {
            $entry->update(['qr_token' => bin2hex(random_bytes(32))]);

            if ($email) {
                SendGuestInvitationsJob::dispatch($guestList, $email);
            }
        }

        return $entry->load('gear');
    }
}
