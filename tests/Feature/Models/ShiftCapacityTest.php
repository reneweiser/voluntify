<?php

use App\Models\Shift;
use App\Models\ShiftReservation;
use App\Models\ShiftSignup;
use App\Models\Volunteer;
use App\Models\VolunteerJob;

beforeEach(function () {
    $this->job = VolunteerJob::factory()->create();
    $this->shift = Shift::factory()->for($this->job, 'volunteerJob')->create(['capacity' => 3]);
});

it('isFull counts active reservations in addition to active signups', function () {
    $v1 = Volunteer::factory()->create();
    ShiftSignup::factory()->create(['shift_id' => $this->shift->id, 'volunteer_id' => $v1->id]);

    ShiftReservation::factory()->create([
        'shift_id' => $this->shift->id,
        'session_id' => 'session-a',
        'expires_at' => now()->addMinutes(10),
    ]);

    ShiftReservation::factory()->create([
        'shift_id' => $this->shift->id,
        'session_id' => 'session-b',
        'expires_at' => now()->addMinutes(10),
    ]);

    // 1 signup + 2 active reservations = 3 = capacity
    expect($this->shift->isFull())->toBeTrue();
});

it('spotsRemaining subtracts active reservations', function () {
    ShiftReservation::factory()->create([
        'shift_id' => $this->shift->id,
        'session_id' => 'session-a',
        'expires_at' => now()->addMinutes(10),
    ]);

    // capacity 3 - 0 signups - 1 reservation = 2
    expect($this->shift->spotsRemaining())->toBe(2);
});

it('isFull does not count expired reservations', function () {
    $v1 = Volunteer::factory()->create();
    $v2 = Volunteer::factory()->create();
    ShiftSignup::factory()->create(['shift_id' => $this->shift->id, 'volunteer_id' => $v1->id]);
    ShiftSignup::factory()->create(['shift_id' => $this->shift->id, 'volunteer_id' => $v2->id]);

    // Expired reservation should NOT count
    ShiftReservation::factory()->expired()->create([
        'shift_id' => $this->shift->id,
        'session_id' => 'expired-session',
    ]);

    // 2 signups + 0 active reservations = 2 < 3 = not full
    expect($this->shift->isFull())->toBeFalse()
        ->and($this->shift->spotsRemaining())->toBe(1);
});

it('isFull uses eager-loaded counts when available', function () {
    $shift = Shift::withCount(['activeSignups', 'activeReservations'])
        ->find($this->shift->id);

    expect($shift->isFull())->toBeFalse()
        ->and($shift->spotsRemaining())->toBe(3);
});

it('isFull returns true when only reservations fill capacity', function () {
    for ($i = 0; $i < 3; $i++) {
        ShiftReservation::factory()->create([
            'shift_id' => $this->shift->id,
            'session_id' => "session-{$i}",
            'expires_at' => now()->addMinutes(10),
        ]);
    }

    expect($this->shift->isFull())->toBeTrue()
        ->and($this->shift->spotsRemaining())->toBe(0);
});
