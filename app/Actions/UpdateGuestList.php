<?php

namespace App\Actions;

use App\Models\GuestList;

class UpdateGuestList
{
    public function execute(GuestList $guestList, array $data): GuestList
    {
        $guestList->update([
            'name' => $data['name'] ?? $guestList->name,
            'scanner_id' => $data['scanner_id'] ?? $guestList->scanner_id,
            'gear_items' => array_key_exists('gear_items', $data) ? $data['gear_items'] : $guestList->gear_items,
        ]);

        return $guestList->fresh();
    }
}
