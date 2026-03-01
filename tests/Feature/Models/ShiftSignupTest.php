<?php

use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Volunteer;

it('enforces unique volunteer per shift', function () {
    $volunteer = Volunteer::factory()->create();
    $shift = Shift::factory()->create();

    ShiftSignup::factory()->for($volunteer)->for($shift)->create();

    expect(fn () => ShiftSignup::factory()->for($volunteer)->for($shift)->create())
        ->toThrow(\Illuminate\Database\UniqueConstraintViolationException::class);
});
