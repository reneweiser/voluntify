<?php

use App\Actions\RemoveGuestGroup;
use App\Models\GuestEntry;
use App\Models\GuestGroup;
use App\Models\GuestList;

it('deletes group and cascades entries', function () {
    $group = GuestGroup::factory()->create();
    GuestEntry::factory()->count(3)->for($group, 'group')->create();

    $action = new RemoveGuestGroup;
    $action->execute($group);

    expect(GuestGroup::count())->toBe(0)
        ->and(GuestEntry::count())->toBe(0);
});

it('works on confirmed list groups', function () {
    $guestList = GuestList::factory()->confirmed()->create();
    $group = GuestGroup::factory()->for($guestList)->create();
    GuestEntry::factory()->for($group, 'group')->withQrToken()->create();

    $action = new RemoveGuestGroup;
    $action->execute($group);

    expect(GuestGroup::count())->toBe(0);
});
