<?php

namespace App\Actions;

use App\Jobs\SendGuestInvitationsJob;
use App\Models\GuestEntry;
use App\Models\GuestList;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

class QueueGuestInvitationSiblingSet
{
    public function claimPending(GuestList $guestList, string $email): bool
    {
        return $this->claim(
            guestList: $guestList,
            email: $email,
            claimableEntries: fn ($query) => $query
                ->whereNotNull('qr_token')
                ->whereNull('invitation_sent_at')
                ->whereNull('invitation_queued_at')
                ->whereNull('invitation_failed_at'),
            restoreFailedState: false,
        );
    }

    public function claimPendingEntries(GuestList $guestList, string $email, array $entryIds): bool
    {
        return $this->claim(
            guestList: $guestList,
            email: $email,
            claimableEntries: fn ($query) => $query
                ->whereKey($entryIds)
                ->whereNotNull('qr_token')
                ->whereNull('invitation_sent_at')
                ->whereNull('invitation_queued_at')
                ->whereNull('invitation_failed_at'),
            restoreFailedState: false,
        );
    }

    public function claimFailed(GuestList $guestList, string $email): bool
    {
        return $this->claim(
            guestList: $guestList,
            email: $email,
            claimableEntries: fn ($query) => $query
                ->whereNotNull('qr_token')
                ->whereNull('invitation_sent_at')
                ->whereNull('invitation_queued_at')
                ->whereNotNull('invitation_failed_at'),
            restoreFailedState: true,
        );
    }

    public function claimFailedEntries(GuestList $guestList, string $email, array $entryIds): bool
    {
        return $this->claim(
            guestList: $guestList,
            email: $email,
            claimableEntries: fn ($query) => $query
                ->whereKey($entryIds)
                ->whereNotNull('qr_token')
                ->whereNull('invitation_sent_at')
                ->whereNull('invitation_queued_at')
                ->whereNotNull('invitation_failed_at'),
            restoreFailedState: true,
        );
    }

    private function claim(GuestList $guestList, string $email, callable $claimableEntries, bool $restoreFailedState): bool
    {
        $claimedEntryIds = DB::transaction(function () use ($guestList, $email, $claimableEntries) {
            $candidateEntries = $guestList->entries()
                ->select('guest_entries.id')
                ->where('email', $email)
                ->lockForUpdate();

            $claimableEntries($candidateEntries);

            $claimedEntryIds = $candidateEntries
                ->pluck('guest_entries.id')
                ->all();

            if ($claimedEntryIds === []) {
                return [];
            }

            GuestEntry::query()
                ->whereKey($claimedEntryIds)
                ->update([
                    'invitation_sent_at' => null,
                    'invitation_queued_at' => now(),
                    'invitation_failed_at' => null,
                ]);

            return $claimedEntryIds;
        });

        if ($claimedEntryIds === []) {
            return false;
        }

        try {
            $this->dispatchClaimedJob($guestList, $email, $claimedEntryIds);
        } catch (\Throwable $exception) {
            GuestEntry::query()
                ->whereKey($claimedEntryIds)
                ->update([
                    'invitation_queued_at' => null,
                    'invitation_failed_at' => $restoreFailedState ? Date::now() : null,
                ]);

            throw $exception;
        }

        return true;
    }

    /**
     * @param  int[]  $claimedEntryIds
     */
    protected function dispatchClaimedJob(GuestList $guestList, string $email, array $claimedEntryIds): void
    {
        SendGuestInvitationsJob::dispatch($guestList, $email, $claimedEntryIds);
    }
}
