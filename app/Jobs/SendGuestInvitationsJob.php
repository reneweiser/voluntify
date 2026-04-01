<?php

namespace App\Jobs;

use App\Mail\GuestInvitationMail;
use App\Models\GuestList;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendGuestInvitationsJob implements ShouldQueue
{
    use Queueable;

    /** @var int[] */
    public array $backoff = [10, 30, 60];

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public GuestList $guestList,
        public string $email,
    ) {}

    public function handle(): void
    {
        $entries = $this->guestList->entries()
            ->where('email', $this->email)
            ->whereNotNull('qr_token')
            ->get();

        if ($entries->isEmpty()) {
            return;
        }

        Mail::to($this->email)
            ->send(new GuestInvitationMail($this->guestList, $entries));
    }
}
