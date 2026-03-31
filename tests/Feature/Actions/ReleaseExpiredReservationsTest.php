<?php

use App\Actions\ReleaseExpiredReservations;
use App\Models\ShiftReservation;

it('deletes expired reservations and returns count', function () {
    ShiftReservation::factory()->expired()->count(3)->create();

    $action = new ReleaseExpiredReservations;
    $count = $action->execute();

    expect($count)->toBe(3)
        ->and(ShiftReservation::count())->toBe(0);
});

it('does not delete active non-expired reservations', function () {
    ShiftReservation::factory()->create(['expires_at' => now()->addMinutes(10)]);

    $action = new ReleaseExpiredReservations;
    $count = $action->execute();

    expect($count)->toBe(0)
        ->and(ShiftReservation::count())->toBe(1);
});

it('returns 0 when no expired reservations exist', function () {
    $action = new ReleaseExpiredReservations;
    $count = $action->execute();

    expect($count)->toBe(0);
});

it('handles mix of expired and active reservations', function () {
    ShiftReservation::factory()->expired()->count(2)->create();
    ShiftReservation::factory()->create(['expires_at' => now()->addMinutes(10)]);

    $action = new ReleaseExpiredReservations;
    $count = $action->execute();

    expect($count)->toBe(2)
        ->and(ShiftReservation::count())->toBe(1);
});
