<?php

namespace App\Actions;

use App\Exceptions\HasSignupsException;
use App\Models\Shift;

class DeleteShift
{
    public function execute(Shift $shift): void
    {
        if ($shift->signups()->exists()) {
            throw new HasSignupsException('Cannot delete a shift that has volunteer signups.');
        }

        $shift->delete();
    }
}
