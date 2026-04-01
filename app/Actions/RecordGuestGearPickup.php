<?php

namespace App\Actions;

use App\Exceptions\DomainException;
use App\Models\GuestEntryGear;

class RecordGuestGearPickup
{
    public function execute(GuestEntryGear $gear, array $data): GuestEntryGear
    {
        if (isset($data['selection'])) {
            $gear->selection = $data['selection'];
        }

        if (isset($data['status'])) {
            $gear->status = $data['status'];
        }

        if (isset($data['quantity'])) {
            $newCount = $gear->picked_up_count + $data['quantity'];

            if ($newCount > $gear->quantity) {
                throw new DomainException('Pickup count would exceed available quantity.');
            }

            $gear->picked_up_count = $newCount;
        }

        $gear->save();

        return $gear->fresh();
    }
}
