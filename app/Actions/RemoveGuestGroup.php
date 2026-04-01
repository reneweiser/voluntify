<?php

namespace App\Actions;

use App\Models\GuestGroup;

class RemoveGuestGroup
{
    public function execute(GuestGroup $group): void
    {
        $group->delete();
    }
}
