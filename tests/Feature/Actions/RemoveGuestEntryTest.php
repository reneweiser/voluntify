<?php

use App\Actions\RemoveGuestEntry;
use App\Models\GuestEntry;
use App\Models\GuestGroup;

it('deletes entry', function () {
    $entry = GuestEntry::factory()->create();

    $action = new RemoveGuestEntry;
    $action->execute($entry);

    expect(GuestEntry::count())->toBe(0);
});

it('does not renumber remaining entries because changing displayed labels would confuse guests who already received QR codes', function () {
    $group = GuestGroup::factory()->create(['guest_count' => 3]);
    GuestEntry::factory()->for($group, 'group')->create(['number' => 1]);
    $entryToRemove = GuestEntry::factory()->for($group, 'group')->create(['number' => 2]);
    GuestEntry::factory()->for($group, 'group')->create(['number' => 3]);

    $action = new RemoveGuestEntry;
    $action->execute($entryToRemove);

    $remaining = GuestEntry::orderBy('number')->pluck('number')->all();
    expect($remaining)->toBe([1, 3]);
});
