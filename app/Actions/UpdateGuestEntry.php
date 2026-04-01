<?php

namespace App\Actions;

use App\Models\GuestEntry;

class UpdateGuestEntry
{
    public function execute(GuestEntry $entry, array $data): GuestEntry
    {
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

        return $entry->fresh()->load('gear');
    }
}
