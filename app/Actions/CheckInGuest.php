<?php

namespace App\Actions;

use App\Exceptions\DomainException;
use App\Models\GuestEntry;

class CheckInGuest
{
    public function execute(GuestEntry $entry, ?int $checkedInBy = null): GuestEntry
    {
        if ($entry->isCheckedIn()) {
            throw new DomainException('Guest is already checked in.');
        }

        $entry->update([
            'checked_in_at' => now(),
            'checked_in_by' => $checkedInBy,
        ]);

        return $entry->fresh();
    }
}
