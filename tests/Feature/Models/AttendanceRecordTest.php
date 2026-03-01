<?php

use App\Models\AttendanceRecord;
use App\Models\ShiftSignup;

it('enforces unique shift_signup_id', function () {
    $signup = ShiftSignup::factory()->create();
    AttendanceRecord::factory()->create(['shift_signup_id' => $signup->id]);

    expect(fn () => AttendanceRecord::factory()->create(['shift_signup_id' => $signup->id]))
        ->toThrow(\Illuminate\Database\UniqueConstraintViolationException::class);
});
