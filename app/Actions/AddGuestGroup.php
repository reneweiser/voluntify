<?php

namespace App\Actions;

use App\Jobs\SendGuestInvitationsJob;
use App\Models\GuestEntry;
use App\Models\GuestGroup;
use App\Models\GuestList;
use Illuminate\Support\Facades\DB;

class AddGuestGroup
{
    public function execute(GuestList $guestList, string $label, int $guestCount): GuestGroup
    {
        $group = $guestList->groups()->create([
            'label' => $label,
            'guest_count' => $guestCount,
        ]);

        for ($i = 1; $i <= $guestCount; $i++) {
            $group->entries()->create([
                'number' => $i,
            ]);
        }

        if ($guestList->isConfirmed()) {
            DB::transaction(function () use ($group) {
                $group->entries()->whereNull('qr_token')->each(function (GuestEntry $entry) {
                    $entry->update(['qr_token' => bin2hex(random_bytes(32))]);
                });
            });

            $emails = $group->entries()->whereNotNull('email')->distinct()->pluck('email');
            foreach ($emails as $email) {
                SendGuestInvitationsJob::dispatch($guestList, $email);
            }
        }

        return $group->load('entries');
    }
}
