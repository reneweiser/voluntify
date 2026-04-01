<?php

use App\Models\GuestEntry;
use App\Models\GuestGroup;
use App\Models\GuestList;

it('creates a guest group with correct attributes', function () {
    $guestList = GuestList::factory()->create();
    $group = GuestGroup::factory()->create([
        'guest_list_id' => $guestList->id,
        'label' => 'DJ Soundwave',
        'guest_count' => 3,
    ]);

    expect($group->exists)->toBeTrue()
        ->and($group->label)->toBe('DJ Soundwave')
        ->and($group->guest_count)->toBe(3);
});

it('belongs to a guest list', function () {
    $guestList = GuestList::factory()->create();
    $group = GuestGroup::factory()->for($guestList)->create();

    expect($group->guestList->id)->toBe($guestList->id);
});

it('has many entries', function () {
    $group = GuestGroup::factory()->create();
    GuestEntry::factory()->count(3)->for($group, 'group')->create();

    expect($group->entries)->toHaveCount(3);
});

it('counts checked-in entries', function () {
    $group = GuestGroup::factory()->create(['guest_count' => 3]);
    GuestEntry::factory()->for($group, 'group')->checkedIn()->create(['number' => 1]);
    GuestEntry::factory()->for($group, 'group')->checkedIn()->create(['number' => 2]);
    GuestEntry::factory()->for($group, 'group')->create(['number' => 3]);

    expect($group->checkedInCount())->toBe(2);
});

it('cascades delete from guest list', function () {
    $guestList = GuestList::factory()->create();
    GuestGroup::factory()->for($guestList)->create();

    expect(GuestGroup::count())->toBe(1);

    $guestList->delete();

    expect(GuestGroup::count())->toBe(0);
});
