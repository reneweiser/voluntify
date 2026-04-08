<?php

namespace App\Actions;

use App\Exceptions\DomainException;
use App\Models\User;
use App\Models\VolunteerGear;
use App\Models\VolunteerGearPickup;
use Illuminate\Support\Facades\DB;

class RecordGearPickup
{
    public function execute(VolunteerGear $gear, ?User $user, ?string $state = null, int $quantity = 1): VolunteerGearPickup
    {
        return DB::transaction(function () use ($gear, $user, $state, $quantity) {
            $lockedGear = VolunteerGear::lockForUpdate()->find($gear->id);

            if ($lockedGear->quantity_entitled !== null) {
                $currentTotal = $lockedGear->pickups()->sum('quantity');

                if (($currentTotal + $quantity) > $lockedGear->quantity_entitled) {
                    throw new DomainException('Pickup would exceed entitled quantity.');
                }
            }

            return VolunteerGearPickup::create([
                'volunteer_gear_id' => $lockedGear->id,
                'picked_up_by' => $user?->id,
                'picked_up_at' => now(),
                'state' => $state,
                'quantity' => $quantity,
            ]);
        });
    }
}
