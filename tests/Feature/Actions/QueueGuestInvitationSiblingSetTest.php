<?php

use App\Actions\QueueGuestInvitationSiblingSet;
use App\Jobs\SendGuestInvitationsJob;
use App\Models\GuestEntry;
use App\Models\GuestGroup;
use App\Models\GuestList;
use Illuminate\Support\Facades\Queue;

it('claims a pending recipient sibling set only once before dispatching', function () {
    Queue::fake();

    $guestList = GuestList::factory()->confirmed()->create();
    $group = GuestGroup::factory()->for($guestList)->create(['guest_count' => 2]);

    GuestEntry::factory()->for($group, 'group')->withQrToken()->create([
        'number' => 1,
        'email' => 'pending@example.com',
    ]);
    GuestEntry::factory()->for($group, 'group')->create([
        'number' => 2,
        'email' => 'pending@example.com',
        'qr_token' => null,
    ]);

    $action = new QueueGuestInvitationSiblingSet;

    expect($action->claimPending($guestList, 'pending@example.com'))->toBeTrue()
        ->and($action->claimPending($guestList, 'pending@example.com'))->toBeFalse();

    Queue::assertPushed(SendGuestInvitationsJob::class, 1);

    $entries = GuestEntry::where('email', 'pending@example.com')->orderBy('number')->get();

    expect($entries[0]->fresh()->invitation_queued_at)->not->toBeNull()
        ->and($entries[1]->fresh()->invitation_queued_at)->toBeNull();
});

it('resends only terminal failed rows for a failed recipient sibling set', function () {
    Queue::fake();

    $guestList = GuestList::factory()->confirmed()->create();
    $group = GuestGroup::factory()->for($guestList)->create(['guest_count' => 3]);

    $failedEntry = GuestEntry::factory()->for($group, 'group')->withQrToken()->create([
        'number' => 1,
        'email' => 'shared@example.com',
        'invitation_failed_at' => now()->subMinute(),
    ]);
    $sentEntry = GuestEntry::factory()->for($group, 'group')->withQrToken()->create([
        'number' => 2,
        'email' => 'shared@example.com',
        'invitation_sent_at' => now()->subMinutes(5),
    ]);
    $queuedEntry = GuestEntry::factory()->for($group, 'group')->withQrToken()->create([
        'number' => 3,
        'email' => 'shared@example.com',
        'invitation_queued_at' => now()->subMinute(),
    ]);

    $action = new QueueGuestInvitationSiblingSet;

    expect($action->claimFailed($guestList, 'shared@example.com'))->toBeTrue()
        ->and($action->claimFailed($guestList, 'shared@example.com'))->toBeFalse();

    Queue::assertPushed(SendGuestInvitationsJob::class, function (SendGuestInvitationsJob $job) use ($failedEntry, $sentEntry, $queuedEntry) {
        return $job->email === 'shared@example.com'
            && $job->guestEntryIds === [$failedEntry->id]
            && ! in_array($sentEntry->id, $job->guestEntryIds, true)
            && ! in_array($queuedEntry->id, $job->guestEntryIds, true);
    });

    expect($failedEntry->fresh()->invitation_queued_at)->not->toBeNull()
        ->and($failedEntry->fresh()->invitation_failed_at)->toBeNull()
        ->and($sentEntry->fresh()->invitation_sent_at)->not->toBeNull()
        ->and($sentEntry->fresh()->invitation_queued_at)->toBeNull()
        ->and($queuedEntry->fresh()->invitation_queued_at)->not->toBeNull();
});

it('restores pending rows when dispatch throws after claiming', function () {
    $guestList = GuestList::factory()->confirmed()->create();
    $group = GuestGroup::factory()->for($guestList)->create(['guest_count' => 1]);
    $entry = GuestEntry::factory()->for($group, 'group')->withQrToken()->create([
        'email' => 'pending@example.com',
    ]);

    $action = new class extends QueueGuestInvitationSiblingSet
    {
        protected function dispatchClaimedJob(GuestList $guestList, string $email, array $claimedEntryIds): void
        {
            throw new RuntimeException('queue connection down');
        }
    };

    expect(fn () => $action->claimPending($guestList, 'pending@example.com'))
        ->toThrow(RuntimeException::class);

    expect($entry->fresh()->invitation_queued_at)->toBeNull()
        ->and($entry->fresh()->invitation_failed_at)->toBeNull()
        ->and($entry->fresh()->invitation_sent_at)->toBeNull();
});

it('restores failed rows to failed state when dispatch throws after a resend claim', function () {
    $guestList = GuestList::factory()->confirmed()->create();
    $group = GuestGroup::factory()->for($guestList)->create(['guest_count' => 1]);
    $entry = GuestEntry::factory()->for($group, 'group')->withQrToken()->create([
        'email' => 'failed@example.com',
        'invitation_failed_at' => now()->subMinute(),
    ]);

    $action = new class extends QueueGuestInvitationSiblingSet
    {
        protected function dispatchClaimedJob(GuestList $guestList, string $email, array $claimedEntryIds): void
        {
            throw new RuntimeException('queue connection down');
        }
    };

    expect(fn () => $action->claimFailed($guestList, 'failed@example.com'))
        ->toThrow(RuntimeException::class);

    expect($entry->fresh()->invitation_queued_at)->toBeNull()
        ->and($entry->fresh()->invitation_failed_at)->not->toBeNull();
});
