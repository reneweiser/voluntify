<?php

namespace App\Actions;

use App\Exceptions\HasGuestListsException;
use App\Models\ProjectScanner;

class DeleteProjectScanner
{
    public function execute(ProjectScanner $scanner): void
    {
        if ($scanner->guestLists()->exists()) {
            throw new HasGuestListsException(
                'Scanner mit aktiven Gästelisten kann nicht gelöscht werden.'
            );
        }

        $scanner->delete();
    }
}
