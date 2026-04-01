<?php

namespace App\Actions;

use App\Enums\GuestListStatus;
use App\Exceptions\DomainException;
use App\Jobs\ConfirmGuestListJob;
use App\Models\GuestList;
use Illuminate\Support\Facades\DB;

class ConfirmGuestList
{
    public function execute(GuestList $guestList): GuestList
    {
        return DB::transaction(function () use ($guestList) {
            $guestList = GuestList::lockForUpdate()->findOrFail($guestList->id);

            if ($guestList->isConfirmed()) {
                throw new DomainException('Guest list is already confirmed.');
            }

            $guestList->update([
                'status' => GuestListStatus::Confirmed,
                'confirmed_at' => now(),
            ]);

            ConfirmGuestListJob::dispatch($guestList);

            return $guestList->fresh();
        });
    }
}
