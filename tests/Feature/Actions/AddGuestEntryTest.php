<?php

use App\Actions\AddGuestEntry;
use App\Jobs\SendGuestInvitationsJob;
use App\Models\GuestEntry;
use App\Models\GuestGroup;
use App\Models\GuestList;
use App\Models\ProjectGearItem;
use Illuminate\Support\Facades\Queue;

it('adds entry to draft list without QR token', function () {
    $group = GuestGroup::factory()->create(['guest_count' => 3]);
    GuestEntry::factory()->for($group, 'group')->create(['number' => 1]);

    $action = new AddGuestEntry;
    $entry = $action->execute($group, 'DJ Soundwave', 'dj@example.com');

    expect($entry->number)->toBe(2)
        ->and($entry->name)->toBe('DJ Soundwave')
        ->and($entry->email)->toBe('dj@example.com')
        ->and($entry->qr_token)->toBeNull();
});

it('adds entry to confirmed list and generates QR token and dispatches email', function () {
    Queue::fake();

    $guestList = GuestList::factory()->confirmed()->create();
    $group = GuestGroup::factory()->for($guestList)->create(['guest_count' => 2]);

    $action = new AddGuestEntry;
    $entry = $action->execute($group, 'VIP Guest', 'vip@example.com');

    expect($entry->qr_token)->not->toBeNull()
        ->and(strlen($entry->qr_token))->toBe(64);

    Queue::assertPushed(SendGuestInvitationsJob::class, function ($job) {
        return $job->email === 'vip@example.com';
    });

    expect($entry->fresh()->invitation_queued_at)->not->toBeNull()
        ->and($entry->fresh()->invitation_sent_at)->toBeNull();
});

it('adds entry to confirmed list without email and skips email dispatch', function () {
    Queue::fake();

    $guestList = GuestList::factory()->confirmed()->create();
    $group = GuestGroup::factory()->for($guestList)->create(['guest_count' => 2]);

    $action = new AddGuestEntry;
    $entry = $action->execute($group, 'Companion');

    expect($entry->qr_token)->not->toBeNull();

    Queue::assertNothingPushed();
});

it('assigns gear to entry', function () {
    $guestList = GuestList::factory()->create();
    $group = GuestGroup::factory()->for($guestList)->create();
    $gearItem = ProjectGearItem::factory()->create();

    $action = new AddGuestEntry;
    $entry = $action->execute($group, 'Guest', null, [
        ['project_gear_item_id' => $gearItem->id, 'quantity' => 3],
    ]);

    expect($entry->gear)->toHaveCount(1)
        ->and($entry->gear->first()->project_gear_item_id)->toBe($gearItem->id)
        ->and($entry->gear->first()->quantity)->toBe(3);
});

it('assigns gear with selection', function () {
    $guestList = GuestList::factory()->create();
    $group = GuestGroup::factory()->for($guestList)->create();
    $gearItem = ProjectGearItem::factory()->create();

    $action = new AddGuestEntry;
    $entry = $action->execute($group, 'Guest', null, [
        ['project_gear_item_id' => $gearItem->id, 'quantity' => 1, 'selection' => 'XL'],
    ]);

    expect($entry->gear->first()->selection)->toBe('XL');
});

it('adds entry with no name and no email', function () {
    $group = GuestGroup::factory()->create();

    $action = new AddGuestEntry;
    $entry = $action->execute($group);

    expect($entry->name)->toBeNull()
        ->and($entry->email)->toBeNull()
        ->and($entry->number)->toBe(1);
});

it('auto-increments number correctly with gaps', function () {
    $group = GuestGroup::factory()->create();
    GuestEntry::factory()->for($group, 'group')->create(['number' => 1]);
    GuestEntry::factory()->for($group, 'group')->create(['number' => 3]); // gap at 2

    $action = new AddGuestEntry;
    $entry = $action->execute($group, 'New Guest');

    // Should use max+1, not fill gaps
    expect($entry->number)->toBe(4);
});
