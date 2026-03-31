<?php

namespace App\Actions;

use App\Models\User;
use App\Models\VolunteerGear;
use App\Models\VolunteerGearPickup;

class RecordGearPickup
{
    public function execute(VolunteerGear $gear, User $user, ?string $state = null, int $quantity = 1): VolunteerGearPickup
    {
        return VolunteerGearPickup::create([
            'volunteer_gear_id' => $gear->id,
            'picked_up_by' => $user->id,
            'picked_up_at' => now(),
            'state' => $state,
            'quantity' => $quantity,
        ]);
    }
}
