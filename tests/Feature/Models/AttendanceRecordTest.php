<?php

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\ShiftSignup;
use App\Models\User;

it('belongs to a shift signup', function () {
    $signup = ShiftSignup::factory()->create();
    $record = AttendanceRecord::factory()->create(['shift_signup_id' => $signup->id]);

    expect($record->shiftSignup->id)->toBe($signup->id);
});

it('belongs to a recorder (user)', function () {
    $user = User::factory()->create();
    $record = AttendanceRecord::factory()->create(['recorded_by' => $user->id]);

    expect($record->recorder->id)->toBe($user->id);
});

it('casts status to AttendanceStatus enum', function () {
    $record = AttendanceRecord::factory()->create(['status' => AttendanceStatus::OnTime]);

    expect($record->status)->toBe(AttendanceStatus::OnTime);
});

it('casts recorded_at to datetime', function () {
    $record = AttendanceRecord::factory()->create();

    expect($record->recorded_at)->toBeInstanceOf(\Carbon\CarbonInterface::class);
});

it('enforces unique shift_signup_id', function () {
    $signup = ShiftSignup::factory()->create();
    AttendanceRecord::factory()->create(['shift_signup_id' => $signup->id]);

    expect(fn () => AttendanceRecord::factory()->create(['shift_signup_id' => $signup->id]))
        ->toThrow(\Illuminate\Database\UniqueConstraintViolationException::class);
});
