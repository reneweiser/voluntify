<?php

namespace App\Actions;

use App\Models\GuestList;

class DeleteGuestList
{
    public function execute(GuestList $guestList): void
    {
        $guestList->delete();
    }
}
