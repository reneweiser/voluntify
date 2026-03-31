<?php

use App\Models\ShiftReservation;

it('isExpired returns true for past expiry', function () {
    $reservation = ShiftReservation::factory()->expired()->create();

    expect($reservation->isExpired())->toBeTrue();
});

it('isExpired returns false for future expiry', function () {
    $reservation = ShiftReservation::factory()->create();

    expect($reservation->isExpired())->toBeFalse();
});

it('scopeActive filters to non-expired reservations', function () {
    ShiftReservation::factory()->create(['expires_at' => now()->addMinutes(10)]);
    ShiftReservation::factory()->expired()->create();

    $active = ShiftReservation::active()->get();

    expect($active)->toHaveCount(1);
});

it('scopeExpired filters to expired reservations', function () {
    ShiftReservation::factory()->create(['expires_at' => now()->addMinutes(10)]);
    ShiftReservation::factory()->expired()->create();

    $expired = ShiftReservation::expired()->get();

    expect($expired)->toHaveCount(1);
});

it('scopeForSession filters by session ID', function () {
    ShiftReservation::factory()->create(['session_id' => 'session-abc']);
    ShiftReservation::factory()->create(['session_id' => 'session-xyz']);

    $results = ShiftReservation::forSession('session-abc')->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->session_id)->toBe('session-abc');
});
