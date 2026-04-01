<?php

namespace App\Actions;

use App\Models\GuestGroup;
use App\Models\GuestList;

class AddGuestGroup
{
    public function execute(GuestList $guestList, string $label, int $guestCount): GuestGroup
    {
        $group = $guestList->groups()->create([
            'label' => $label,
            'guest_count' => $guestCount,
        ]);

        for ($i = 1; $i <= $guestCount; $i++) {
            $group->entries()->create([
                'number' => $i,
            ]);
        }

        return $group->load('entries');
    }
}
