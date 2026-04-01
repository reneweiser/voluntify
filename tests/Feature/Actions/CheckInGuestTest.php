<?php

use App\Actions\CheckInGuest;
use App\Exceptions\DomainException;
use App\Models\GuestEntry;
use App\Models\User;

it('sets checked_in_at and checked_in_by', function () {
    $user = User::factory()->create();
    $entry = GuestEntry::factory()->create();

    $action = new CheckInGuest;
    $result = $action->execute($entry, $user->id);

    expect($result->checked_in_at)->not->toBeNull()
        ->and($result->checked_in_by)->toBe($user->id);
});

it('rejects already checked-in guest to prevent duplicate check-ins', function () {
    $entry = GuestEntry::factory()->checkedIn()->create();

    $action = new CheckInGuest;

    expect(fn () => $action->execute($entry))->toThrow(DomainException::class, 'Guest is already checked in.');
});

it('accepts null checked_in_by for anonymous check-in', function () {
    $entry = GuestEntry::factory()->create();

    $action = new CheckInGuest;
    $result = $action->execute($entry);

    expect($result->checked_in_at)->not->toBeNull()
        ->and($result->checked_in_by)->toBeNull();
});
