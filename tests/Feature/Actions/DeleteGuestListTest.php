<?php

use App\Actions\DeleteGuestList;
use App\Models\GuestEntry;
use App\Models\GuestGroup;
use App\Models\GuestList;

it('deletes guest list and cascades to groups and entries', function () {
    $guestList = GuestList::factory()->create();
    $group = GuestGroup::factory()->for($guestList)->create();
    GuestEntry::factory()->for($group, 'group')->create();

    $action = new DeleteGuestList;
    $action->execute($guestList);

    expect(GuestList::count())->toBe(0)
        ->and(GuestGroup::count())->toBe(0)
        ->and(GuestEntry::count())->toBe(0);
});

it('handles empty guest list deletion', function () {
    $guestList = GuestList::factory()->create();

    $action = new DeleteGuestList;
    $action->execute($guestList);

    expect(GuestList::count())->toBe(0);
});
