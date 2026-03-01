<?php

use App\Models\AttendanceRecord;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Volunteer;

it('belongs to a volunteer', function () {
    $volunteer = Volunteer::factory()->create();
    $signup = ShiftSignup::factory()->for($volunteer)->create();

    expect($signup->volunteer->id)->toBe($volunteer->id);
});

it('belongs to a shift', function () {
    $shift = Shift::factory()->create();
    $signup = ShiftSignup::factory()->for($shift)->create();

    expect($signup->shift->id)->toBe($shift->id);
});

it('has one attendance record', function () {
    $signup = ShiftSignup::factory()->create();
    AttendanceRecord::factory()->create(['shift_signup_id' => $signup->id]);

    expect($signup->attendanceRecord)->toBeInstanceOf(AttendanceRecord::class);
});

it('enforces unique volunteer per shift', function () {
    $volunteer = Volunteer::factory()->create();
    $shift = Shift::factory()->create();

    ShiftSignup::factory()->for($volunteer)->for($shift)->create();

    expect(fn () => ShiftSignup::factory()->for($volunteer)->for($shift)->create())
        ->toThrow(\Illuminate\Database\UniqueConstraintViolationException::class);
});

it('casts signed_up_at to datetime', function () {
    $signup = ShiftSignup::factory()->create();

    expect($signup->signed_up_at)->toBeInstanceOf(\Carbon\CarbonInterface::class);
});

it('casts notification flags to boolean', function () {
    $signup = ShiftSignup::factory()->create();

    expect($signup->notification_24h_sent)->toBeBool()
        ->and($signup->notification_4h_sent)->toBeBool();
});
