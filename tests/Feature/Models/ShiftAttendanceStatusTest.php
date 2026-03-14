<?php

use App\Enums\AttendanceStatus;
use App\Models\Shift;
use App\Models\VolunteerJob;
use Illuminate\Support\Carbon;

it('returns on-time when scanned before shift start', function () {
    $shift = Shift::factory()->for(VolunteerJob::factory(), 'volunteerJob')->create([
        'starts_at' => Carbon::parse('2026-03-14 10:00:00'),
        'ends_at' => Carbon::parse('2026-03-14 12:00:00'),
    ]);

    $status = $shift->attendanceStatusAt(Carbon::parse('2026-03-14 09:50:00'));

    expect($status)->toBe(AttendanceStatus::OnTime);
});

it('returns on-time when scanned exactly at shift start', function () {
    $shift = Shift::factory()->for(VolunteerJob::factory(), 'volunteerJob')->create([
        'starts_at' => Carbon::parse('2026-03-14 10:00:00'),
        'ends_at' => Carbon::parse('2026-03-14 12:00:00'),
    ]);

    $status = $shift->attendanceStatusAt(Carbon::parse('2026-03-14 10:00:00'));

    expect($status)->toBe(AttendanceStatus::OnTime);
});

it('returns late when scanned after shift start without grace', function () {
    $shift = Shift::factory()->for(VolunteerJob::factory(), 'volunteerJob')->create([
        'starts_at' => Carbon::parse('2026-03-14 10:00:00'),
        'ends_at' => Carbon::parse('2026-03-14 12:00:00'),
    ]);

    $status = $shift->attendanceStatusAt(Carbon::parse('2026-03-14 10:01:00'));

    expect($status)->toBe(AttendanceStatus::Late);
});

it('returns on-time within grace period', function () {
    $shift = Shift::factory()->for(VolunteerJob::factory(), 'volunteerJob')->create([
        'starts_at' => Carbon::parse('2026-03-14 10:00:00'),
        'ends_at' => Carbon::parse('2026-03-14 12:00:00'),
    ]);

    $status = $shift->attendanceStatusAt(Carbon::parse('2026-03-14 10:10:00'), graceMinutes: 15);

    expect($status)->toBe(AttendanceStatus::OnTime);
});

it('returns late after grace period expires', function () {
    $shift = Shift::factory()->for(VolunteerJob::factory(), 'volunteerJob')->create([
        'starts_at' => Carbon::parse('2026-03-14 10:00:00'),
        'ends_at' => Carbon::parse('2026-03-14 12:00:00'),
    ]);

    $status = $shift->attendanceStatusAt(Carbon::parse('2026-03-14 10:16:00'), graceMinutes: 15);

    expect($status)->toBe(AttendanceStatus::Late);
});

it('returns on-time at exact grace boundary', function () {
    $shift = Shift::factory()->for(VolunteerJob::factory(), 'volunteerJob')->create([
        'starts_at' => Carbon::parse('2026-03-14 10:00:00'),
        'ends_at' => Carbon::parse('2026-03-14 12:00:00'),
    ]);

    $status = $shift->attendanceStatusAt(Carbon::parse('2026-03-14 10:15:00'), graceMinutes: 15);

    expect($status)->toBe(AttendanceStatus::OnTime);
});
