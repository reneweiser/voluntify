<?php

use App\Actions\AddGuestGroup;
use App\Models\GuestEntry;
use App\Models\GuestGroup;
use App\Models\GuestList;

it('creates group with N empty entries', function () {
    $guestList = GuestList::factory()->create();
    $action = new AddGuestGroup;

    $group = $action->execute($guestList, 'DJ Soundwave', 3);

    expect($group)->toBeInstanceOf(GuestGroup::class)
        ->and($group->label)->toBe('DJ Soundwave')
        ->and($group->guest_count)->toBe(3)
        ->and($group->entries)->toHaveCount(3);

    $numbers = $group->entries->pluck('number')->sort()->values()->all();
    expect($numbers)->toBe([1, 2, 3]);
});

it('creates entries with no name or email by default', function () {
    $guestList = GuestList::factory()->create();
    $action = new AddGuestGroup;

    $group = $action->execute($guestList, 'VIP', 2);

    $group->entries->each(function (GuestEntry $entry) {
        expect($entry->name)->toBeNull()
            ->and($entry->email)->toBeNull()
            ->and($entry->qr_token)->toBeNull();
    });
});

it('requires guest count of at least 1', function () {
    $guestList = GuestList::factory()->create();
    $action = new AddGuestGroup;

    $group = $action->execute($guestList, 'Solo', 1);

    expect($group->entries)->toHaveCount(1)
        ->and($group->entries->first()->number)->toBe(1);
});
