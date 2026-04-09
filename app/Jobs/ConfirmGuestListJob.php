<?php

namespace App\Jobs;

use App\Models\GuestEntry;
use App\Models\GuestList;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class ConfirmGuestListJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** @var int[] */
    public array $backoff = [10, 30, 60];

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public GuestList $guestList,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->guestList->id;
    }

    public function handle(): void
    {
        if (! $this->guestList->isConfirmed()) {
            return;
        }

        $entries = $this->guestList->entries()->whereNull('qr_token')->get();

        if ($entries->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($entries) {
            $entries->each(function (GuestEntry $entry) {
                $entry->update(['qr_token' => bin2hex(random_bytes(32))]);
            });
        });

        $entries = $this->guestList->entries()
            ->whereNotNull('email')
            ->whereNotNull('qr_token')
            ->get();

        $emails = $entries->pluck('email')->unique();

        foreach ($emails as $email) {
            SendGuestInvitationsJob::dispatch($this->guestList, $email);
        }

        $entries->each(function (GuestEntry $entry) {
            $entry->update(['invitation_sent_at' => now()]);
        });
    }
}
