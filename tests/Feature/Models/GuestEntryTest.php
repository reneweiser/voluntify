<?php

use App\Models\GuestEntry;
use App\Models\GuestEntryGear;
use App\Models\GuestGroup;
use App\Models\User;

it('creates a guest entry with correct attributes', function () {
    $group = GuestGroup::factory()->create(['label' => 'DJ Soundwave', 'guest_count' => 3]);
    $entry = GuestEntry::factory()->create([
        'guest_group_id' => $group->id,
        'number' => 1,
        'name' => 'DJ Soundwave',
        'email' => 'dj@example.com',
    ]);

    expect($entry->exists)->toBeTrue()
        ->and($entry->number)->toBe(1)
        ->and($entry->name)->toBe('DJ Soundwave')
        ->and($entry->email)->toBe('dj@example.com');
});

it('belongs to a group', function () {
    $group = GuestGroup::factory()->create();
    $entry = GuestEntry::factory()->for($group, 'group')->create();

    expect($entry->group->id)->toBe($group->id);
});

it('has many gear items', function () {
    $entry = GuestEntry::factory()->create();
    GuestEntryGear::factory()->count(2)->create(['guest_entry_id' => $entry->id]);

    expect($entry->gear)->toHaveCount(2);
});

it('tracks checked-in-by user', function () {
    $user = User::factory()->create();
    $entry = GuestEntry::factory()->checkedIn()->create(['checked_in_by' => $user->id]);

    expect($entry->checkedInByUser->id)->toBe($user->id);
});

it('reports check-in status', function () {
    $notCheckedIn = GuestEntry::factory()->create();
    $checkedIn = GuestEntry::factory()->checkedIn()->create();

    expect($notCheckedIn->isCheckedIn())->toBeFalse()
        ->and($checkedIn->isCheckedIn())->toBeTrue();
});

it('generates display label in GroupLabel N/Total format', function () {
    $group = GuestGroup::factory()->create(['label' => 'DJ Soundwave', 'guest_count' => 3]);
    $entry = GuestEntry::factory()->for($group, 'group')->create(['number' => 2]);

    expect($entry->displayLabel())->toBe('DJ Soundwave 2/3');
});

it('cascades delete from group', function () {
    $group = GuestGroup::factory()->create();
    GuestEntry::factory()->for($group, 'group')->create();

    expect(GuestEntry::count())->toBe(1);

    $group->delete();

    expect(GuestEntry::count())->toBe(0);
});

it('nullifies checked_in_by when user is deleted', function () {
    $user = User::factory()->create();
    $entry = GuestEntry::factory()->checkedIn()->create(['checked_in_by' => $user->id]);

    $user->delete();

    expect($entry->fresh()->checked_in_by)->toBeNull();
});
