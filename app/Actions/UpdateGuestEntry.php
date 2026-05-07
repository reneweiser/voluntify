<?php

namespace App\Actions;

use App\Models\GuestEntry;
use Illuminate\Support\Facades\DB;

class UpdateGuestEntry
{
    public function execute(GuestEntry $entry, array $data): GuestEntry
    {
        $originalEmail = $entry->email;
        $guestList = $entry->group->guestList;
        $emailWasUpdated = array_key_exists('email', $data);
        $requestedEmail = $emailWasUpdated ? $data['email'] : $originalEmail;
        $failedSiblingEntryIds = [];

        if ($guestList->isConfirmed() && $entry->isInvitationFailed() && $originalEmail !== null) {
            $failedSiblingEntryIds = $guestList->entries()
                ->where('email', $originalEmail)
                ->whereNull('invitation_sent_at')
                ->whereNull('invitation_queued_at')
                ->whereNotNull('invitation_failed_at')
                ->pluck('guest_entries.id')
                ->all();
        }

        $isFailedSiblingSetCorrection = $requestedEmail !== null
            && $requestedEmail !== $originalEmail
            && in_array($entry->id, $failedSiblingEntryIds, true);

        $changedEntryIds = [$entry->id];

        DB::transaction(function () use ($entry, $data, $isFailedSiblingSetCorrection, $emailWasUpdated, $requestedEmail, $failedSiblingEntryIds, $guestList, $originalEmail, &$changedEntryIds) {
            $entry->update([
                'name' => array_key_exists('name', $data) ? $data['name'] : $entry->name,
                'email' => $isFailedSiblingSetCorrection
                    ? $entry->email
                    : ($emailWasUpdated ? $requestedEmail : $entry->email),
            ]);

            if ($isFailedSiblingSetCorrection) {
                GuestEntry::query()
                    ->whereKey($failedSiblingEntryIds)
                    ->update([
                        'email' => $requestedEmail,
                    ]);

                $changedEntryIds = $failedSiblingEntryIds;

                return;
            }

            if ($guestList->isConfirmed() && $emailWasUpdated && $requestedEmail !== null && $requestedEmail !== $originalEmail) {
                GuestEntry::query()
                    ->whereKey([$entry->id])
                    ->update([
                        'invitation_sent_at' => null,
                        'invitation_queued_at' => null,
                        'invitation_failed_at' => null,
                    ]);
            }
        });

        if (isset($data['gear'])) {
            foreach ($data['gear'] as $gearData) {
                $entry->gear()->updateOrCreate(
                    ['project_gear_item_id' => $gearData['project_gear_item_id']],
                    [
                        'quantity' => $gearData['quantity'] ?? 1,
                        'selection' => $gearData['selection'] ?? null,
                    ]
                );
            }
        }

        $entry = $entry->fresh();
        $newEmail = $entry->email;

        if ($guestList->isConfirmed() && $newEmail !== null && $newEmail !== $originalEmail) {
            $qrTokenQuery = $isFailedSiblingSetCorrection
                ? GuestEntry::query()->whereKey($failedSiblingEntryIds)
                : GuestEntry::query()->whereKey($changedEntryIds);

            $qrTokenQuery
                ->whereNull('qr_token')
                ->get()
                ->each(fn (GuestEntry $sibling) => $sibling->update(['qr_token' => bin2hex(random_bytes(32))]));

            $queueGuestInvitationSiblingSet = app(QueueGuestInvitationSiblingSet::class);

            if ($isFailedSiblingSetCorrection) {
                $queueGuestInvitationSiblingSet->claimFailedEntries($guestList, $newEmail, $failedSiblingEntryIds);
            } else {
                $queueGuestInvitationSiblingSet->claimPendingEntries($guestList, $newEmail, $changedEntryIds);
            }
        }

        return $entry->fresh()->load('gear');
    }
}
