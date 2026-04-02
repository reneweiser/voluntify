<?php

namespace App\Actions;

use App\Exceptions\DomainException;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RequestEventDeletion
{
    public function execute(Event $event, string $password, User $causer): Event
    {
        if (! Hash::check($password, $causer->password)) {
            throw new DomainException('Falsches Passwort.');
        }

        if ($event->isPendingDeletion()) {
            throw new DomainException('Event ist bereits zur Löschung vorgemerkt.');
        }

        if ($event->status->isPublished()) {
            throw new DomainException('Veröffentlichte Events müssen zuerst archiviert werden.');
        }

        $event->update(['deletion_requested_at' => now()]);

        return $event->refresh();
    }
}
