<?php

use App\Models\ShiftReservation;

it('runs and releases expired reservations', function () {
    ShiftReservation::factory()->expired()->count(3)->create();
    ShiftReservation::factory()->create(['expires_at' => now()->addMinutes(10)]);

    $this->artisan('app:release-expired-reservations')
        ->expectsOutputToContain('Released 3 expired reservation(s).')
        ->assertExitCode(0);

    expect(ShiftReservation::count())->toBe(1);
});

it('outputs count of released reservations', function () {
    ShiftReservation::factory()->expired()->count(5)->create();

    $this->artisan('app:release-expired-reservations')
        ->expectsOutputToContain('Released 5 expired reservation(s).')
        ->assertExitCode(0);
});

it('outputs nothing when no expired reservations exist', function () {
    ShiftReservation::factory()->create(['expires_at' => now()->addMinutes(10)]);

    $this->artisan('app:release-expired-reservations')
        ->doesntExpectOutputToContain('Released')
        ->assertExitCode(0);
});
