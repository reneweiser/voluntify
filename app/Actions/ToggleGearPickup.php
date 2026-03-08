<?php

namespace App\Actions;

use App\Models\User;
use App\Models\VolunteerGear;

class ToggleGearPickup
{
    public function execute(VolunteerGear $gear, User $user): void
    {
        if ($gear->picked_up_at) {
            $gear->update([
                'picked_up_at' => null,
                'picked_up_by' => null,
            ]);
        } else {
            $gear->update([
                'picked_up_at' => now(),
                'picked_up_by' => $user->id,
            ]);
        }
    }
}
