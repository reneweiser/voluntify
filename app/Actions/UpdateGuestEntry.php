<?php

namespace App\Actions;

use App\Jobs\SendGuestInvitationsJob;
use App\Models\GuestEntry;

class UpdateGuestEntry
{
    public function execute(GuestEntry $entry, array $data): GuestEntry
    {
        $originalEmail = $entry->email;

        $entry->update([
            'name' => array_key_exists('name', $data) ? $data['name'] : $entry->name,
            'email' => array_key_exists('email', $data) ? $data['email'] : $entry->email,
        ]);

        if (isset($data['gear'])) {
            foreach ($data['gear'] as $gearData) {
                $entry->gear()->updateOrCreate(
                    ['project_gear_item_id' => $gearData['project_gear_item_id']],
                    [
                        'quantity' => $gearData['quantity'] ?? 1,
                        'selection' => $gearData['selection'] ?? null,
                    ]
                );
            }
        }

        $newEmail = $entry->email;
        $guestList = $entry->group->guestList;

        if ($guestList->isConfirmed() && $newEmail !== null && $newEmail !== $originalEmail) {
            if (! $entry->qr_token) {
                $entry->update(['qr_token' => bin2hex(random_bytes(32))]);
            }

            SendGuestInvitationsJob::dispatch($guestList, $newEmail);
            $entry->update(['invitation_sent_at' => now()]);
        }

        return $entry->fresh()->load('gear');
    }
}
