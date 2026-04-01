<?php

namespace App\Actions;

use App\Models\GuestEntry;

class RemoveGuestEntry
{
    public function execute(GuestEntry $entry): void
    {
        $entry->delete();
    }
}
