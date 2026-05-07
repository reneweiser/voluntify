<?php

namespace App\Jobs;

use App\Mail\GuestInvitationMail;
use App\Models\GuestList;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Date;
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
        public array $guestEntryIds,
    ) {}

    public function handle(): void
    {
        $entries = $this->guestList->entries()
            ->with(['group.guestList.project', 'group.guestList.scanner'])
            ->whereKey($this->guestEntryIds)
            ->where('email', $this->email)
            ->whereNotNull('qr_token')
            ->whereNull('invitation_sent_at')
            ->whereNotNull('invitation_queued_at')
            ->whereNull('invitation_failed_at')
            ->get();

        if ($entries->isEmpty()) {
            return;
        }

        Mail::to($this->email)
            ->send(new GuestInvitationMail($this->guestList, $entries));

        $this->guestList->entries()
            ->whereKey($entries->modelKeys())
            ->update([
                'invitation_sent_at' => Date::now(),
                'invitation_queued_at' => null,
                'invitation_failed_at' => null,
            ]);
    }

    public function failed(\Throwable $exception): void
    {
        $this->guestList->entries()
            ->whereKey($this->guestEntryIds)
            ->where('email', $this->email)
            ->whereNull('invitation_sent_at')
            ->whereNotNull('invitation_queued_at')
            ->whereNull('invitation_failed_at')
            ->update([
                'invitation_queued_at' => null,
                'invitation_failed_at' => Date::now(),
            ]);
    }
}
