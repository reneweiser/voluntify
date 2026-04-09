<?php

use App\Actions\UpdateGuestEntry;
use App\Jobs\SendGuestInvitationsJob;
use App\Models\GuestEntry;
use App\Models\GuestEntryGear;
use App\Models\GuestGroup;
use App\Models\GuestList;
use App\Models\ProjectGearItem;
use Illuminate\Support\Facades\Queue;

it('updates name and email while preserving QR token', function () {
    $entry = GuestEntry::factory()->withQrToken()->create([
        'name' => 'Old Name',
        'email' => 'old@example.com',
    ]);

    $originalToken = $entry->qr_token;
    $action = new UpdateGuestEntry;

    $result = $action->execute($entry, [
        'name' => 'New Name',
        'email' => 'new@example.com',
    ]);

    expect($result->name)->toBe('New Name')
        ->and($result->email)->toBe('new@example.com')
        ->and($result->qr_token)->toBe($originalToken);
});

it('updates gear selections via upsert', function () {
    $entry = GuestEntry::factory()->create();
    $gearItem = ProjectGearItem::factory()->create();

    GuestEntryGear::factory()->create([
        'guest_entry_id' => $entry->id,
        'project_gear_item_id' => $gearItem->id,
        'quantity' => 1,
    ]);

    $action = new UpdateGuestEntry;
    $result = $action->execute($entry, [
        'gear' => [
            ['project_gear_item_id' => $gearItem->id, 'quantity' => 5],
        ],
    ]);

    expect($result->gear)->toHaveCount(1)
        ->and($result->gear->first()->quantity)->toBe(5);
});

it('allows setting name to null', function () {
    $entry = GuestEntry::factory()->create(['name' => 'Some Name']);

    $action = new UpdateGuestEntry;
    $result = $action->execute($entry, ['name' => null]);

    expect($result->name)->toBeNull();
});

it('preserves email when only name is updated', function () {
    $entry = GuestEntry::factory()->create([
        'name' => 'Old Name',
        'email' => 'keep@example.com',
    ]);

    $action = new UpdateGuestEntry;
    $result = $action->execute($entry, ['name' => 'New Name']);

    expect($result->name)->toBe('New Name')
        ->and($result->email)->toBe('keep@example.com');
});

it('dispatches email when email is added to entry on confirmed list', function () {
    Queue::fake();

    $guestList = GuestList::factory()->confirmed()->create();
    $group = GuestGroup::factory()->for($guestList)->create();
    $entry = GuestEntry::factory()->for($group, 'group')->withQrToken()->create([
        'name' => 'Unnamed Guest',
        'email' => null,
    ]);

    $action = new UpdateGuestEntry;
    $action->execute($entry, ['email' => 'newguest@example.com']);

    Queue::assertPushed(SendGuestInvitationsJob::class, function ($job) {
        return $job->email === 'newguest@example.com';
    });
});

it('dispatches email when email is changed on confirmed list', function () {
    Queue::fake();

    $guestList = GuestList::factory()->confirmed()->create();
    $group = GuestGroup::factory()->for($guestList)->create();
    $entry = GuestEntry::factory()->for($group, 'group')->withQrToken()->create([
        'email' => 'old@example.com',
    ]);

    $action = new UpdateGuestEntry;
    $action->execute($entry, ['email' => 'new@example.com']);

    Queue::assertPushed(SendGuestInvitationsJob::class, function ($job) {
        return $job->email === 'new@example.com';
    });
});

it('does not dispatch email when email is updated on draft list', function () {
    Queue::fake();

    $guestList = GuestList::factory()->create();
    $group = GuestGroup::factory()->for($guestList)->create();
    $entry = GuestEntry::factory()->for($group, 'group')->create(['email' => null]);

    $action = new UpdateGuestEntry;
    $action->execute($entry, ['email' => 'draft@example.com']);

    Queue::assertNothingPushed();
});

it('generates QR token when email is added to entry without one on confirmed list', function () {
    Queue::fake();

    $guestList = GuestList::factory()->confirmed()->create();
    $group = GuestGroup::factory()->for($guestList)->create();
    $entry = GuestEntry::factory()->for($group, 'group')->create([
        'email' => null,
        'qr_token' => null,
    ]);

    $action = new UpdateGuestEntry;
    $result = $action->execute($entry, ['email' => 'newguest@example.com']);

    expect($result->qr_token)->not->toBeNull()
        ->and(strlen($result->qr_token))->toBe(64);

    Queue::assertPushed(SendGuestInvitationsJob::class);
});

it('does not dispatch email when only name is updated on confirmed list', function () {
    Queue::fake();

    $guestList = GuestList::factory()->confirmed()->create();
    $group = GuestGroup::factory()->for($guestList)->create();
    $entry = GuestEntry::factory()->for($group, 'group')->withQrToken()->create([
        'name' => 'Old',
        'email' => 'existing@example.com',
    ]);

    $action = new UpdateGuestEntry;
    $action->execute($entry, ['name' => 'New Name']);

    Queue::assertNothingPushed();
});

it('adds new gear to entry without removing existing gear', function () {
    $entry = GuestEntry::factory()->create();
    $gearItem1 = ProjectGearItem::factory()->create();
    $gearItem2 = ProjectGearItem::factory()->create();

    GuestEntryGear::factory()->create([
        'guest_entry_id' => $entry->id,
        'project_gear_item_id' => $gearItem1->id,
        'quantity' => 1,
    ]);

    $action = new UpdateGuestEntry;
    $result = $action->execute($entry, [
        'gear' => [
            ['project_gear_item_id' => $gearItem2->id, 'quantity' => 2],
        ],
    ]);

    // Should now have both gear items
    expect($result->gear)->toHaveCount(2);
});
